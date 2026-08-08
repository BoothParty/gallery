<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Str;
use Laravel\Socialite\Contracts\User as SocialiteUser;

class OAuthUserResolver
{
    /**
     * Resolve the application user allowed to sign in for the given provider
     * account, provisioning it when its email domain is allowed.
     */
    public function resolve(SocialiteUser $socialiteUser): ?User
    {
        $email = Str::lower(trim((string) $socialiteUser->getEmail()));

        if ($email === '') {
            return null;
        }

        $user = User::query()->where('email', $email)->first();

        if ($user !== null) {
            return $user;
        }

        if (! $this->isDomainAllowed($email)) {
            return null;
        }

        return User::query()->create([
            'name' => $this->nameFor($socialiteUser, $email),
            'email' => $email,
            'email_verified_at' => now(),
        ]);
    }

    private function isDomainAllowed(string $email): bool
    {
        /** @var list<string> $allowedDomains */
        $allowedDomains = config('auth.oauth.allowed_domains', []);

        $domain = Str::lower(Str::afterLast($email, '@'));

        foreach ($allowedDomains as $allowedDomain) {
            if (Str::lower(trim($allowedDomain)) === $domain) {
                return true;
            }
        }

        return false;
    }

    private function nameFor(SocialiteUser $socialiteUser, string $email): string
    {
        $name = trim((string) $socialiteUser->getName());

        return $name !== '' ? $name : Str::before($email, '@');
    }
}
