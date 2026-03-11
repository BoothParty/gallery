<?php

use App\Models\Party;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

// ── Authentification ─────────────────────────────────────────────────────────

it('rejects unauthenticated requests on index', function () {
    $this->getJson('/api/parties')->assertUnauthorized();
});

it('rejects unauthenticated requests on show', function () {
    $party = Party::factory()->create();

    $this->getJson("/api/parties/{$party->id}")->assertUnauthorized();
});

it('rejects unauthenticated requests on store', function () {
    $this->postJson('/api/parties', ['name' => 'Test'])->assertUnauthorized();
});

it('rejects unauthenticated requests on update', function () {
    $party = Party::factory()->create();

    $this->putJson("/api/parties/{$party->id}", ['name' => 'Updated'])->assertUnauthorized();
});

it('rejects unauthenticated requests on destroy', function () {
    $party = Party::factory()->create();

    $this->deleteJson("/api/parties/{$party->id}")->assertUnauthorized();
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
    $party = Party::factory()->create(['name' => 'Gala Night']);

    $this->getJson("/api/parties/{$party->id}")
        ->assertOk()
        ->assertJsonPath('data.id', $party->id)
        ->assertJsonPath('data.name', 'Gala Night');
});

it('returns 404 for a non-existent party', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/parties/99999')->assertNotFound();
});

// ── Store ────────────────────────────────────────────────────────────────────

it('creates a party with valid data', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/parties', ['name' => 'New Party'])
        ->assertCreated()
        ->assertJsonPath('data.name', 'New Party')
        ->assertJsonPath('data.user_id', $user->id);

    $this->assertDatabaseHas('parties', ['name' => 'New Party', 'user_id' => $user->id]);
});

it('creates a party with an expiry date', function () {
    Sanctum::actingAs(User::factory()->create());
    $expireAt = now()->addWeek()->startOfMinute()->toIso8601String();

    $this->postJson('/api/parties', ['name' => 'Timed Party', 'expire_at' => $expireAt])
        ->assertCreated()
        ->assertJsonPath('data.expire_at', $expireAt);
});

it('assigns the authenticated user as owner on creation', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $response = $this->postJson('/api/parties', ['name' => 'Owned Party']);

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

    $this->postJson('/api/parties', ['name' => 'Party', 'expire_at' => 'not-a-date'])
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('expire_at');
});

it('accepts store when expire_at is null', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/parties', ['name' => 'No Expiry', 'expire_at' => null])
        ->assertCreated()
        ->assertJsonPath('data.expire_at', null);
});

// ── Update ───────────────────────────────────────────────────────────────────

it('updates a party with valid data', function () {
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->create(['name' => 'Old Name']);

    $this->putJson("/api/parties/{$party->id}", ['name' => 'New Name'])
        ->assertOk()
        ->assertJsonPath('data.name', 'New Name');

    $this->assertDatabaseHas('parties', ['id' => $party->id, 'name' => 'New Name']);
});

it('updates only the provided fields (partial update)', function () {
    Sanctum::actingAs(User::factory()->create());
    $expireAt = now()->addMonth()->startOfMinute();
    $party = Party::factory()->expiresAt($expireAt)->create(['name' => 'Keep Me']);

    $this->patchJson("/api/parties/{$party->id}", ['name' => 'Changed'])
        ->assertOk()
        ->assertJsonPath('data.name', 'Changed')
        ->assertJsonPath('data.expire_at', $expireAt->toIso8601String());
});

it('returns 404 when updating a non-existent party', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->putJson('/api/parties/99999', ['name' => 'Ghost'])->assertNotFound();
});

// ── Update – Validation ──────────────────────────────────────────────────────

it('rejects update when name is empty string', function () {
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->create();

    $this->putJson("/api/parties/{$party->id}", ['name' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('name');
});

it('rejects update when expire_at is not a valid date', function () {
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->create();

    $this->patchJson("/api/parties/{$party->id}", ['expire_at' => 'invalid'])
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('expire_at');
});

// ── Destroy ──────────────────────────────────────────────────────────────────

it('deletes a party', function () {
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->create();

    $this->deleteJson("/api/parties/{$party->id}")->assertNoContent();

    $this->assertDatabaseMissing('parties', ['id' => $party->id]);
});

it('returns 404 when deleting a non-existent party', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->deleteJson('/api/parties/99999')->assertNotFound();
});

// ── Resource structure ───────────────────────────────────────────────────────

it('returns the correct JSON structure for a party resource', function () {
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->withOwner()->expiresAt(now()->addWeek())->create();

    $this->getJson("/api/parties/{$party->id}")
        ->assertOk()
        ->assertExactJsonStructure([
            'data' => ['id', 'name', 'user_id', 'expire_at', 'created_at', 'updated_at'],
        ]);
});

it('does not expose unexpected fields in the party resource', function () {
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->create();

    $this->getJson("/api/parties/{$party->id}")
        ->assertOk()
        ->assertJsonMissingPath('data.password')
        ->assertJsonMissingPath('data.remember_token');
});
