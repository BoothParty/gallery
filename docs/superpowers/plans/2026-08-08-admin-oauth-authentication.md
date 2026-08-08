# Authentification OAuth du panel admin — Plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Protéger `/admin/*` et `/{party}/dashboard/*` par une authentification OAuth (Zoho), supprimer toute authentification par mot de passe.

**Architecture:** Laravel Socialite avec le fournisseur communautaire Zoho, piloté par une configuration générique (`config/auth.php` → `oauth.default`, `oauth.providers`, `oauth.allowed_domains`). Un `OAuthController` mince délègue toute la décision d'accès à `App\Services\OAuthUserResolver`, qui connecte un utilisateur existant ou en crée un si le domaine de son email est autorisé. Il n'y a pas de page de login : `GET /login` redirige directement vers le fournisseur par défaut.

**Tech Stack:** Laravel 12, Inertia 2 + React 19, Pest 4 (+ plugin browser), MariaDB, Laravel Sail.

## Global Constraints

- Spec de référence : `docs/superpowers/specs/2026-08-08-admin-oauth-authentication-design.md`.
- **Toutes** les commandes passent par Sail : `vendor/bin/sail artisan …`, `vendor/bin/sail composer …`, `vendor/bin/sail npm …`.
- Après toute modification PHP : `vendor/bin/sail bin pint --dirty --format agent`.
- Lancer les tests avec `vendor/bin/sail artisan test --compact --filter=…` (le minimum nécessaire).
- Types de retour explicites sur toutes les méthodes, promotion de propriétés dans les constructeurs, accolades toujours présentes, PHPDoc plutôt que commentaires inline.
- Pas de `DB::` ni de requêtes brutes : `Model::query()`.
- `env()` uniquement dans `config/`.
- Message de refus, mot pour mot : `Votre compte n'est pas autorisé à accéder à cette application.`
- Domaine autorisé par défaut : `boothparty.fr`. Fournisseur par défaut : `zoho`.
- Ne jamais supprimer un test sauf mention explicite du plan (les deux suppressions prévues, tâche 5, ont été validées par l'utilisateur).

## File Structure

| Fichier | Responsabilité |
| --- | --- |
| `config/services.php` (modif) | Credentials Zoho |
| `config/auth.php` (modif) | Bloc `oauth` (défaut, whitelist, domaines) ; suppression du bloc `passwords` |
| `app/Providers/AppServiceProvider.php` (modif) | Enregistrement du driver Socialite Zoho |
| `app/Services/OAuthUserResolver.php` (création) | Décision d'accès + provisioning ; aucune dépendance HTTP |
| `app/Http/Controllers/Auth/OAuthController.php` (création) | Redirection, callback, déconnexion ; aucune logique métier |
| `routes/web.php` (modif) | Routes d'auth **en tête de fichier**, middleware `auth` sur admin et dashboard |
| `database/migrations/*_remove_password_from_users_table.php` (création) | Drop `users.password` + table `password_reset_tokens` |
| `app/Http/Middleware/HandleInertiaRequests.php` (modif) | Partage `auth.user` |
| `resources/js/components/nav-user.tsx` (modif) | Affiche l'utilisateur connecté, bouton de déconnexion |

⚠️ **Ordre des routes** : `routes/web.php` commence par `Route::prefix('{party}')`. Une route `/login` déclarée après serait avalée par ce groupe (`party = "login"`). Les routes d'authentification doivent être déclarées **avant** le groupe `{party}`.

---

### Task 1: Dépendances Socialite et configuration du fournisseur Zoho

**Files:**
- Modify: `composer.json` (via composer)
- Modify: `config/services.php`
- Modify: `config/auth.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `.env.example`
- Test: `tests/Feature/Auth/OAuthConfigTest.php`

**Interfaces:**
- Consumes: rien.
- Produces: `config('auth.oauth.default')` (string), `config('auth.oauth.providers')` (`list<string>`), `config('auth.oauth.allowed_domains')` (`list<string>`), `config('services.zoho.{client_id,client_secret,redirect}')`, et le driver Socialite `zoho`.

- [ ] **Step 1: Installer les dépendances**

```bash
vendor/bin/sail composer require laravel/socialite socialiteproviders/zoho
```

- [ ] **Step 2: Écrire le test qui échoue**

Créer `tests/Feature/Auth/OAuthConfigTest.php` :

```php
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
```

- [ ] **Step 3: Lancer le test pour le voir échouer**

Run: `vendor/bin/sail artisan test --compact --filter=OAuthConfigTest`
Expected: FAIL — driver `zoho` non supporté / `config('auth.oauth.default')` vaut `null`.

- [ ] **Step 4: Ajouter les credentials Zoho**

Dans `config/services.php`, après le bloc `slack` :

```php
    'zoho' => [
        'client_id' => env('ZOHO_CLIENT_ID'),
        'client_secret' => env('ZOHO_CLIENT_SECRET'),
        'redirect' => env('ZOHO_REDIRECT_URI'),
    ],
```

- [ ] **Step 5: Ajouter le bloc `oauth` à `config/auth.php`**

À ajouter à la fin du tableau retourné par `config/auth.php`, avant le `];` final :

```php
    /*
    |--------------------------------------------------------------------------
    | OAuth Authentication
    |--------------------------------------------------------------------------
    |
    | The administration panel is only reachable through an OAuth provider.
    | "providers" whitelists the drivers the authentication routes accept, and
    | "allowed_domains" lists the email domains allowed to be provisioned on
    | their first successful login.
    |
    */

    'oauth' => [
        'default' => env('OAUTH_DEFAULT_PROVIDER', 'zoho'),

        'providers' => ['zoho'],

        'allowed_domains' => array_values(array_filter(array_map(
            'trim',
            explode(',', (string) env('OAUTH_ALLOWED_DOMAINS', 'boothparty.fr')),
        ))),
    ],
