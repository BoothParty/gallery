<?php

use App\Models\Party;
use App\Models\Picture;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

use function Pest\Laravel\assertDatabaseHas;
use function Pest\Laravel\postJson;

beforeEach(function () {
    Storage::fake('s3');
});

test('can upload an image to a party', function () {
    $party = Party::factory()->create([
        'expiration_date' => now()->addDays(7),
    ]);

    $file = UploadedFile::fake()->image('test-photo.jpg', 800, 600)->size(500);

    $response = postJson("/api/parties/{$party->id}/pictures", [
        'image' => $file,
    ]);

    $response->assertStatus(201)
        ->assertJsonStructure([
            'message',
            'data' => [
                'id',
                'url',
                'original_name',
                'size',
                'uploaded_at',
            ],
        ])
        ->assertJson([
            'message' => 'Picture uploaded successfully.',
            'data' => [
                'original_name' => 'test-photo.jpg',
            ],
        ]);

    assertDatabaseHas('pictures', [
        'party_id' => $party->id,
        'original_name' => 'test-photo.jpg',
        'mime_type' => 'image/jpeg',
    ]);

    $picture = Picture::latest()->first();
    Storage::disk('s3')->assertExists($picture->path);
});

test('validates image is required', function () {
    $party = Party::factory()->create([
        'expiration_date' => now()->addDays(7),
    ]);

    $response = postJson("/api/parties/{$party->id}/pictures", []);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['image']);
});

test('validates file must be an image', function () {
    $party = Party::factory()->create([
        'expiration_date' => now()->addDays(7),
    ]);

    $file = UploadedFile::fake()->create('document.pdf', 100);

    $response = postJson("/api/parties/{$party->id}/pictures", [
        'image' => $file,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['image']);
});

test('validates image size is within limits', function () {
    $party = Party::factory()->create([
        'expiration_date' => now()->addDays(7),
    ]);

    // Test file too large (> 10 MB)
    $file = UploadedFile::fake()->image('huge.jpg')->size(11 * 1024);

    $response = postJson("/api/parties/{$party->id}/pictures", [
        'image' => $file,
    ]);

    $response->assertStatus(422)
        ->assertJsonValidationErrors(['image']);
});

test('cannot upload to non-existent party', function () {
    $file = UploadedFile::fake()->image('test.jpg');

    $response = postJson('/api/parties/99999/pictures', [
        'image' => $file,
    ]);

    $response->assertStatus(404);
});

test('cannot upload to expired party', function () {
    $party = Party::factory()->create([
        'expiration_date' => now()->subDays(1),
    ]);

    $file = UploadedFile::fake()->image('test.jpg');

    $response = postJson("/api/parties/{$party->id}/pictures", [
        'image' => $file,
    ]);

    $response->assertStatus(403);
});

test('can list all pictures for a party', function () {
    $party = Party::factory()->create();

    Picture::factory()->count(3)->create([
        'party_id' => $party->id,
    ]);

    $response = $this->getJson("/api/parties/{$party->id}/pictures");

    $response->assertStatus(200)
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id',
                    'url',
                    'original_name',
                    'mime_type',
                    'size',
                    'uploaded_at',
                ],
            ],
        ])
        ->assertJsonCount(3, 'data');
});

test('can delete a picture from a party', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->create([
        'party_id' => $party->id,
        'path' => 'parties/1/test.jpg',
    ]);

    Storage::disk('s3')->put($picture->path, 'fake content');

    $response = $this->deleteJson("/api/parties/{$party->id}/pictures/{$picture->id}");

    $response->assertStatus(200)
        ->assertJson([
            'message' => 'Picture deleted successfully.',
        ]);

    expect(Picture::find($picture->id))->toBeNull();
    Storage::disk('s3')->assertMissing($picture->path);
});

test('cannot delete picture from wrong party', function () {
    $party1 = Party::factory()->create();
    $party2 = Party::factory()->create();

    $picture = Picture::factory()->create([
        'party_id' => $party1->id,
    ]);

    $response = $this->deleteJson("/api/parties/{$party2->id}/pictures/{$picture->id}");

    $response->assertStatus(404)
        ->assertJson([
            'message' => 'Picture does not belong to this party.',
        ]);

    expect(Picture::find($picture->id))->not->toBeNull();
});
