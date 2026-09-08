<?php

use App\Jobs\CreatePictureThumbnail;
use App\Models\Party;
use App\Models\Picture;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('s3');
});

// ── Authentification ─────────────────────────────────────────────────────────

it('rejects unauthenticated requests on index', function () {
    $party = Party::factory()->create();

    $this->getJson(route('pictures.index', $party))
        ->assertUnauthorized();
});

it('rejects unauthenticated requests on store', function () {
    $party = Party::factory()->create();

    $this->postJson(route('pictures.store', $party), [
        'photo' => UploadedFile::fake()->image('booth.jpg'),
    ])->assertUnauthorized();
});

it('rejects unauthenticated requests on show', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create();

    $this->getJson(route('pictures.show', ['party' => $party, 'picture' => $picture]))
        ->assertUnauthorized();
});

it('rejects unauthenticated requests on destroy', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create();

    $this->deleteJson(route('pictures.destroy', ['party' => $party, 'picture' => $picture]))
        ->assertUnauthorized();
});

// ── Index ────────────────────────────────────────────────────────────────────

it('returns all pictures for a given party', function () {
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->create();
    Picture::factory()->count(3)->for($party)->create();

    $this->getJson(route('pictures.index', $party))
        ->assertOk()
        ->assertJsonCount(3, 'data');
});

it('returns the correct JSON structure for the index', function () {
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->create();
    Picture::factory()->for($party)->create();

    $this->getJson(route('pictures.index', $party))
        ->assertOk()
        ->assertJsonStructure([
            'data' => [
                '*' => ['id', 'party_id', 'url', 'thumbnail_url', 'width', 'height', 'created_at', 'updated_at'],
            ],
        ]);
});

it('returns 404 on index when the party does not exist', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/unknown-party/pictures')
        ->assertNotFound();
});

// ── Show ─────────────────────────────────────────────────────────────────────

it('returns a single picture', function () {
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create();

    $this->getJson(route('pictures.show', ['party' => $party, 'picture' => $picture]))
        ->assertOk()
        ->assertJsonPath('data.id', $picture->id)
        ->assertJsonPath('data.party_id', $party->id);
});

it('returns the correct JSON structure for a picture resource', function () {
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create();

    $this->getJson(route('pictures.show', ['party' => $party, 'picture' => $picture]))
        ->assertOk()
        ->assertExactJsonStructure([
            'data' => ['id', 'party_id', 'url', 'thumbnail_url', 'width', 'height', 'hidden', 'created_at', 'updated_at'],
        ]);
});

it('returns 404 on show when the picture does not exist', function () {
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->create();

    $this->getJson(route('pictures.show', ['party' => $party, 'picture' => 99999]))
        ->assertNotFound();
});

// ── Store ────────────────────────────────────────────────────────────────────

it('creates a picture for a given party', function () {
    $user = User::factory()->create();
    Sanctum::actingAs($user);
    $party = Party::factory()->create();

    $this->postJson(route('pictures.store', $party), [
        'photo' => UploadedFile::fake()->image('booth.jpg'),
    ])
        ->assertCreated()
        ->assertJsonPath('data.party_id', $party->id);

    $this->assertDatabaseHas('pictures', ['party_id' => $party->id]);
});

it('stores the file on the s3 disk', function () {
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->create();

    $response = $this->postJson(route('pictures.store', $party), [
        'photo' => UploadedFile::fake()->image('booth.jpg'),
    ])->assertCreated();

    $picture = Picture::query()->where('party_id', $party->id)->first();
    Storage::disk('s3')->assertExists($picture->path);
});

it('stores the file under the pictures/{party_id} directory', function () {
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->create();

    $this->postJson(route('pictures.store', $party), [
        'photo' => UploadedFile::fake()->image('booth.jpg'),
    ])->assertCreated();

    $picture = Picture::query()->where('party_id', $party->id)->first();
    expect($picture->path)->toStartWith("pictures/{$party->id}/");
});