```

- [ ] **Step 6: Enregistrer le driver Zoho**

Dans `app/Providers/AppServiceProvider.php`, méthode `boot()` (remplacer le `//`) :

```php
    public function boot(): void
    {
        Event::listen(function (SocialiteWasCalled $event): void {
            $event->extendSocialite('zoho', ZohoProvider::class);
        });
    }
```

Imports à ajouter en haut du fichier :

```php
use Illuminate\Support\Facades\Event;
use SocialiteProviders\Manager\SocialiteWasCalled;
use SocialiteProviders\Zoho\Provider as ZohoProvider;
```

- [ ] **Step 7: Documenter les variables d'environnement**

Dans `.env.example`, sous le bloc `NOTION_*` :

```
ZOHO_CLIENT_ID=
ZOHO_CLIENT_SECRET=
ZOHO_REDIRECT_URI="${APP_URL}/auth/zoho/callback"
OAUTH_ALLOWED_DOMAINS=boothparty.fr
```

- [ ] **Step 8: Lancer le test pour le voir passer**

Run: `vendor/bin/sail artisan test --compact --filter=OAuthConfigTest`
Expected: PASS (2 tests)

- [ ] **Step 9: Formater et committer**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add composer.json composer.lock config/services.php config/auth.php app/Providers/AppServiceProvider.php .env.example tests/Feature/Auth/OAuthConfigTest.php
git commit -m "feat(auth): configure le fournisseur OAuth Zoho via Socialite"
```

---

### Task 2: Service de résolution de l'utilisateur OAuth

**Files:**
- Create: `app/Services/OAuthUserResolver.php`
- Modify: `app/Models/User.php` (ajout de `email_verified_at` à `$fillable`)
- Test: `tests/Feature/Auth/OAuthUserResolverTest.php`

**Interfaces:**
- Consumes: `config('auth.oauth.allowed_domains')` de la tâche 1.
- Produces: `App\Services\OAuthUserResolver::resolve(\Laravel\Socialite\Contracts\User $socialiteUser): ?\App\Models\User` — retourne l'utilisateur à connecter, ou `null` si l'accès doit être refusé.

- [ ] **Step 1: Écrire les tests qui échouent**

Créer `tests/Feature/Auth/OAuthUserResolverTest.php` :

```php
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
```

- [ ] **Step 2: Lancer les tests pour les voir échouer**

Run: `vendor/bin/sail artisan test --compact --filter=OAuthUserResolverTest`
Expected: FAIL — `Class "App\Services\OAuthUserResolver" does not exist`.

- [ ] **Step 3: Écrire le service**

Créer `app/Services/OAuthUserResolver.php` :

```php
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
```

- [ ] **Step 4: Autoriser l'assignation de `email_verified_at`**

Dans `app/Models/User.php`, remplacer le tableau `$fillable` par :

```php
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
        'password',
    ];
