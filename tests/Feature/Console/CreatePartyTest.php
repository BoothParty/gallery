<?php

use App\Models\Party;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Options complètes ────────────────────────────────────────────────────────

it('creates a party with all options provided', function () {
    $user = User::factory()->create();

    $this->artisan('party:create', [
        '--name' => 'Retro Night',
        '--user' => $user->id,
        '--expires-at' => '2030-12-31 23:59:59',
    ])
        ->expectsOutputToContain('Party "Retro Night" created successfully')
        ->assertSuccessful();

    $this->assertDatabaseHas('parties', [
        'name' => 'Retro Night',
        'user_id' => $user->id,
    ]);
});

// ── Sans owner (sélection "— No owner —") ───────────────────────────────────

it('creates a party without an owner by selecting "No owner"', function () {
    $this->artisan('party:create', [
        '--name' => 'Anonymous Party',
    ])
        ->expectsSearch(
            'Search for an owner',
            search: '',
            answers: [0 => '— No owner —'],
            answer: 0,
        )
        ->expectsQuestion('Expiration date', '')
        ->expectsOutputToContain('Party "Anonymous Party" created successfully')
        ->assertSuccessful();

    $this->assertDatabaseHas('parties', [
        'name' => 'Anonymous Party',
        'user_id' => null,
    ]);
});

// ── Owner par email ──────────────────────────────────────────────────────────

it('resolves owner by email via --user option', function () {
    $user = User::factory()->create();

    $this->artisan('party:create', [
        '--name' => 'Email Party',
        '--user' => $user->email,
    ])
        ->expectsQuestion('Expiration date', '')
        ->expectsOutputToContain('Party "Email Party" created successfully')
        ->assertSuccessful();

    $this->assertDatabaseHas('parties', [
        'name' => 'Email Party',
        'user_id' => $user->id,
    ]);
});

// ── Prompt interactif pour le nom ────────────────────────────────────────────

it('prompts for party name when --name is not provided', function () {
    $user = User::factory()->create();

    $this->artisan('party:create', ['--user' => $user->id])
        ->expectsQuestion('Party name', 'Prompted Party')
        ->expectsQuestion('Expiration date', '')
        ->expectsOutputToContain('Party "Prompted Party" created successfully')
        ->assertSuccessful();

    $this->assertDatabaseHas('parties', ['name' => 'Prompted Party']);
});

// ── Recherche interactive de l'owner ────────────────────────────────────────

it('finds the owner via the interactive search prompt', function () {
    $user = User::factory()->create(['name' => 'Bob', 'email' => 'bob@example.com']);

    $this->artisan('party:create', ['--name' => 'Search Party'])
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
        ->expectsOutputToContain('Party "Search Party" created successfully')
        ->assertSuccessful();

    $this->assertDatabaseHas('parties', [
        'name' => 'Search Party',
        'user_id' => $user->id,
    ]);
});

// ── User introuvable ─────────────────────────────────────────────────────────

it('fails with an error when the user ID does not exist', function () {
    $this->artisan('party:create', [
        '--name' => 'Ghost Party',
        '--user' => 99999,
    ])
        ->expectsOutputToContain('User not found')
        ->assertFailed();

    expect(Party::where('name', 'Ghost Party')->exists())->toBeFalse();
});

it('fails with an error when the user email does not exist', function () {
    $this->artisan('party:create', [
        '--name' => 'Ghost Party',
        '--user' => 'nobody@example.com',
    ])
        ->expectsOutputToContain('User not found')
        ->assertFailed();
});

// ── Date d'expiration ────────────────────────────────────────────────────────

it('stores the expiration date when provided via option', function () {
    $this->artisan('party:create', [
        '--name' => 'Timed Party',
        '--expires-at' => '2030-06-15 18:00:00',
    ])
        ->expectsSearch(
            'Search for an owner',
            search: '',
            answers: [0 => '— No owner —'],
            answer: 0,
        )
        ->assertSuccessful();

    $party = Party::where('name', 'Timed Party')->first();

    expect($party->expire_at->toDateTimeString())->toBe('2030-06-15 18:00:00');
});

// ── Validation de la date d'expiration ──────────────────────────────────────

it('fails when --expires-at is not a valid date', function () {
    $this->artisan('party:create', [
        '--name' => 'Bad Date Party',
        '--user' => User::factory()->create()->id,
        '--expires-at' => 'not-a-date',
    ])
        ->expectsOutputToContain('The expiration date is not a valid date.')
        ->assertFailed();

    expect(Party::where('name', 'Bad Date Party')->exists())->toBeFalse();
});

it('fails when --expires-at is in the past', function () {
    $this->artisan('party:create', [
        '--name' => 'Past Date Party',
        '--user' => User::factory()->create()->id,
        '--expires-at' => '2000-01-01 00:00:00',
    ])
        ->expectsOutputToContain('The expiration date must be in the future.')
        ->assertFailed();

    expect(Party::where('name', 'Past Date Party')->exists())->toBeFalse();
});

it('accepts an empty expiration date interactively', function () {
    $user = User::factory()->create();

    $this->artisan('party:create', ['--name' => 'No Expiry Party', '--user' => $user->id])
        ->expectsQuestion('Expiration date', '')
        ->assertSuccessful();

    expect(Party::where('name', 'No Expiry Party')->value('expire_at'))->toBeNull();
});

it('re-prompts with an error when an invalid date is entered interactively', function () {
    $user = User::factory()->create();

    $this->artisan('party:create', ['--name' => 'Retry Party', '--user' => $user->id])
        ->expectsQuestion('Expiration date', 'not-a-date')
        ->assertFailed();

    expect(Party::where('name', 'Retry Party')->exists())->toBeFalse();
});
