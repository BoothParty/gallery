<?php

use App\Models\User;

test('can generate token for user by id', function () {
    $user = User::factory()->create();

    $this->artisan('user:generate-token', [
        '--user' => $user->id,
        '--name' => 'Test Token',
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('Token generated successfully');

    expect($user->tokens()->count())->toBe(1);
    expect($user->tokens()->first()->name)->toBe('Test Token');
});

test('can generate token for user by email', function () {
    $user = User::factory()->create(['email' => 'test@example.com']);

    $this->artisan('user:generate-token', [
        '--user' => 'test@example.com',
        '--name' => 'Email Token',
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('Token generated successfully');

    expect($user->tokens()->count())->toBe(1);
    expect($user->tokens()->first()->name)->toBe('Email Token');
});

test('displays error when user not found', function () {
    $this->artisan('user:generate-token', [
        '--user' => 999,
        '--name' => 'Test Token',
    ])
        ->assertFailed()
        ->expectsOutputToContain('User not found');
});

test('displays token after generation', function () {
    $user = User::factory()->create();

    $this->artisan('user:generate-token', [
        '--user' => $user->id,
        '--name' => 'API Token',
    ])
        ->assertSuccessful()
        ->expectsOutputToContain('Token')
        ->expectsOutputToContain('Make sure to copy this token now');
});