```

(La ligne `password` disparaîtra en tâche 5.)

- [ ] **Step 5: Lancer les tests pour les voir passer**

Run: `vendor/bin/sail artisan test --compact --filter=OAuthUserResolverTest`
Expected: PASS (7 tests)

- [ ] **Step 6: Formater et committer**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add app/Services/OAuthUserResolver.php app/Models/User.php tests/Feature/Auth/OAuthUserResolverTest.php
git commit -m "feat(auth): resoud et provisionne l'utilisateur depuis le compte OAuth"
```

---

### Task 3: Routes et contrôleur d'authentification

**Files:**
- Create: `app/Http/Controllers/Auth/OAuthController.php`
- Modify: `routes/web.php` (en tête de fichier)
- Test: `tests/Feature/Auth/OAuthLoginTest.php`

**Interfaces:**
- Consumes: `OAuthUserResolver::resolve()` (tâche 2), la config `auth.oauth.*` et le driver `zoho` (tâche 1).
- Produces: routes nommées `login` (`GET /login`), `auth.redirect` (`GET /auth/{provider}/redirect`), `auth.callback` (`GET /auth/{provider}/callback`), `logout` (`POST /logout`).

- [ ] **Step 1: Écrire les tests qui échouent**

Créer `tests/Feature/Auth/OAuthLoginTest.php` :

```php
<?php

use App\Models\Party;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Socialite\Contracts\User as SocialiteUserContract;
use Laravel\Socialite\Facades\Socialite;
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
    $driver = Mockery::mock(Laravel\Socialite\Two\AbstractProvider::class);
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
        ->assertSee("Votre compte n'est pas autorisé à accéder à cette application.", escape: false);

    expect(User::query()->count())->toBe(0);
    $this->assertGuest();
});

it('forbids an account without an email', function () {
    fakeProviderReturns(providerUser(null));

    $this->get('/auth/zoho/callback')->assertForbidden();

    $this->assertGuest();
});

it('forbids a failed exchange with the provider', function () {
    $driver = Mockery::mock(Laravel\Socialite\Two\AbstractProvider::class);
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
        ->assertRedirect(route('login'));

    $this->assertGuest();
});
```

- [ ] **Step 2: Lancer les tests pour les voir échouer**

Run: `vendor/bin/sail artisan test --compact --filter=OAuthLoginTest`
Expected: FAIL — 404 sur `/login` (ou route `login` inconnue).

- [ ] **Step 3: Écrire le contrôleur**

Créer `app/Http/Controllers/Auth/OAuthController.php` :

```php
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

        return Inertia::location(route('login'));
    }

    private function enabledProvider(string $provider): string
    {
        /** @var list<string> $providers */
        $providers = config('auth.oauth.providers', []);

        abort_unless(in_array($provider, $providers, true), 404);

        return $provider;
    }
}
```

`Inertia::location()` renvoie une redirection 302 classique pour une requête normale, et un 409 `X-Inertia-Location` pour une requête Inertia — indispensable ici, car la cible est externe (Zoho) et une redirection suivie en XHR casserait le client Inertia.

- [ ] **Step 4: Déclarer les routes en tête de `routes/web.php`**

Juste après les `use` et **avant** `Route::prefix('{party}')` :

```php
Route::get('login', [OAuthController::class, 'redirect'])->name('login');
Route::get('auth/{provider}/redirect', [OAuthController::class, 'redirect'])->name('auth.redirect');
Route::get('auth/{provider}/callback', [OAuthController::class, 'callback'])->name('auth.callback');
Route::post('logout', [OAuthController::class, 'destroy'])->name('logout');
```

Import à ajouter : `use App\Http\Controllers\Auth\OAuthController;`

- [ ] **Step 5: Lancer les tests pour les voir passer**

Run: `vendor/bin/sail artisan test --compact --filter=OAuthLoginTest`
Expected: PASS (10 tests)

- [ ] **Step 6: Vérifier qu'aucune route existante n'a été masquée**

