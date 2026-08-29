<?php

use App\Models\Party;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

// ── Creation & persistence ──────────────────────────────────────────────────

it('can be created with only a name', function () {
    $party = Party::factory()->create(['name' => 'My Party']);

    expect($party->exists)->toBeTrue()
        ->and($party->name)->toBe('My Party')
        ->and($party->user_id)->toBeNull()
        ->and($party->expire_at)->toBeNull();
});

it('persists all fillable attributes', function () {
    $user = User::factory()->create();
    $expiresAt = now()->addWeek()->startOfMinute();

    $party = Party::factory()->create([
        'name' => 'Booth Night',
        'user_id' => $user->id,
        'expire_at' => $expiresAt,
    ]);

    expect($party->fresh())
        ->name->toBe('Booth Night')
        ->user_id->toBe($user->id)
        ->expire_at->toEqual($expiresAt);
});

// ── Casts ───────────────────────────────────────────────────────────────────

it('casts expire_at as a Carbon instance', function () {
    $party = Party::factory()->expiresAt(now()->addDays(3))->create();

    expect($party->expire_at)->toBeInstanceOf(Carbon::class);
});

it('leaves expire_at null when not set', function () {
    $party = Party::factory()->create();

    expect($party->expire_at)->toBeNull();
});

// ── Relationship: User ──────────────────────────────────────────────────────

it('belongs to a user', function () {
    $user = User::factory()->create();
    $party = Party::factory()->withOwner()->create(['user_id' => $user->id]);

    expect($party->user)
        ->toBeInstanceOf(User::class)
        ->id->toBe($user->id);
});

it('can exist without an owner (nullable user)', function () {
    $party = Party::factory()->create(['user_id' => null]);

    expect($party->user)->toBeNull();
});

it('sets user_id to null when the owner is deleted (nullOnDelete)', function () {
    $user = User::factory()->create();
    $party = Party::factory()->create(['user_id' => $user->id]);

    $user->delete();

    expect($party->fresh()->user_id)->toBeNull();
});

// ── Factory states ──────────────────────────────────────────────────────────

it('factory withOwner state creates a user relationship', function () {
    $party = Party::factory()->withOwner()->create();

    expect($party->user)->toBeInstanceOf(User::class);
});

it('factory expired state sets expire_at in the past', function () {
    $party = Party::factory()->expired()->create();

    expect($party->expire_at)->not->toBeNull()
        ->and($party->expire_at->isPast())->toBeTrue();
});

it('factory expiresAt state sets the given date', function () {
    $date = now()->addMonth()->startOfDay();
    $party = Party::factory()->expiresAt($date)->create();

    expect($party->expire_at->toDateString())->toBe($date->toDateString());
});

// ── hidePictures ─────────────────────────────────────────────────────────────

it('hides pictures when expire_at is in the past', function () {
    $party = Party::factory()->expired()->create();

    expect($party->hidePictures())->toBeTrue();
});

it('does not hide pictures when expire_at is in the future', function () {
    $party = Party::factory()->expiresAt(now()->addDay())->create();

    expect($party->hidePictures())->toBeFalse();
});

it('does not hide pictures when expire_at is null', function () {
    $party = Party::factory()->create(['expire_at' => null]);

    expect($party->hidePictures())->toBeFalse();
});

// ── Event metadata ───────────────────────────────────────────────────────────

it('persists the display name and description', function () {
    $party = Party::factory()->create([
        'display_name' => 'Camille & Antoine',
        'description' => 'Samedi 12 septembre · Domaine des Ormes',
    ]);

    expect($party->fresh())
        ->display_name->toBe('Camille & Antoine')
        ->description->toBe('Samedi 12 septembre · Domaine des Ormes');
});

it('displays the display name as its title', function () {
    $party = Party::factory()->create([
        'name' => 'booth-night',
        'display_name' => 'Booth Night',
    ]);

    expect($party->displayTitle())->toBe('Booth Night');
});

it('falls back to the name when no display name is set', function () {
    $party = Party::factory()->create([
        'name' => 'booth-night',
        'display_name' => null,
    ]);

    expect($party->displayTitle())->toBe('booth-night');
});

it('falls back to the name when the display name is an empty string', function () {
    $party = Party::factory()->create([
        'name' => 'booth-night',
        'display_name' => '',
    ]);

    expect($party->displayTitle())->toBe('booth-night');
});

it('has no banner url without a banner', function () {
    $party = Party::factory()->create();

    expect($party->bannerUrl())->toBeNull();
});

it('exposes the banner url when a banner is stored', function () {
    Storage::fake('s3');

    $party = Party::factory()->create();
    $party->forceFill(['banner_path' => 'banners/1/cover.jpg'])->save();

    expect($party->bannerUrl())->toBe(Storage::disk('s3')->url('banners/1/cover.jpg'));
});

it('ignores a banner path passed through mass assignment', function () {
    $party = Party::factory()->create();

    $party->update(['banner_path' => 'banners/forged.jpg']);

    expect($party->fresh()->banner_path)->toBeNull();
});

it('deletes the banner file when the party is deleted', function () {
    Storage::fake('s3');
    Storage::disk('s3')->put('banners/1/cover.jpg', 'binary');

    $party = Party::factory()->create();
    $party->forceFill(['banner_path' => 'banners/1/cover.jpg'])->save();

    $party->delete();

    Storage::disk('s3')->assertMissing('banners/1/cover.jpg');
});

it('factory withBanner state stores a banner path', function () {
    $party = Party::factory()->withBanner()->create();

    expect($party->banner_path)->not->toBeNull();
});

// ── Host edit link ──────────────────────────────────────────────────────────

it('generates a host token when a party is created', function () {
    $party = Party::factory()->create();

    expect($party->host_token)->toBeString()->toHaveLength(48);
});

it('gives each party its own host token', function () {
    $first = Party::factory()->create();
    $second = Party::factory()->create();

    expect($first->host_token)->not->toBe($second->host_token);
});

it('keeps a host token that was set explicitly', function () {
    $party = Party::factory()->create(['host_token' => 'a-token-we-chose']);

    expect($party->host_token)->toBe('a-token-we-chose');
});

it('composes the host edit url from the public url and the token', function () {
    $party = Party::factory()->create(['name' => 'noces']);

    expect($party->hostEditUrl())
        ->toBe('https://noces.'.config('party.domain').'/?token='.$party->host_token);
});
