<?php

use App\Models\User;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

beforeEach(function () {
    Config::set('auth.socialite.providers', ['zoho']);
    Config::set('auth.socialite.allowed_domains', ['boothparty.fr']);
});

describe('redirectToProvider', function () {
    test('redirects to zoho oauth provider', function () {
        Socialite::shouldReceive('driver')
            ->once()
            ->with('zoho')
            ->andReturnSelf();

        Socialite::shouldReceive('redirect')
            ->once()
            ->andReturn(redirect('https://accounts.zoho.eu/oauth/v2/auth'));

        $response = $this->get(route('auth.redirect', ['provider' => 'zoho']));

        $response->assertRedirect();
    });

    test('returns 404 for unsupported provider', function () {
        $response = $this->get('/auth/github/redirect');

        $response->assertNotFound();
    });

    test('handles exception during redirect gracefully', function () {
        Log::shouldReceive('error')->once();

        Socialite::shouldReceive('driver')
            ->once()
            ->with('zoho')
            ->andThrow(new \Exception('OAuth configuration error'));

        $response = $this->get(route('auth.redirect', ['provider' => 'zoho']));

        $response->assertStatus(500);
    });
});

describe('handleProviderCallback', function () {
    test('authenticates user with valid zoho credentials', function () {
        $socialiteUser = Mockery::mock(SocialiteUserContract::class);
        $socialiteUser->shouldReceive('getId')->andReturn('zoho-user-123');
        $socialiteUser->shouldReceive('getEmail')->andReturn('john.doe@boothparty.fr');
        $socialiteUser->shouldReceive('getName')->andReturn('John Doe');
        $socialiteUser->shouldReceive('getNickname')->andReturn('johndoe');

        Socialite::shouldReceive('driver')
            ->once()
            ->with('zoho')
            ->andReturnSelf();

        Socialite::shouldReceive('stateless')
            ->once()
            ->andReturnSelf();

        Socialite::shouldReceive('user')
            ->once()
            ->andReturn($socialiteUser);

        Log::shouldReceive('info')->once();

        $response = $this->get(route('auth.callback', ['provider' => 'zoho']));

        $response->assertRedirect(route('home'));
        $this->assertAuthenticated();

        // Fetch the user from database directly
        $user = User::query()
            ->where('provider', 'zoho')
            ->where('provider_id', 'zoho-user-123')
            ->first();

        expect($user)->not->toBeNull();
        expect($user->name)->toBe('John Doe');
        expect($user->email)->toBe('john.doe@boothparty.fr');
        expect($user->provider)->toBe('zoho');
        expect($user->provider_id)->toBe('zoho-user-123');
        expect($user->email_verified_at)->not->toBeNull();
    });

    test('updates existing user on subsequent login', function () {
        $existingUser = User::factory()->create([
            'email' => 'jane.smith@boothparty.fr',
            'name' => 'Old Name',
            'provider' => 'zoho',
            'provider_id' => 'zoho-user-456',
        ]);

        $socialiteUser = Mockery::mock(SocialiteUserContract::class);
        $socialiteUser->shouldReceive('getId')->andReturn('zoho-user-456');
        $socialiteUser->shouldReceive('getEmail')->andReturn('jane.smith@boothparty.fr');
        $socialiteUser->shouldReceive('getName')->andReturn('Jane Smith Updated');
        $socialiteUser->shouldReceive('getNickname')->andReturn(null);

        Socialite::shouldReceive('driver')
            ->once()
            ->with('zoho')
            ->andReturnSelf();

        Socialite::shouldReceive('stateless')
            ->once()
            ->andReturnSelf();

        Socialite::shouldReceive('user')
            ->once()
            ->andReturn($socialiteUser);

        Log::shouldReceive('info')->once();

        $response = $this->get(route('auth.callback', ['provider' => 'zoho']));

        $response->assertRedirect(route('home'));
        $this->assertAuthenticated();

        $updatedUser = $existingUser->fresh();
        expect($updatedUser->name)->toBe('Jane Smith Updated');
        expect(User::count())->toBe(1); // No duplicate created
    });

    test('rejects unauthorized email domain', function () {
        $socialiteUser = Mockery::mock(SocialiteUserContract::class);
        $socialiteUser->shouldReceive('getId')->andReturn('zoho-user-789');
        $socialiteUser->shouldReceive('getEmail')->andReturn('unauthorized@example.com');
        $socialiteUser->shouldReceive('getName')->andReturn('Unauthorized User');

        Socialite::shouldReceive('driver')
            ->once()
            ->with('zoho')
            ->andReturnSelf();

        Socialite::shouldReceive('stateless')
            ->once()
            ->andReturnSelf();

        Socialite::shouldReceive('user')
            ->once()
            ->andReturn($socialiteUser);

        Log::shouldReceive('warning')->once();

        $response = $this->get(route('auth.callback', ['provider' => 'zoho']));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['error']);
        $this->assertGuest();

        expect(User::query()->where('email', 'unauthorized@example.com')->exists())->toBeFalse();
    });

    test('handles invalid state exception', function () {
        Socialite::shouldReceive('driver')
            ->once()
            ->with('zoho')
            ->andReturnSelf();

        Socialite::shouldReceive('stateless')
            ->once()
            ->andReturnSelf();

        Socialite::shouldReceive('user')
            ->once()
            ->andThrow(new InvalidStateException);

        Log::shouldReceive('warning')->once();

        $response = $this->get(route('auth.callback', ['provider' => 'zoho']));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['error']);
        $this->assertGuest();
    });

    test('handles general oauth exception', function () {
        Socialite::shouldReceive('driver')
            ->once()
            ->with('zoho')
            ->andReturnSelf();

        Socialite::shouldReceive('stateless')
            ->once()
            ->andReturnSelf();

        Socialite::shouldReceive('user')
            ->once()
            ->andThrow(new \Exception('OAuth API Error'));

        Log::shouldReceive('error')->once();

        $response = $this->get(route('auth.callback', ['provider' => 'zoho']));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['error']);
        $this->assertGuest();
    });

    test('rejects user without email from provider', function () {
        $socialiteUser = Mockery::mock(SocialiteUserContract::class);
        $socialiteUser->shouldReceive('getId')->andReturn('zoho-user-999');
        $socialiteUser->shouldReceive('getEmail')->andReturn(null);
        $socialiteUser->shouldReceive('getName')->andReturn('No Email User');

        Socialite::shouldReceive('driver')
            ->once()
            ->with('zoho')
            ->andReturnSelf();

        Socialite::shouldReceive('stateless')
            ->once()
            ->andReturnSelf();

        Socialite::shouldReceive('user')
            ->once()
            ->andReturn($socialiteUser);

        Log::shouldReceive('warning')->once();

        $response = $this->get(route('auth.callback', ['provider' => 'zoho']));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['error']);
        $this->assertGuest();
    });

    test('rejects user without provider id', function () {
        $socialiteUser = Mockery::mock(SocialiteUserContract::class);
        $socialiteUser->shouldReceive('getId')->andReturn(null);
        $socialiteUser->shouldReceive('getEmail')->andReturn('test@boothparty.fr');
        $socialiteUser->shouldReceive('getName')->andReturn('No ID User');

        Socialite::shouldReceive('driver')
            ->once()
            ->with('zoho')
            ->andReturnSelf();

        Socialite::shouldReceive('stateless')
            ->once()
            ->andReturnSelf();

        Socialite::shouldReceive('user')
            ->once()
            ->andReturn($socialiteUser);

        Log::shouldReceive('warning')->once();

        $response = $this->get(route('auth.callback', ['provider' => 'zoho']));

        $response->assertRedirect(route('login'));
        $response->assertSessionHasErrors(['error']);
        $this->assertGuest();
    });

    test('handles user without name gracefully', function () {
        $socialiteUser = Mockery::mock(SocialiteUserContract::class);
        $socialiteUser->shouldReceive('getId')->andReturn('zoho-user-111');
        $socialiteUser->shouldReceive('getEmail')->andReturn('noname@boothparty.fr');
        $socialiteUser->shouldReceive('getName')->andReturn(null);
        $socialiteUser->shouldReceive('getNickname')->andReturn(null);

        Socialite::shouldReceive('driver')
            ->once()
            ->with('zoho')
            ->andReturnSelf();

        Socialite::shouldReceive('stateless')
            ->once()
            ->andReturnSelf();

        Socialite::shouldReceive('user')
            ->once()
            ->andReturn($socialiteUser);

        Log::shouldReceive('info')->once();

        $response = $this->get(route('auth.callback', ['provider' => 'zoho']));

        $response->assertRedirect(route('home'));
        $this->assertAuthenticated();

        $user = User::query()->where('email', 'noname@boothparty.fr')->first();
        expect($user->name)->toBe('Unknown User');
    });

    test('uses nickname when name is not available', function () {
        $socialiteUser = Mockery::mock(SocialiteUserContract::class);
        $socialiteUser->shouldReceive('getId')->andReturn('zoho-user-222');
        $socialiteUser->shouldReceive('getEmail')->andReturn('nickname@boothparty.fr');
        $socialiteUser->shouldReceive('getName')->andReturn(null);
        $socialiteUser->shouldReceive('getNickname')->andReturn('nicknameuser');

        Socialite::shouldReceive('driver')
            ->once()
            ->with('zoho')
            ->andReturnSelf();

        Socialite::shouldReceive('stateless')
            ->once()
            ->andReturnSelf();

        Socialite::shouldReceive('user')
            ->once()
            ->andReturn($socialiteUser);

        Log::shouldReceive('info')->once();

        $response = $this->get(route('auth.callback', ['provider' => 'zoho']));

        $response->assertRedirect(route('home'));
        $this->assertAuthenticated();

        $user = User::query()->where('email', 'nickname@boothparty.fr')->first();
        expect($user->name)->toBe('nicknameuser');
    });

    test('allows multiple authorized domains', function () {
        Config::set('auth.socialite.allowed_domains', ['boothparty.fr', 'partner.com']);

        $socialiteUser = Mockery::mock(SocialiteUserContract::class);
        $socialiteUser->shouldReceive('getId')->andReturn('zoho-user-333');
        $socialiteUser->shouldReceive('getEmail')->andReturn('user@partner.com');
        $socialiteUser->shouldReceive('getName')->andReturn('Partner User');
        $socialiteUser->shouldReceive('getNickname')->andReturn(null);

        Socialite::shouldReceive('driver')
            ->once()
            ->with('zoho')
            ->andReturnSelf();

        Socialite::shouldReceive('stateless')
            ->once()
            ->andReturnSelf();

        Socialite::shouldReceive('user')
            ->once()
            ->andReturn($socialiteUser);

        Log::shouldReceive('info')->once();

        $response = $this->get(route('auth.callback', ['provider' => 'zoho']));

        $response->assertRedirect(route('home'));
        $this->assertAuthenticated();

        $user = User::query()->where('email', 'user@partner.com')->first();
        expect($user)->not->toBeNull();
    });

    test('email domain check is case insensitive', function () {
        $socialiteUser = Mockery::mock(SocialiteUserContract::class);
        $socialiteUser->shouldReceive('getId')->andReturn('zoho-user-444');
        $socialiteUser->shouldReceive('getEmail')->andReturn('User@BoothParty.FR');
        $socialiteUser->shouldReceive('getName')->andReturn('Case Test User');
        $socialiteUser->shouldReceive('getNickname')->andReturn(null);

        Socialite::shouldReceive('driver')
            ->once()
            ->with('zoho')
            ->andReturnSelf();

        Socialite::shouldReceive('stateless')
            ->once()
            ->andReturnSelf();

        Socialite::shouldReceive('user')
            ->once()
            ->andReturn($socialiteUser);

        Log::shouldReceive('info')->once();

        $response = $this->get(route('auth.callback', ['provider' => 'zoho']));

        $response->assertRedirect(route('home'));
        $this->assertAuthenticated();
    });

    test('redirects to intended url after authentication', function () {
        session(['url.intended' => route('home')]);

        $socialiteUser = Mockery::mock(SocialiteUserContract::class);
        $socialiteUser->shouldReceive('getId')->andReturn('zoho-user-555');
        $socialiteUser->shouldReceive('getEmail')->andReturn('intended@boothparty.fr');
        $socialiteUser->shouldReceive('getName')->andReturn('Intended User');
        $socialiteUser->shouldReceive('getNickname')->andReturn(null);

        Socialite::shouldReceive('driver')
            ->once()
            ->with('zoho')
            ->andReturnSelf();

        Socialite::shouldReceive('stateless')
            ->once()
            ->andReturnSelf();

        Socialite::shouldReceive('user')
            ->once()
            ->andReturn($socialiteUser);

        Log::shouldReceive('info')->once();

        $response = $this->get(route('auth.callback', ['provider' => 'zoho']));

        $response->assertRedirect(route('home'));
    });
});

describe('provider constraint', function () {
    test('enforces unique constraint on provider and provider_id combination', function () {
        User::factory()->create([
            'email' => 'first@boothparty.fr',
            'provider' => 'zoho',
            'provider_id' => 'unique-id-123',
        ]);

        // Attempting to create another user with same provider+provider_id should fail
        expect(fn () => User::factory()->create([
            'email' => 'second@boothparty.fr',
            'provider' => 'zoho',
            'provider_id' => 'unique-id-123',
        ]))->toThrow(\Illuminate\Database\QueryException::class);
    });
});