Run: `vendor/bin/sail artisan test --compact --filter="GalleryTest|PartyTest"`
Expected: PASS — la route `/{party}` continue de répondre.

- [ ] **Step 7: Formater et committer**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add app/Http/Controllers/Auth/OAuthController.php routes/web.php tests/Feature/Auth/OAuthLoginTest.php
git commit -m "feat(auth): ajoute les routes de connexion et deconnexion OAuth"
```

---

### Task 4: Protection des routes admin et dashboard

**Files:**
- Modify: `routes/web.php`
- Test: `tests/Feature/Auth/AdminAccessTest.php` (création)
- Modify: `tests/Feature/AdminPartiesTest.php`, `tests/Feature/AdminPartiesBulkCreateTest.php`, `tests/Feature/AdminPartyPosterTest.php`, `tests/Feature/AdminGalleryTest.php`, `tests/Feature/AdminUsersTest.php`, `tests/Feature/DashboardTest.php`
- Modify: `tests/Web/AdminGalleryTest.php`, `tests/Web/AdminPartiesTest.php`, `tests/Web/DashboardTest.php`

**Interfaces:**
- Consumes: la route nommée `login` (tâche 3).
- Produces: rien de nouveau ; les groupes `party.dashboard.*` et `admin.*` exigent désormais une session authentifiée.

- [ ] **Step 1: Écrire le test qui échoue**

Créer `tests/Feature/Auth/AdminAccessTest.php` :

```php
<?php

use App\Models\Party;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects a guest away from the administration', function (string $url) {
    $this->get($url)->assertRedirect(route('login'));
})->with([
    '/admin/parties',
    '/admin/users',
    '/admin/users/create',
]);

it('redirects a guest away from the party dashboard', function () {
    $party = Party::factory()->create();

    $this->get(route('party.dashboard.show', $party))
        ->assertRedirect(route('login'));

    $this->get(route('party.dashboard.pictures', $party))
        ->assertRedirect(route('login'));
});

it('keeps the public party gallery open to guests', function () {
    $party = Party::factory()->create();

    $this->get(route('party.gallery', $party))->assertOk();
});

