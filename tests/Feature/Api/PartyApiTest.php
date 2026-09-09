<?php

use App\Models\Party;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

// ── Authentification ─────────────────────────────────────────────────────────

it('rejects unauthenticated requests on index', function () {
    $this->getJson('/api/parties')->assertUnauthorized();
});

it('rejects unauthenticated requests on show', function () {
    $party = Party::factory()->create();

    $this->getJson(route('parties.show', $party))->assertUnauthorized();
});

it('rejects unauthenticated requests on store', function () {
    $this->postJson('/api/parties', ['name' => 'test'])->assertUnauthorized();
});

it('rejects unauthenticated requests on update', function () {
    $party = Party::factory()->create();

    $this->putJson(route('parties.show', $party), ['name' => 'updated'])->assertUnauthorized();
});

it('rejects unauthenticated requests on destroy', function () {
    $party = Party::factory()->create();

    $this->deleteJson(route('parties.show', $party))->assertUnauthorized();
});

// ── Index ────────────────────────────────────────────────────────────────────

it('returns a list of parties', function () {
    Sanctum::actingAs(User::factory()->create());
    Party::factory()->count(3)->create();

    $this->getJson('/api/parties')
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'name', 'user_id', 'expire_at', 'created_at', 'updated_at'],
            ],
        ]);
});

it('returns an empty list when no parties exist', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/parties')
        ->assertOk()
        ->assertJsonCount(0, 'data');
});

// ── Show ─────────────────────────────────────────────────────────────────────

it('returns a single party', function () {
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->create(['name' => 'gala-night']);

    $this->getJson(route('parties.show', $party))
        ->assertOk()
        ->assertJsonPath('data.id', $party->id)
        ->assertJsonPath('data.name', 'gala-night');
});

it('returns 404 for a non-existent party', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/unknown-party')->assertNotFound();
});

// ── Store ────────────────────────────────────────────────────────────────────

it('creates a party with valid data', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/parties', ['name' => 'new-party'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'new-party')
        ->assertJsonPath('data.user_id', $user->id);

    $this->assertDatabaseHas('parties', ['name' => 'new-party', 'user_id' => $user->id]);
});

it('creates a party with an expiry date', function () {
    Sanctum::actingAs(User::factory()->create());
    $expireAt = now()->addWeek()->startOfMinute()->toIso8601String();

    $this->postJson('/api/parties', ['name' => 'timed-party', 'expire_at' => $expireAt])
        ->assertCreated()
        ->assertJsonPath('data.expire_at', $expireAt);
});

it('assigns the authenticated user as owner on creation', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/parties', ['name' => 'owned-party']);

    $response->assertCreated();
    $this->assertDatabaseHas('parties', ['id' => $response->json('data.id'), 'user_id' => $user->id]);
});

// ── Store – Validation ───────────────────────────────────────────────────────

it('rejects store when name is missing', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/parties', [])
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('name');
});

it('rejects store when name exceeds 255 characters', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/parties', ['name' => str_repeat('a', 256)])
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('name');
});

it('rejects store when expire_at is not a valid date', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/parties', ['name' => 'party', 'expire_at' => 'not-a-date'])
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('expire_at');
});

it('accepts store when expire_at is null', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/parties', ['name' => 'no-expiry', 'expire_at' => null])
        ->assertCreated()
        ->assertJsonPath('data.expire_at', null);
});

// ── Update ───────────────────────────────────────────────────────────────────

it('updates a party with valid data', function () {
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->create(['name' => 'old-name']);

    $this->putJson(route('parties.show', $party), ['name' => 'new-name'])
        ->assertOk()
        ->assertJsonPath('data.name', 'new-name');

    $this->assertDatabaseHas('parties', ['id' => $party->id, 'name' => 'new-name']);
});

it('updates only the provided fields (partial update)', function () {
    Sanctum::actingAs(User::factory()->create());
    $expireAt = now()->addMonth()->startOfMinute();
    $party = Party::factory()->expiresAt($expireAt)->create(['name' => 'keep-me']);

    $this->patchJson(route('parties.show', $party), ['name' => 'changed'])
        ->assertOk()
        ->assertJsonPath('data.name', 'changed')
        ->assertJsonPath('data.expire_at', $expireAt->toIso8601String());
});

it('returns 404 when updating a non-existent party', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->putJson('/api/unknown-party', ['name' => 'ghost'])->assertNotFound();
});

// ── Update – Validation ──────────────────────────────────────────────────────

it('rejects update when name is empty string', function () {
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->create();

    $this->putJson(route('parties.show', $party), ['name' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('name');
});

it('rejects update when expire_at is not a valid date', function () {
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->create();

    $this->patchJson(route('parties.show', $party), ['expire_at' => 'invalid'])
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('expire_at');
});

// ── Destroy ──────────────────────────────────────────────────────────────────

it('deletes a party', function () {
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->create();

    $this->deleteJson(route('parties.show', $party))->assertNoContent();

    $this->assertModelMissing($party);
});

it('returns 404 when deleting a non-existent party', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->deleteJson('/api/unknown-party')->assertNotFound();
});

// ── Resource structure ───────────────────────────────────────────────────────

it('returns the correct JSON structure for a party resource', function () {
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->withOwner()->expiresAt(now()->addWeek())->create();

    $this->getJson(route('parties.show', $party))
        ->assertOk()
        ->assertExactJsonStructure([
            'data' => ['id', 'name', 'display_name', 'description', 'banner_url', 'banner_position_x', 'banner_position_y', 'banner_zoom', 'user_id', 'expire_at', 'created_at', 'updated_at'],
        ]);
});

it('does not expose unexpected fields in the party resource', function () {
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->create();

    $this->getJson(route('parties.show', $party))
        ->assertOk()
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.remember_token');
});
