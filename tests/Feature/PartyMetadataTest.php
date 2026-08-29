<?php

use App\Models\Party;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
});

/**
 * @param  array<string, mixed>  $payload
 */
function hostUpdate(Party $party, array $payload = []): TestResponse
{
    return test()
        ->withSession(['party_host' => [$party->id => true]])
        ->post(route('party.metadata.update', $party), [
            '_method' => 'put',
            'display_name' => 'Camille & Antoine',
            'description' => 'Samedi 12 septembre · Domaine des Ormes',
            'banner_position_x' => 50,
            'banner_position_y' => 50,
            'banner_zoom' => 1,
            ...$payload,
        ]);
}

// ── Authorisation ───────────────────────────────────────────────────────────

it('rejects a write without a host session', function () {
    $party = Party::factory()->create();

    $this->put(route('party.metadata.update', $party), [
        'display_name' => 'Pirate',
        'banner_position_x' => 50,
        'banner_position_y' => 50,
        'banner_zoom' => 1,
    ])->assertForbidden();

    expect($party->fresh()->display_name)->toBeNull();
});

it('rejects a write carrying the host session of another party', function () {
    $mine = Party::factory()->create();
    $other = Party::factory()->create();

    $this->withSession(['party_host' => [$mine->id => true]])
        ->put(route('party.metadata.update', $other), [
            'display_name' => 'Pirate',
            'banner_position_x' => 50,
            'banner_position_y' => 50,
            'banner_zoom' => 1,
        ])->assertForbidden();
});

// ── Writing the metadata ────────────────────────────────────────────────────

it('writes the title, the description and the framing', function () {
    $party = Party::factory()->create();

    hostUpdate($party, [
        'banner_position_x' => 24.5,
        'banner_position_y' => 76,
        'banner_zoom' => 1.8,
    ])->assertRedirect();

    $party->refresh();

    expect($party->display_name)->toBe('Camille & Antoine')
        ->and($party->description)->toBe('Samedi 12 septembre · Domaine des Ormes')
        ->and($party->banner_position_x)->toBe(24.5)
        ->and($party->banner_position_y)->toBe(76.0)
        ->and($party->banner_zoom)->toBe(1.8);
});

it('stores an uploaded banner', function () {
    $party = Party::factory()->create();

    hostUpdate($party, ['banner' => UploadedFile::fake()->image('cover.jpg', 3000, 1200)]);

    $party->refresh();

    expect($party->banner_path)->not->toBeNull();
    Storage::disk('s3')->assertExists($party->banner_path);
});

it('drops the banner and recentres the framing on removal', function () {
    $party = Party::factory()->create();

    hostUpdate($party, ['banner' => UploadedFile::fake()->image('cover.jpg', 3000, 1200)]);
    $storedPath = $party->fresh()->banner_path;

    hostUpdate($party->fresh(), [
        'remove_banner' => true,
        'banner_position_x' => 10,
        'banner_position_y' => 90,
        'banner_zoom' => 2.4,
    ]);

    $party->refresh();

    expect($party->banner_path)->toBeNull()
        ->and($party->banner_position_x)->toBe(50.0)
        ->and($party->banner_position_y)->toBe(50.0)
        ->and($party->banner_zoom)->toBe(1.0);
    Storage::disk('s3')->assertMissing($storedPath);
});

it('lets removal win over an upload sent in the same submission', function () {
    $party = Party::factory()->create();

    hostUpdate($party, [
        'remove_banner' => true,
        'banner' => UploadedFile::fake()->image('cover.jpg', 3000, 1200),
    ]);

    expect($party->fresh()->banner_path)->toBeNull();
});

// ── Validation ──────────────────────────────────────────────────────────────

it('rejects a title longer than 60 characters', function () {
    $party = Party::factory()->create();

    hostUpdate($party, ['display_name' => str_repeat('a', 61)])
        ->assertSessionHasErrors('display_name');
});

it('rejects a description longer than 90 characters', function () {
    $party = Party::factory()->create();

    hostUpdate($party, ['description' => str_repeat('a', 91)])
        ->assertSessionHasErrors('description');
});

it('rejects a zoom outside 1 to 3', function () {
    $party = Party::factory()->create();

    hostUpdate($party, ['banner_zoom' => 3.5])->assertSessionHasErrors('banner_zoom');
});

it('rejects a position outside 0 to 100', function () {
    $party = Party::factory()->create();

    hostUpdate($party, ['banner_position_x' => 140])->assertSessionHasErrors('banner_position_x');
});

it('rejects a banner that is not an image', function () {
    $party = Party::factory()->create();

    hostUpdate($party, ['banner' => UploadedFile::fake()->create('notes.pdf', 12)])
        ->assertSessionHasErrors('banner');
});
