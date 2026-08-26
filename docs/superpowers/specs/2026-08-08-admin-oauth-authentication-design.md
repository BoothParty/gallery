# Authentification OAuth du panel admin

Date : 2026-08-08

## Contexte

Les routes `/admin/*` et `/{party}/dashboard/*` ne sont protégées par aucun
middleware : n'importe qui peut aujourd'hui créer une soirée, uploader ou
supprimer des photos. La table `users` porte un mot de passe, exposé par le CRUD
admin, mais aucun écran de connexion ne l'utilise.

## Objectif

Protéger l'administration par une authentification OAuth uniquement. Aucune
authentification par mot de passe ne subsiste. Le premier — et pour l'instant
unique — fournisseur est Zoho Mail, mais le mécanisme reste générique : ajouter
un second fournisseur doit se limiter à une dépendance et une entrée de
configuration.

## Périmètre

Protégés par le middleware `auth` :

- le groupe de routes `admin.*` ;
- le groupe `{party}/dashboard/*`.

Hors périmètre : la galerie publique `{party}/`, la galerie privée `/gallery`
(accès par code en session) et l'API `/api/*` (tokens Sanctum, inchangée).

Tout utilisateur authentifié accède à tous les dashboards : pas de policy par
propriétaire.

## Flux d'authentification

| Route | Nom | Rôle |
| --- | --- | --- |
| `GET /login` | `login` | Redirige vers le fournisseur par défaut |
| `GET /auth/{provider}/redirect` | `auth.redirect` | Redirection Socialite, `abort(404)` si le fournisseur n'est pas whitelisté |
| `GET /auth/{provider}/callback` | `auth.callback` | Retour du fournisseur, connexion |
| `POST /logout` | `logout` | Invalide la session et régénère le token |

Il n'y a pas de page de login. `GET /login` est le raccourci vers
`config('auth.oauth.default')` ; c'est la cible par défaut de la redirection des
invités par Laravel.

Après connexion réussie : redirection vers `admin.parties.index` (ou l'URL
initialement demandée, via `intended`). Après déconnexion : redirection vers
`login`.

## Provisioning

`App\Services\OAuthUserResolver::resolve(SocialiteUser $user): ?User`

1. Pas d'email retourné par le fournisseur → `null`.
2. Un `User` existe déjà avec cet email (comparaison insensible à la casse) →
   il est retourné tel quel, quel que soit son domaine.
3. Sinon, si le domaine de l'email appartient à
   `config('auth.oauth.allowed_domains')` → création d'un `User`
   (`name`, `email`, `email_verified_at` = maintenant).
4. Sinon → `null`.

Un `null` fait répondre au contrôleur `abort(403)` avec le message « Votre
compte n'est pas autorisé à accéder à cette application. ». Aucun utilisateur
n'est créé dans ce cas. Une exception Socialite pendant le callback (code
invalide, state expiré) donne le même 403.

Le contrôleur reste mince : toute la logique de décision vit dans le service.

## Suppression du mot de passe

- Migration : colonne `users.password` supprimée, table
  `password_reset_tokens` supprimée. Le `down()` les recrée.
- `users.remember_token` est conservée : ce n'est pas un mot de passe, elle est
  manipulée par le `SessionGuard` de Laravel et permettra des sessions
  « se souvenir de moi » sans nouvelle migration.
- `App\Models\User` : `password` retiré de `$fillable`, `$hidden` et de
  `casts()`.
- `UserFactory` : plus de `password`.
- `StoreUserRequest` / `UpdateUserRequest` : règles `password` supprimées.
- `Admin\UserController::update` : suppression du `unset` conditionnel.
- `config/auth.php` : bloc `passwords` et `defaults.passwords` supprimés.
- `resources/js/pages/admin/users/create.tsx` et `edit.tsx` : champs mot de
  passe supprimés.

Le CRUD users reste en place : il sert à pré-créer un compte hors domaine
autorisé, ou à révoquer un accès en supprimant l'utilisateur.

## Configuration

```php
// config/services.php
'zoho' => [
    'client_id' => env('ZOHO_CLIENT_ID'),
    'client_secret' => env('ZOHO_CLIENT_SECRET'),
    'redirect' => env('ZOHO_REDIRECT_URI'),
],
```

```php
// config/auth.php
'oauth' => [
    'default' => env('OAUTH_DEFAULT_PROVIDER', 'zoho'),
    'providers' => ['zoho'],
    'allowed_domains' => array_filter(explode(',', (string) env('OAUTH_ALLOWED_DOMAINS', 'boothparty.fr'))),
],
```

`providers` est la whitelist utilisée par les routes `auth.redirect` et
`auth.callback`. Ajouter un fournisseur : `composer require socialiteproviders/x`,
son event listener, une entrée dans `services.php` et une dans `providers`.

Variables ajoutées à `.env.example` : `ZOHO_CLIENT_ID`, `ZOHO_CLIENT_SECRET`,
`ZOHO_REDIRECT_URI`, `OAUTH_ALLOWED_DOMAINS`.

## Dépendances

`laravel/socialite` et `socialiteproviders/zoho` (validées par l'utilisateur).
Le provider Zoho est enregistré via l'event `SocialiteWasCalled` dans
`AppServiceProvider`.

Réserve connue : `socialiteproviders/zoho` code en dur `accounts.zoho.com` pour
l'URL d'autorisation et ne suit la région (`.eu`, `.in`) que sur les appels
token/userinfo, via le paramètre `accounts-server` renvoyé dans le callback. Si
le compte Zoho de l'organisation est hébergé hors `.com` et que la redirection
échoue en conditions réelles, on étendra la classe `Provider` dans
`app/Services/Socialite/` pour surcharger cette URL depuis la configuration.

## Front

- `HandleInertiaRequests::share` expose `auth.user` (`id`, `name`, `email`),
  `null` pour un invité.
- Le layout admin affiche l'utilisateur connecté et un lien « Se déconnecter »
  (`POST /logout`).
- Aucune page Inertia n'est ajoutée.

## Tests

`tests/Feature/Auth/OAuthLoginTest.php`, Socialite mocké :

- `GET /login` redirige vers le fournisseur par défaut ;
- `GET /auth/inconnu/redirect` et `/auth/inconnu/callback` → 404 ;
- callback avec un email d'un domaine autorisé : utilisateur créé, connecté,
  redirigé vers l'admin ;
- callback avec un utilisateur existant : connecté, aucun doublon créé ;
- callback avec un domaine non autorisé : 403, aucun utilisateur créé, invité ;
- callback sans email : 403 ;
- exception Socialite pendant le callback : 403 ;
- `POST /logout` déconnecte et redirige vers `login`.

`tests/Feature/Auth/AdminAccessTest.php` : un invité sur `/admin/parties`,
`/admin/users` et `/{party}/dashboard` est redirigé vers `login` ; un
utilisateur authentifié obtient 200.

Suites existantes à mettre à jour avec `actingAs` : `AdminPartiesTest`,
`AdminPartiesBulkCreateTest`, `AdminPartyPosterTest`, `AdminGalleryTest`,
`AdminUsersTest`, `DashboardTest`, et les tests navigateur `tests/Web/`
(`AdminGalleryTest`, `AdminPartiesTest`, `DashboardTest`). `AdminUsersTest`
perd également ses assertions sur le mot de passe.