it('returns the correct JSON structure on store', function () {
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->create();

    $this->postJson(route('pictures.store', $party), [
        'photo' => UploadedFile::fake()->image('booth.jpg'),
    ])
        ->assertCreated()
        ->assertExactJsonStructure([
            'data' => ['id', 'party_id', 'url', 'thumbnail_url', 'width', 'height', 'hidden', 'created_at', 'updated_at'],
        ]);
});

it('stores the picture dimensions in the database', function () {
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->create();

    $this->postJson(route('pictures.store', $party), [
        'photo' => UploadedFile::fake()->image('booth.jpg', 1600, 1200),
    ])->assertCreated();

    $this->assertDatabaseHas('pictures', [
        'party_id' => $party->id,
        'width' => 1600,
        'height' => 1200,
    ]);
});

it('returns the picture dimensions in the response', function () {
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->create();

    $this->postJson(route('pictures.store', $party), [
        'photo' => UploadedFile::fake()->image('booth.jpg', 1600, 1200),
    ])
        ->assertCreated()
        ->assertJsonPath('data.width', 1600)
        ->assertJsonPath('data.height', 1200);
});

it('returns 404 when the party does not exist', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/unknown-party/pictures', [
        'photo' => UploadedFile::fake()->image('booth.jpg'),
    ])->assertNotFound();
});

// ── Store – Validation ───────────────────────────────────────────────────────

it('rejects store when photo is missing', function () {
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->create();

    $this->postJson(route('pictures.store', $party), [])
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('photo');
});

it('rejects store when photo is not an image', function () {
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->create();

    $this->postJson(route('pictures.store', $party), [
        'photo' => UploadedFile::fake()->create('document.pdf', 100, 'application/pdf'),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('photo');
});

it('rejects store when photo exceeds max size', function () {
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->create();

    $this->postJson(route('pictures.store', $party), [
        'photo' => UploadedFile::fake()->image('huge.jpg')->size(10241),
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrorFor('photo');
});

// ── Cross-party scoping ──────────────────────────────────────────────────────

it('does not reach a picture through another party', function () {
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->create();
    $otherParty = Party::factory()->create();
    $picture = Picture::factory()->for($otherParty)->create();

    $this->getJson(route('pictures.show', ['party' => $party, 'picture' => $picture]))
        ->assertNotFound();
});

it('does not delete a picture through another party', function () {
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->create();
    $otherParty = Party::factory()->create();
    $picture = Picture::factory()->for($otherParty)->create();

    $this->deleteJson(route('pictures.destroy', ['party' => $party, 'picture' => $picture]))
        ->assertNotFound();

    $this->assertModelExists($picture);
});

// ── Destroy ───────────────────────────────────────────────────────────────────

it('deletes a picture and returns no content', function () {
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create();

    $this->deleteJson(route('pictures.destroy', ['party' => $party, 'picture' => $picture]))
        ->assertNoContent();

    $this->assertModelMissing($picture);
});

it('deletes the file from s3 when a picture is destroyed', function () {
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->create();

    $file = UploadedFile::fake()->image('booth.jpg');
    $path = Storage::disk('s3')->putFile("pictures/{$party->id}", $file);
    $picture = Picture::factory()->for($party)->create(['path' => $path]);

    Storage::disk('s3')->assertExists($path);

    $this->deleteJson(route('pictures.destroy', ['party' => $party, 'picture' => $picture]))
        ->assertNoContent();

    Storage::disk('s3')->assertMissing($path);
});

it('returns 404 on destroy when the picture does not exist', function () {
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->create();

    $this->deleteJson(route('pictures.destroy', ['party' => $party, 'picture' => 99999]))
        ->assertNotFound();
});

it('dispatches the CreatePictureThumbnail job on store', function () {
    Queue::fake();
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->create();

    $this->postJson(route('pictures.store', $party), [
        'photo' => UploadedFile::fake()->image('booth.jpg'),
    ])->assertCreated();

    $picture = Picture::query()->where('party_id', $party->id)->first();

    Queue::assertPushed(CreatePictureThumbnail::class, function ($job) use ($picture) {
        return $job->picture->id === $picture->id;
    });
});
