<?php

use App\Models\Party;
use App\Models\User;

test('can create party with all options', function () {
    $owner = User::factory()->create();

    $this->artisan('party:create', [
        '--name' => 'Test Party',
        '--expiration' => '2026-12-31 23:59:59',
        '--owner' => $owner->id,
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('Party "Test Party" created successfully!');

    expect(Party::count())->toBe(1);

    $party = Party::first();
    expect($party->name)->toBe('Test Party');
    expect($party->expiration_date->format('Y-m-d H:i:s'))->toBe('2026-12-31 23:59:59');
    expect($party->owned_by)->toBe($owner->id);
});

test('can create party without owner', function () {
    $this->artisan('party:create', [
        '--name' => 'Solo Party',
        '--expiration' => '2026-06-15 18:00:00',
        '--no-interaction' => true,
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('Party "Solo Party" created successfully!');

    expect(Party::count())->toBe(1);

    $party = Party::first();
    expect($party->name)->toBe('Solo Party');
    expect($party->owned_by)->toBeNull();
});

test('can create party with owner by email', function () {
    $owner = User::factory()->create(['email' => 'party@example.com']);

    $this->artisan('party:create', [
        '--name' => 'Email Party',
        '--expiration' => '2026-03-20 12:00:00',
        '--owner' => 'party@example.com',
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('Party "Email Party" created successfully!');

    $party = Party::first();
    expect($party->owned_by)->toBe($owner->id);
});

test('displays error when owner not found', function () {
    $this->artisan('party:create', [
        '--name' => 'Test Party',
        '--expiration' => '2026-12-31 23:59:59',
        '--owner' => 999,
    ])
        ->assertFailed()
        ->expectsOutputToContain('Owner not found');

    expect(Party::count())->toBe(0);
});

test('displays error for invalid date format', function () {
    $this->artisan('party:create', [
        '--name' => 'Bad Date Party',
        '--expiration' => 'not-a-date',
    ])
        ->assertFailed()
        ->expectsOutputToContain('Invalid date format');

    expect(Party::count())->toBe(0);
});

test('displays party details after creation', function () {
    $owner = User::factory()->create(['name' => 'John Doe', 'email' => 'john@example.com']);

    $this->artisan('party:create', [
        '--name' => 'Details Party',
        '--expiration' => '2026-08-10 20:00:00',
        '--owner' => $owner->id,
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('Details Party')
        ->expectsOutputToContain('2026-08-10 20:00:00')
        ->expectsOutputToContain('John Doe (john@example.com)');
});
