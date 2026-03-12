<?php

use App\Models\Party;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
});

// ── Authentification ─────────────────────────────────────────────────────────

it('rejects unauthenticated requests on store', function () {
    $party = Party::factory()->create();

    $this->postJson("/api/parties/{$party->id}/pictures", [
        'photo' => UploadedFile::fake()->image('booth.jpg'),
    ])->assertUnauthorized();
});

// ── Store ────────────────────────────────────────────────────────────────────

it('creates a picture for a given party', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $party = Party::factory()->create();

    $this->postJson("/api/parties/{$party->id}/pictures", [
        'photo' => UploadedFile::fake()->image('booth.jpg'),
    ])
        ->assertCreated()
        ->assertJsonPath('data.party_id', $party->id);

    $this->assertDatabaseHas('pictures', ['party_id' => $party->id]);
});

it('stores the file on the s3 disk', function () {
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->create();

    $response = $this->postJson("/api/parties/{$party->id}/pictures", [
        'photo' => UploadedFile::fake()->image('booth.jpg'),
    ])->assertCreated();

    Storage::disk('s3')->assertExists($response->json('data.path'));
});

it('stores the file under the pictures/{party_id} directory', function () {
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->create();

    $response = $this->postJson("/api/parties/{$party->id}/pictures", [
        'photo' => UploadedFile::fake()->image('booth.jpg'),
    ])->assertCreated();

    expect($response->json('data.path'))->toStartWith("pictures/{$party->id}/");
});

it('returns the correct JSON structure for a picture resource', function () {
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->create();

    $this->postJson("/api/parties/{$party->id}/pictures", [
        'photo' => UploadedFile::fake()->image('booth.jpg'),
    ])
        ->assertCreated()
        ->assertExactJsonStructure([
            'data' => ['id', 'party_id', 'path', 'url', 'created_at', 'updated_at'],
        ]);
});

it('returns 404 when the party does not exist', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/parties/99999/pictures', [
        'photo' => UploadedFile::fake()->image('booth.jpg'),
    ])->assertNotFound();
});

// ── Store – Validation ───────────────────────────────────────────────────────

it('rejects store when photo is missing', function () {
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->create();

    $this->postJson("/api/parties/{$party->id}/pictures", [])
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('photo');
});

it('rejects store when photo is not an image', function () {
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->create();

    $this->postJson("/api/parties/{$party->id}/pictures", [
        'photo' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('photo');
});

it('rejects store when photo exceeds max size', function () {
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->create();

    $this->postJson("/api/parties/{$party->id}/pictures", [
        'photo' => UploadedFile::fake()->image('huge.jpg')->size(10241),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('photo');
});
