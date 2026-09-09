<?php

use App\Models\Party;
use App\Models\User;

// ── Options complètes ────────────────────────────────────────────────────────

it('creates a party with all options provided', function () {
    $user = User::factory()->create();

    $this->artisan('party:create', [
        '--name' => 'retro-night',
        '--user' => $user->id,
        '--expires-at' => '2030-12-31 23:59:59',
    ])
        ->expectsOutputToContain('Party "retro-night" created successfully')
        ->assertSuccessful();

    $this->assertDatabaseHas('parties', [
        'name' => 'retro-night',
        'user_id' => $user->id,
    ]);
});

// ── Sans owner (sélection "— No owner —") ───────────────────────────────────

it('creates a party without an owner by selecting "No owner"', function () {
    $this->artisan('party:create', [
        '--name' => 'anonymous-party',
    ])
        ->expectsSearch(
            'Search for an owner',
            search: '',
            answers: [0 => '— No owner —'],
            answer: 0,
        )
        ->expectsQuestion('Expiration date', '')
        ->expectsOutputToContain('Party "anonymous-party" created successfully')
        ->assertSuccessful();

    $this->assertDatabaseHas('parties', [
        'name' => 'anonymous-party',
        'user_id' => null,
    ]);
});

// ── Owner par email ──────────────────────────────────────────────────────────

it('resolves owner by email via --user option', function () {
    $user = User::factory()->create();

    $this->artisan('party:create', [
        '--name' => 'email-party',
        '--user' => $user->email,
    ])
        ->expectsQuestion('Expiration date', '')
        ->expectsOutputToContain('Party "email-party" created successfully')
        ->assertSuccessful();

    $this->assertDatabaseHas('parties', [
        'name' => 'email-party',
        'user_id' => $user->id,
    ]);
});

// ── Prompt interactif pour le nom ────────────────────────────────────────────

it('prompts for party name when --name is not provided', function () {
    $user = User::factory()->create();

    $this->artisan('party:create', ['--user' => $user->id])
        ->expectsQuestion('Party name', 'prompted-party')
        ->expectsQuestion('Expiration date', '')
        ->expectsOutputToContain('Party "prompted-party" created successfully')
        ->assertSuccessful();

    $this->assertDatabaseHas('parties', ['name' => 'prompted-party']);
});

// ── Recherche interactive de l'owner ────────────────────────────────────────

it('finds the owner via the interactive search prompt', function () {
    $user = User::factory()->create(['name' => 'Bob', 'email' => 'bob@example.com']);

    $this->artisan('party:create', ['--name' => 'search-party'])
        ->expectsSearch(
            'Search for an owner',
            search: 'Bob',
            answers: [
                0 => '— No owner —',
                $user->id => 'Bob (bob@example.com)',
            ],
            answer: $user->id,
        )
        ->expectsQuestion('Expiration date', '')
        ->expectsOutputToContain('Party "search-party" created successfully')
        ->assertSuccessful();

    $this->assertDatabaseHas('parties', [
        'name' => 'search-party',
        'user_id' => $user->id,
    ]);
});

// ── User introuvable ─────────────────────────────────────────────────────────

it('fails with an error when the user ID does not exist', function () {
    $this->artisan('party:create', [
        '--name' => 'ghost-party',
        '--user' => 99999,
    ])
        ->expectsOutputToContain('User not found')
        ->assertFailed();

    expect(Party::where('name', 'ghost-party')->exists())->toBeFalse();
});

it('fails with an error when the user email does not exist', function () {
    $this->artisan('party:create', [
        '--name' => 'ghost-party',
        '--user' => 'nobody@example.com',
    ])
        ->expectsOutputToContain('User not found')
        ->assertFailed();
});

// ── Date d'expiration ────────────────────────────────────────────────────────

it('stores the expiration date when provided via option', function () {
    $this->artisan('party:create', [
        '--name' => 'timed-party',
        '--expires-at' => '2030-06-15 18:00:00',
    ])
        ->expectsSearch(
            'Search for an owner',
            search: '',
            answers: [0 => '— No owner —'],
            answer: 0,
        )
        ->assertSuccessful();

    $party = Party::where('name', 'timed-party')->first();

    expect($party->expire_at->toDateTimeString())->toBe('2030-06-15 18:00:00');
});

// ── Validation de la date d'expiration ──────────────────────────────────────

it('fails when --expires-at is not a valid date', function () {
    $this->artisan('party:create', [
        '--name' => 'bad-date-party',
        '--user' => User::factory()->create()->id,
        '--expires-at' => 'not-a-date',
    ])
        ->expectsOutputToContain('The expiration date is not a valid date.')
        ->assertFailed();

    expect(Party::where('name', 'bad-date-party')->exists())->toBeFalse();
});

it('fails when --expires-at is in the past', function () {
    $this->artisan('party:create', [
        '--name' => 'past-date-party',
        '--user' => User::factory()->create()->id,
        '--expires-at' => '2000-01-01 00:00:00',
    ])
        ->expectsOutputToContain('The expiration date must be in the future.')
        ->assertFailed();

    expect(Party::where('name', 'past-date-party')->exists())->toBeFalse();
});

it('accepts an empty expiration date interactively', function () {
    $user = User::factory()->create();

    $this->artisan('party:create', ['--name' => 'no-expiry-party', '--user' => $user->id])
        ->expectsQuestion('Expiration date', '')
        ->assertSuccessful();

    expect(Party::where('name', 'no-expiry-party')->value('expire_at'))->toBeNull();
});

it('re-prompts with an error when an invalid date is entered interactively', function () {
    $user = User::factory()->create();

    $this->artisan('party:create', ['--name' => 'retry-party', '--user' => $user->id])
        ->expectsQuestion('Expiration date', 'not-a-date')
        ->assertFailed();

    expect(Party::where('name', 'retry-party')->exists())->toBeFalse();
});