it('lets an authenticated user reach the administration', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin/parties')
        ->assertOk();
});
```

- [ ] **Step 2: Lancer le test pour le voir échouer**

Run: `vendor/bin/sail artisan test --compact --filter=AdminAccessTest`
Expected: FAIL — les invités reçoivent 200 au lieu d'une redirection.

- [ ] **Step 3: Ajouter le middleware `auth`**

Dans `routes/web.php`, le groupe dashboard devient :

```php
    Route::prefix('dashboard')->name('dashboard.')->middleware('auth')->group(function () {
```

et le groupe admin :

```php
Route::prefix('admin')->name('admin.')->middleware('auth')->group(function () {
```

- [ ] **Step 4: Lancer le test pour le voir passer**

Run: `vendor/bin/sail artisan test --compact --filter=AdminAccessTest`
Expected: PASS

- [ ] **Step 5: Authentifier les suites de tests existantes**

Ajouter, juste après le `uses(RefreshDatabase::class);` de chacun des fichiers suivants :

```php
beforeEach(function () {
    $this->actingAs(User::factory()->create());
});
```

avec l'import `use App\Models\User;` s'il est absent. Fichiers concernés :
`tests/Feature/AdminPartiesTest.php`, `tests/Feature/AdminPartiesBulkCreateTest.php`,
`tests/Feature/AdminPartyPosterTest.php`, `tests/Feature/AdminGalleryTest.php`,
`tests/Feature/AdminUsersTest.php`, `tests/Feature/DashboardTest.php`,
`tests/Web/AdminGalleryTest.php`, `tests/Web/AdminPartiesTest.php`, `tests/Web/DashboardTest.php`.

- [ ] **Step 6: Corriger le comptage d'utilisateurs de `AdminUsersTest`**

L'utilisateur connecté ajoute une ligne à la table users. Remplacer le premier test de `tests/Feature/AdminUsersTest.php` par :

```php
it('renders administration users index with datatable data', function () {
    $user = User::factory()->create(['name' => 'Alice']);
    Party::factory()->count(2)->create(['user_id' => $user->id]);

    $this->get('/admin/users')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/users/index')
            ->has('tableData.columns')
            ->has('tableData.rows', 2)
            ->where('tableData.rows', fn ($rows) => collect($rows)
                ->contains(fn (array $row) => $row['name'] === 'Alice' && $row['parties_count'] === 2)
            )
        );
});
```

- [ ] **Step 7: Lancer les suites Feature**

Run: `vendor/bin/sail artisan test --compact --testsuite=Feature`
Expected: PASS

- [ ] **Step 8: Lancer les suites navigateur**

Run: `vendor/bin/sail artisan test --compact --testsuite=Web`
Expected: PASS.

Ces tests sont connus pour flanquer sur des timeouts de 5 s : relancer une fois avant d'enquêter. Si l'authentification n'est **pas** prise en compte dans le navigateur (redirection vers `/login` observée), c'est que `actingAs` n'est pas propagé à la session du serveur de test : dans ce cas, remplacer le `beforeEach` par une connexion réelle en session avant `visit()` et signaler le point avant d'aller plus loin.

- [ ] **Step 9: Formater et committer**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add routes/web.php tests
git commit -m "feat(auth): protege l'administration et le dashboard par le middleware auth"
```

---

### Task 5: Suppression de l'authentification par mot de passe

**Files:**
- Create: `database/migrations/*_remove_password_from_users_table.php`
- Modify: `app/Models/User.php`, `database/factories/UserFactory.php`
- Modify: `app/Http/Requests/StoreUserRequest.php`, `app/Http/Requests/UpdateUserRequest.php`
- Modify: `app/Http/Controllers/Admin/UserController.php`
- Modify: `config/auth.php`
- Modify: `resources/js/pages/admin/users/create.tsx`, `edit.tsx`, `index.tsx`
- Modify: `tests/Feature/AdminUsersTest.php`, `tests/Feature/Api/PartyApiTest.php`

**Interfaces:**
- Consumes: rien.
- Produces: le modèle `User` n'expose plus d'attribut `password` ; `UserFactory` ne le renseigne plus.

- [ ] **Step 1: Adapter les tests (ils doivent échouer)**

Dans `tests/Feature/AdminUsersTest.php` :
- retirer `'password' => 'password'` et `'password_confirmation' => 'password'` du test `stores a new user…` ;
- retirer `'password' => ''` et `'password_confirmation' => ''` du test `updates an existing user…` ;
- **supprimer** le test `keeps existing password when updating a user with empty password` (suppression validée par l'utilisateur : le comportement testé n'existe plus) ;
- ajouter ce test :

```php
it('stores a user without any password column', function () {
    Party::factory()->create();

    $this->post('/admin/users', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ])->assertRedirect('/admin/users');

    $user = User::query()->where('email', 'jane@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->getAttributes())->not->toHaveKey('password');
});
```

Dans `tests/Feature/Api/PartyApiTest.php`, la ligne `->assertJsonMissingPath('data.password')` reste valable telle quelle : ne pas y toucher.

- [ ] **Step 2: Lancer les tests pour les voir échouer**

Run: `vendor/bin/sail artisan test --compact --filter=AdminUsersTest`
Expected: FAIL — `The password field is required.` et la colonne `password` toujours présente.

- [ ] **Step 3: Créer la migration**

```bash
vendor/bin/sail artisan make:migration remove_password_from_users_table --no-interaction
```

Contenu :

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('password');
        });

        Schema::dropIfExists('password_reset_tokens');
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('password')->nullable()->after('email_verified_at');
        });

        Schema::create('password_reset_tokens', function (Blueprint $table) {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }
};
```

- [ ] **Step 4: Nettoyer le modèle et la factory**

`app/Models/User.php` :

```php
    protected $fillable = [
        'name',
        'email',
        'email_verified_at',
    ];

    protected $hidden = [
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
        ];
    }
```

`database/factories/UserFactory.php` : supprimer la propriété statique `$password`, la ligne `'password' => …` de `definition()`, et les imports `Illuminate\Support\Facades\Hash` devenu inutile (garder `Str` pour `remember_token`).

- [ ] **Step 5: Nettoyer les Form Requests et le contrôleur**

`StoreUserRequest` : supprimer la règle `'password' => [...]`, les messages `password.required` / `password.confirmed`, et l'import `Illuminate\Validation\Rules\Password`.

`UpdateUserRequest` : supprimer la méthode `prepareForValidation()`, la règle `'password' => [...]`, le message `password.confirmed`, et l'import `Password`.

`Admin\UserController::update()` devient :

```php
    public function update(UpdateUserRequest $request, User $user)
    {
        $user->update($request->validated());

        return to_route('admin.users.index');
    }
