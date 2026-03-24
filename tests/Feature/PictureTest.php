<?php

use App\Models\Party;
use App\Models\Picture;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// ── Creation & persistence ──────────────────────────────────────────────────

it('auto-generates a path on creation', function () {
    $picture = Picture::factory()->create();

    expect($picture->path)->toBeString()
        ->toStartWith('pictures/')
        ->toEndWith('.jpg');
});

it('does not override an explicitly provided path', function () {
    $picture = Picture::factory()->create(['path' => 'pictures/custom.jpg']);

    expect($picture->path)->toBe('pictures/custom.jpg');
});

it('generates a unique path for each picture', function () {
    [$a, $b] = Picture::factory()->count(2)->create();

    expect($a->path)->not->toBe($b->path);
});

it('persists party_id', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->forParty($party)->create();

    expect($picture->fresh()->party_id)->toBe($party->id);
});

// ── Relationship: Party ─────────────────────────────────────────────────────

it('belongs to a party', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->forParty($party)->create();

    expect($picture->party)
        ->toBeInstanceOf(Party::class)
        ->id->toBe($party->id);
});

it('is deleted when its party is deleted (cascade)', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->forParty($party)->create();

    $party->delete();

    expect(Picture::find($picture->id))->toBeNull();
});

// ── Relationship: Party -> Pictures (hasMany) ───────────────────────────────

it('can be accessed from a party via pictures relationship', function () {
    $party = Party::factory()->create();
    Picture::factory()->forParty($party)->count(3)->create();

    expect($party->pictures)->toHaveCount(3)
        ->each->toBeInstanceOf(Picture::class);
});

it('returns an empty collection when party has no pictures', function () {
    $party = Party::factory()->create();

    expect($party->pictures)->toHaveCount(0);
});

// ── Unique code ──────────────────────────────────────────────────────────────

it('unique_code is null by default', function () {
    $picture = Picture::factory()->create();

    expect($picture->unique_code)->toBeNull();
});

it('stores a provided unique_code', function () {
    $picture = Picture::factory()->create(['unique_code' => 'BOOTH001']);

    expect($picture->fresh()->unique_code)->toBe('BOOTH001');
});

it('enforces unique_code uniqueness', function () {
    Picture::factory()->create(['unique_code' => 'DUPCODE1']);

    expect(fn () => Picture::factory()->create(['unique_code' => 'DUPCODE1']))
        ->toThrow(QueryException::class);
});

// ── URL generation ──────────────────────────────────────────────────────────

it('generates a url from the s3 disk', function () {
    Storage::fake('s3');

    $picture = Picture::factory()->create(['path' => 'pictures/test.jpg']);

    $url = $picture->url();

    expect($url)->toBeString()
        ->and($url)->toContain('pictures/test.jpg');
});

// ── Factory ─────────────────────────────────────────────────────────────────

it('factory creates the associated party automatically', function () {
    $picture = Picture::factory()->create();

    expect($picture->party)->toBeInstanceOf(Party::class);
});

it('factory forParty state assigns the given party', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->forParty($party)->create();

    expect($picture->party_id)->toBe($party->id);
});
