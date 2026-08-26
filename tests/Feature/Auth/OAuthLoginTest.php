<?php

use App\Models\Party;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\AbstractProvider;
use Laravel\Socialite\Two\User as SocialiteUser;

uses(RefreshDatabase::class);

function providerUser(?string $email, ?string $name = 'Jane Doe'): SocialiteUserContract
{
    $user = new SocialiteUser;

    return $user->map([
        'id' => '12345',
        'email' => $email,
        'name' => $name,
    ]);
}

function fakeProviderReturns(SocialiteUserContract $user): void
{
    $driver = Mockery::mock(AbstractProvider::class);
    $driver->shouldReceive('user')->andReturn($user);

    Socialite::shouldReceive('driver')->with('zoho')->andReturn($driver);
}

beforeEach(function () {
    config([
        'auth.oauth.allowed_domains' => ['boothparty.fr'],
        'services.zoho' => [
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
            'redirect' => 'https://gallery.test/auth/zoho/callback',
        ],
    ]);
});

it('redirects the login route to the default provider', function () {
    $response = $this->get('/login');

    $response->assertRedirectContains('accounts.zoho.com/oauth/v2/auth');
    $response->assertRedirectContains('client_id=test-client-id');
});

it('redirects the generic provider route to that provider', function () {
    $this->get('/auth/zoho/redirect')
        ->assertRedirectContains('accounts.zoho.com/oauth/v2/auth');
});

it('returns a not found response for a provider that is not enabled', function () {
    $this->get('/auth/github/redirect')->assertNotFound();
    $this->get('/auth/github/callback')->assertNotFound();
});

it('creates and logs in a user coming back from an allowed domain', function () {
    fakeProviderReturns(providerUser('jane@boothparty.fr'));

    $this->get('/auth/zoho/callback')
        ->assertRedirect(route('admin.parties.index'));

    $user = User::query()->where('email', 'jane@boothparty.fr')->first();

    expect($user)->not->toBeNull();
    $this->assertAuthenticatedAs($user);
});

it('logs in an existing user without duplicating it', function () {
    $existing = User::factory()->create(['email' => 'jane@boothparty.fr']);
    fakeProviderReturns(providerUser('jane@boothparty.fr'));

    $this->get('/auth/zoho/callback')->assertRedirect(route('admin.parties.index'));

    expect(User::query()->count())->toBe(1);
    $this->assertAuthenticatedAs($existing);
});

it('forbids an account from a domain that is not allowed', function () {
    fakeProviderReturns(providerUser('intruder@example.com'));

    $this->get('/auth/zoho/callback')
        ->assertForbidden()
        ->assertSee("Votre compte n'est pas autorisé à accéder à cette application.");

    expect(User::query()->count())->toBe(0);
    $this->assertGuest();
});

it('forbids an account without an email', function () {
    fakeProviderReturns(providerUser(null));

    $this->get('/auth/zoho/callback')->assertForbidden();

    $this->assertGuest();
});

it('forbids a failed exchange with the provider', function () {
    $driver = Mockery::mock(AbstractProvider::class);
    $driver->shouldReceive('user')->andThrow(new RuntimeException('invalid state'));

    Socialite::shouldReceive('driver')->with('zoho')->andReturn($driver);

    $this->get('/auth/zoho/callback')->assertForbidden();

    $this->assertGuest();
});

it('redirects an authenticated user to the page it originally asked for', function () {
    $party = Party::factory()->create();
    fakeProviderReturns(providerUser('jane@boothparty.fr'));

    $this->withSession(['url.intended' => route('party.dashboard.show', $party)])
        ->get('/auth/zoho/callback')
        ->assertRedirect(route('party.dashboard.show', $party));
});

it('logs the user out', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->post('/logout')
        ->assertRedirect('/');

    $this->assertGuest();
});
