<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

// ── Option --user (ID) ───────────────────────────────────────────────────────

it('generates a token when passing a user ID', function () {
    $user = User::factory()->create();

    $this->artisan('user:generate-token', [
        '--user' => $user->id,
        '--name' => 'my-token',
    ])
        ->expectsOutputToContain("Token generated successfully for {$user->name}")
        ->assertSuccessful();

    expect($user->tokens()->where('name', 'my-token')->exists())->toBeTrue();
});

// ── Option --user (email) ────────────────────────────────────────────────────

it('generates a token when passing a user email', function () {
    $user = User::factory()->create();

    $this->artisan('user:generate-token', [
        '--user' => $user->email,
        '--name' => 'email-token',
    ])
        ->expectsOutputToContain("Token generated successfully for {$user->name}")
        ->assertSuccessful();

    expect($user->tokens()->where('name', 'email-token')->exists())->toBeTrue();
});

// ── Token name prompt ────────────────────────────────────────────────────────

it('prompts for token name when --name is not provided', function () {
    $user = User::factory()->create();

    $this->artisan('user:generate-token', ['--user' => $user->email])
        ->expectsQuestion('Token name', 'prompted-token')
        ->expectsOutputToContain("Token generated successfully for {$user->name}")
        ->assertSuccessful();

    expect($user->tokens()->where('name', 'prompted-token')->exists())->toBeTrue();
});

// ── User not found ───────────────────────────────────────────────────────────

it('fails with an error when the user ID does not exist', function () {
    $this->artisan('user:generate-token', [
        '--user' => 99999,
        '--name' => 'any',
    ])
        ->expectsOutputToContain('User not found')
        ->assertFailed();
});

it('fails with an error when the user email does not exist', function () {
    $this->artisan('user:generate-token', [
        '--user' => 'nobody@example.com',
        '--name' => 'any',
    ])
        ->expectsOutputToContain('User not found')
        ->assertFailed();
});

// ── Interactive search ───────────────────────────────────────────────────────

it('finds a user via the interactive search prompt', function () {
    $user = User::factory()->create(['name' => 'Alice', 'email' => 'alice@example.com']);

    $this->artisan('user:generate-token', ['--name' => 'search-token'])
        ->expectsSearch(
            'Search for a user',
            search: 'Alice',
            answers: [$user->id => 'Alice (alice@example.com)'],
            answer: $user->id,
        )
        ->expectsOutputToContain("Token generated successfully for {$user->name}")
        ->assertSuccessful();

    expect($user->tokens()->where('name', 'search-token')->exists())->toBeTrue();
});

// ── Token is stored in DB ────────────────────────────────────────────────────

it('persists the generated token in the database', function () {
    $user = User::factory()->create();

    $this->artisan('user:generate-token', [
        '--user' => $user->id,
        '--name' => 'db-token',
    ])->assertSuccessful();

    $this->assertDatabaseHas('personal_access_tokens', [
        'tokenable_type' => User::class,
        'tokenable_id' => $user->id,
        'name' => 'db-token',
    ]);
});

// ── Warning message ──────────────────────────────────────────────────────────

it('shows a warning to copy the token', function () {
    $user = User::factory()->create();

    $this->artisan('user:generate-token', [
        '--user' => $user->id,
        '--name' => 'warn-token',
    ])
        ->expectsOutputToContain("You won't be able to see it again")
        ->assertSuccessful();
});
