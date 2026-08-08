<?php

use App\Models\User;
use App\Services\OAuthUserResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

function socialiteUser(?string $email, ?string $name = 'Jane Doe'): SocialiteUserContract
{
    $user = new SocialiteUser;

    return $user->map([
        'id' => '12345',
        'email' => $email,
        'name' => $name,
    ]);
}

beforeEach(function () {
    config(['auth.oauth.allowed_domains' => ['boothparty.fr']]);
});

it('creates a user when the email domain is allowed', function () {
    $user = app(OAuthUserResolver::class)->resolve(socialiteUser('jane@boothparty.fr'));

    expect($user)->not->toBeNull();
    expect($user->name)->toBe('Jane Doe');
    expect($user->email)->toBe('jane@boothparty.fr');
    expect($user->email_verified_at)->not->toBeNull();
    expect(User::query()->count())->toBe(1);
});

it('normalizes the email casing when creating a user', function () {
    $user = app(OAuthUserResolver::class)->resolve(socialiteUser('Jane@BoothParty.FR'));

    expect($user->email)->toBe('jane@boothparty.fr');
});

it('falls back to the email local part when the provider returns no name', function () {
    $user = app(OAuthUserResolver::class)->resolve(socialiteUser('jane@boothparty.fr', null));

    expect($user->name)->toBe('jane');
});

it('returns the existing user whatever its email domain', function () {
    $existing = User::factory()->create(['email' => 'guest@example.com']);

    $user = app(OAuthUserResolver::class)->resolve(socialiteUser('guest@example.com'));

    expect($user->id)->toBe($existing->id);
    expect(User::query()->count())->toBe(1);
});

it('matches an existing user regardless of the email casing', function () {
    $existing = User::factory()->create(['email' => 'jane@boothparty.fr']);

    $user = app(OAuthUserResolver::class)->resolve(socialiteUser('JANE@boothparty.fr'));

    expect($user->id)->toBe($existing->id);
    expect(User::query()->count())->toBe(1);
});

it('refuses an unknown user from a domain that is not allowed', function () {
    $user = app(OAuthUserResolver::class)->resolve(socialiteUser('intruder@example.com'));

    expect($user)->toBeNull();
    expect(User::query()->count())->toBe(0);
});

it('refuses a provider account without an email', function () {
    $user = app(OAuthUserResolver::class)->resolve(socialiteUser(null));

    expect($user)->toBeNull();
    expect(User::query()->count())->toBe(0);
});
