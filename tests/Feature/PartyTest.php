<?php

use App\Models\Party;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

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
