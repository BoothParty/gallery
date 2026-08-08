<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\OAuthUserResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class OAuthController extends Controller
{
    private const DENIED_MESSAGE = "Votre compte n'est pas autorisé à accéder à cette application.";

    public function __construct(private OAuthUserResolver $oAuthUserResolver) {}

    /**
     * Send the visitor to the given provider, defaulting to the configured one.
     */
    public function redirect(?string $provider = null): Response
    {
        $provider = $this->enabledProvider($provider ?? (string) config('auth.oauth.default'));

        return Inertia::location(Socialite::driver($provider)->redirect()->getTargetUrl());
    }

    /**
     * Log the visitor in from the account returned by the provider.
     */
    public function callback(Request $request, string $provider): RedirectResponse
    {
        $provider = $this->enabledProvider($provider);

        try {
            $providerUser = Socialite::driver($provider)->user();
        } catch (Throwable) {
            abort(403, self::DENIED_MESSAGE);
        }

        $user = $this->oAuthUserResolver->resolve($providerUser);

        abort_if($user === null, 403, self::DENIED_MESSAGE);

        Auth::login($user, remember: true);

        $request->session()->regenerate();

        return redirect()->intended(route('admin.parties.index'));
    }

    public function destroy(Request $request): Response
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Inertia::location('/');
    }

    private function enabledProvider(string $provider): string
    {
        /** @var list<string> $providers */
        $providers = config('auth.oauth.providers', []);

        abort_unless(in_array($provider, $providers, true), 404);

        return $provider;
    }
}
