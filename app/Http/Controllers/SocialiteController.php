<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Laravel\Socialite\Facades\Socialite;
use Laravel\Socialite\Two\InvalidStateException;

class SocialiteController extends Controller
{
    /**
     * Redirect the user to the OAuth provider authentication page.
     */
    public function redirectToProvider(Request $request, string $provider): \Illuminate\Http\RedirectResponse
    {
        $this->validateProvider($provider);

        try {
            return Socialite::driver($provider)->redirect();
        } catch (\Exception $e) {
            Log::error('OAuth redirect failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            abort(500, 'Unable to initiate authentication. Please try again later.');
        }
    }

    /**
     * Handle the OAuth provider callback and authenticate the user.
     */
    public function handleProviderCallback(Request $request, string $provider): \Illuminate\Http\RedirectResponse
    {
        $this->validateProvider($provider);

        try {
            $socialiteUser = Socialite::driver($provider)->stateless()->user();
        } catch (InvalidStateException $e) {
            Log::warning('OAuth state mismatch', [
                'provider' => $provider,
                'ip' => $request->ip(),
            ]);

            return redirect()->route('login')
                ->withErrors(['error' => 'Authentication session expired. Please try again.']);
        } catch (\Exception $e) {
            Log::error('OAuth callback failed', [
                'provider' => $provider,
                'error' => $e->getMessage(),
                'ip' => $request->ip(),
            ]);

            return redirect()->route('login')
                ->withErrors(['error' => 'Authentication failed. Please try again.']);
        }

        // Validate user data from provider
        if (! $socialiteUser->getEmail() || ! $socialiteUser->getId()) {
            Log::warning('OAuth provider returned incomplete user data', [
                'provider' => $provider,
                'has_email' => (bool) $socialiteUser->getEmail(),
                'has_id' => (bool) $socialiteUser->getId(),
            ]);

            return redirect()->route('login')
                ->withErrors(['error' => 'Unable to retrieve user information from provider.']);
        }

        // Validate email domain
        if (! $this->isAllowedEmailDomain($socialiteUser->getEmail())) {
            Log::warning('Unauthorized email domain attempted login', [
                'email' => $socialiteUser->getEmail(),
                'provider' => $provider,
                'ip' => $request->ip(),
            ]);

            return redirect()->route('login')
                ->withErrors(['error' => 'Your email domain is not authorized to access this application.']);
        }

        try {
            $user = User::query()->updateOrCreate(
                [
                    'provider' => $provider,
                    'provider_id' => $socialiteUser->getId(),
                ],
                [
                    'name' => $socialiteUser->getName() ?? $socialiteUser->getNickname() ?? 'Unknown User',
                    'email' => $socialiteUser->getEmail(),
                    'email_verified_at' => now(),
                ]
            );

            Auth::login($user, remember: true);

            Log::info('User authenticated via OAuth', [
                'user_id' => $user->id,
                'provider' => $provider,
            ]);

            return redirect()->intended(route('home'));
        } catch (\Exception $e) {
            Log::error('Failed to create or authenticate user', [
                'provider' => $provider,
                'email' => $socialiteUser->getEmail(),
                'error' => $e->getMessage(),
            ]);

            return redirect()->route('login')
                ->withErrors(['error' => 'Unable to complete authentication. Please contact support.']);
        }
    }

    /**
     * Validate that the provider is supported.
     */
    protected function validateProvider(string $provider): void
    {
        $allowedProviders = config('auth.socialite.providers', ['zoho']);

        if (! in_array($provider, $allowedProviders, true)) {
            abort(404);
        }
    }

    /**
     * Check if the email domain is allowed.
     */
    protected function isAllowedEmailDomain(string $email): bool
    {
        $allowedDomains = config('auth.socialite.allowed_domains', ['boothparty.fr']);

        foreach ($allowedDomains as $domain) {
            if (str_ends_with(strtolower($email), '@'.strtolower($domain))) {
                return true;
            }
        }

        return false;
    }
}
