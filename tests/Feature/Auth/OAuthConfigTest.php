<?php

use Laravel\Socialite\Facades\Socialite;
use SocialiteProviders\Zoho\Provider as ZohoProvider;

it('registers the zoho socialite driver from the application configuration', function () {
    config([
        'services.zoho' => [
            'client_id' => 'test-client-id',
            'client_secret' => 'test-client-secret',
            'redirect' => 'https://gallery.test/auth/zoho/callback',
        ],
    ]);

    expect(Socialite::driver('zoho'))->toBeInstanceOf(ZohoProvider::class);
});

it('exposes the generic oauth configuration', function () {
    expect(config('auth.oauth.default'))->toBe('zoho');
    expect(config('auth.oauth.providers'))->toContain('zoho');
    expect(config('auth.oauth.allowed_domains'))->toContain('boothparty.fr');
});
