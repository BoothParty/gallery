<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

describe('UserFactory', function () {
    test('creates user with default attributes', function () {
        $user = User::factory()->create();

        expect($user->name)->not->toBeNull();
        expect($user->email)->not->toBeNull();
        expect($user->email_verified_at)->not->toBeNull();
        expect($user->provider)->toBe('zoho');
        expect($user->provider_id)->not->toBeNull();
        expect($user->remember_token)->not->toBeNull();
    });

    test('creates unverified user', function () {
        $user = User::factory()->unverified()->create();

        expect($user->email_verified_at)->toBeNull();
    });

    test('creates user with boothparty email', function () {
        $user = User::factory()->boothparty()->create();

        expect($user->email)->toEndWith('@boothparty.fr');
    });

    test('creates user with specific provider', function () {
        $user = User::factory()->withProvider('google', 'google-123')->create();

        expect($user->provider)->toBe('google');
        expect($user->provider_id)->toBe('google-123');
    });

    test('creates user with specific provider and auto-generated provider_id', function () {
        $user = User::factory()->withProvider('github')->create();

        expect($user->provider)->toBe('github');
        expect($user->provider_id)->not->toBeNull();
    });

    test('creates multiple users with unique emails', function () {
        $users = User::factory()->count(3)->create();

        $emails = $users->pluck('email')->toArray();
        expect($emails)->toHaveCount(3);
        expect(count($emails))->toBe(count(array_unique($emails)));
    });

    test('creates multiple users with unique provider_ids', function () {
        $users = User::factory()->count(3)->create();

        $providerIds = $users->pluck('provider_id')->toArray();
        expect($providerIds)->toHaveCount(3);
        expect(count($providerIds))->toBe(count(array_unique($providerIds)));
    });
});