```

- [ ] **Step 6: Nettoyer `config/auth.php`**

Supprimer la clé `'passwords' => env('AUTH_PASSWORD_BROKER', 'users'),` du bloc `defaults`, ainsi que tout le bloc `'passwords' => [...]` (et son commentaire d'en-tête) et le bloc `'password_timeout'`.

- [ ] **Step 7: Nettoyer les pages React**

Dans `resources/js/pages/admin/users/create.tsx` et `edit.tsx` : retirer `password` et `password_confirmation` de l'appel `useForm`, et supprimer les deux blocs `<div className="space-y-2">` correspondant aux champs mot de passe.

Dans `resources/js/pages/admin/users/index.tsx` : retirer `password` / `password_confirmation` des trois initialisations de formulaire (lignes ~47, ~54, ~87), remplacer `editForm.reset("password", "password_confirmation")` par `editForm.reset()`, et supprimer les quatre blocs de champs mot de passe des dialogues de création et d'édition.

- [ ] **Step 8: Migrer et lancer les tests**

```bash
vendor/bin/sail artisan migrate --no-interaction
vendor/bin/sail artisan test --compact --testsuite=Feature
```
Expected: PASS

- [ ] **Step 9: Vérifier le build front**

Run: `vendor/bin/sail npm run build`
Expected: build réussi, sans erreur TypeScript.

- [ ] **Step 10: Formater et committer**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add app config database resources/js/pages/admin/users tests
git commit -m "feat(auth): supprime l'authentification par mot de passe"
```

---

### Task 6: Utilisateur connecté et déconnexion dans l'interface

**Files:**
- Modify: `app/Http/Middleware/HandleInertiaRequests.php`
- Modify: `resources/js/components/nav-user.tsx`
- Test: `tests/Feature/Auth/SharedAuthPropsTest.php` (création)
- Modify: `tests/Web/AdminPartiesTest.php` (ajout d'un test de déconnexion)

**Interfaces:**
- Consumes: la route `logout` (tâche 3).
- Produces: la prop Inertia partagée `auth.user` = `{id: number, name: string, email: string} | null`.

- [ ] **Step 1: Écrire le test qui échoue**

Créer `tests/Feature/Auth/SharedAuthPropsTest.php` :

```php
<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('shares the authenticated user with every inertia page', function () {
    $user = User::factory()->create(['name' => 'Alice', 'email' => 'alice@boothparty.fr']);

    $this->actingAs($user)
        ->get('/admin/parties')
        ->assertInertia(fn ($page) => $page
            ->where('auth.user.id', $user->id)
            ->where('auth.user.name', 'Alice')
            ->where('auth.user.email', 'alice@boothparty.fr')
        );
});

it('shares a null user with guests', function () {
    $party = App\Models\Party::factory()->create();

    $this->get(route('party.gallery', $party))
        ->assertInertia(fn ($page) => $page->where('auth.user', null));
});
```

- [ ] **Step 2: Lancer le test pour le voir échouer**

Run: `vendor/bin/sail artisan test --compact --filter=SharedAuthPropsTest`
Expected: FAIL — la prop `auth` n'existe pas.

- [ ] **Step 3: Partager l'utilisateur authentifié**

Dans `app/Http/Middleware/HandleInertiaRequests::share()`, remplacer le `//` par :

```php
            'auth' => [
                'user' => fn (): ?array => $request->user() === null ? null : [
                    'id' => $request->user()->id,
                    'name' => $request->user()->name,
                    'email' => $request->user()->email,
                ],
            ],
```

- [ ] **Step 4: Lancer le test pour le voir passer**

Run: `vendor/bin/sail artisan test --compact --filter=SharedAuthPropsTest`
Expected: PASS

- [ ] **Step 5: Câbler `NavUser` sur l'utilisateur réel**

Remplacer `resources/js/components/nav-user.tsx` par :

```tsx
import { router, usePage } from "@inertiajs/react"

import {
  Avatar,
  AvatarFallback,
} from "@/components/ui/avatar"
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import {
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  useSidebar,
} from "@/components/ui/sidebar"
import { ChevronsUpDownIcon, LogOutIcon } from "lucide-react"

type AuthenticatedUser = {
  id: number
  name: string
  email: string
}

function initials(name: string): string {
  return name
    .split(" ")
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? "")
    .join("")
}

export function NavUser() {
  const { isMobile } = useSidebar()
  const user = usePage<{ auth: { user: AuthenticatedUser | null } }>().props.auth.user

  if (!user) {
    return null
  }

  return (
    <SidebarMenu>
      <SidebarMenuItem>
        <DropdownMenu>
          <DropdownMenuTrigger asChild>
            <SidebarMenuButton
              size="lg"
              className="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
            >
              <Avatar className="h-8 w-8 rounded-lg">
                <AvatarFallback className="rounded-lg">{initials(user.name)}</AvatarFallback>
              </Avatar>
              <div className="grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-medium">{user.name}</span>
                <span className="truncate text-xs">{user.email}</span>
              </div>
              <ChevronsUpDownIcon className="ml-auto size-4" />
            </SidebarMenuButton>
          </DropdownMenuTrigger>
          <DropdownMenuContent
            className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg"
            side={isMobile ? "bottom" : "right"}
            align="end"
            sideOffset={4}
          >
            <DropdownMenuLabel className="p-0 font-normal">
              <div className="flex items-center gap-2 px-1 py-1.5 text-left text-sm">
                <Avatar className="h-8 w-8 rounded-lg">
                  <AvatarFallback className="rounded-lg">{initials(user.name)}</AvatarFallback>
                </Avatar>
                <div className="grid flex-1 text-left text-sm leading-tight">
                  <span className="truncate font-medium">{user.name}</span>
                  <span className="truncate text-xs">{user.email}</span>
                </div>
              </div>
            </DropdownMenuLabel>
            <DropdownMenuSeparator />
            <DropdownMenuItem onSelect={() => router.post("/logout")}>
              <LogOutIcon />
              Log out
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </SidebarMenuItem>
    </SidebarMenu>
  )
}
```

Dans `resources/js/components/app-sidebar.tsx`, remplacer
`<NavUser user={{ name: "Admin", email: "admin@example.com", avatar: "" }} />`
par `<NavUser />`.

- [ ] **Step 6: Ajouter un test navigateur de déconnexion**

À la fin de `tests/Web/AdminPartiesTest.php` :

```php
it('signs the user out from the sidebar menu', function () {
    $page = visit(route('admin.parties.index'));

    $page->click('Log out')
        ->assertNoJavaScriptErrors();

    $this->assertGuest();
});
```

Si le menu utilisateur est replié, ouvrir d'abord le déclencheur avec le nom de l'utilisateur connecté avant de cliquer sur `Log out`.

- [ ] **Step 7: Construire le front et lancer les tests**

```bash
vendor/bin/sail npm run build
vendor/bin/sail artisan test --compact
```
Expected: PASS sur les trois suites (relancer une fois la suite Web en cas de timeout).

- [ ] **Step 8: Formater et committer**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add app/Http/Middleware/HandleInertiaRequests.php resources/js/components tests
git commit -m "feat(auth): affiche l'utilisateur connecte et permet la deconnexion"
```

---

## Vérification finale

- [ ] `vendor/bin/sail artisan test --compact` — les trois suites passent.
- [ ] `vendor/bin/sail bin pint --dirty --format agent` — aucun fichier à corriger.
- [ ] `vendor/bin/sail npm run build` — build réussi.
- [ ] Vérification manuelle avec de vrais credentials Zoho : renseigner `ZOHO_CLIENT_ID`, `ZOHO_CLIENT_SECRET`, `ZOHO_REDIRECT_URI` dans `.env`, déclarer l'URL de callback dans la console Zoho API, puis visiter `/admin/parties` en navigation privée. Si la redirection échoue parce que le compte est hébergé hors `.com` (`accounts.zoho.eu`), appliquer la réserve documentée dans la spec : étendre `SocialiteProviders\Zoho\Provider` dans `app/Services/Socialite/` pour surcharger l'URL d'autorisation depuis la configuration.
