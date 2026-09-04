# Partage mobile des affiches — plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Après une création de parties en lot, l'admin affiche un QR code qui ouvre sur le téléphone une page d'où toutes les affiches partent en une fois dans la feuille de partage native.

**Architecture:** Deux routes publiques signées — une page Inertia et le PNG d'une affiche — dont les URLs sont fabriquées par un unique service, `PosterBatchLink`, qui signe la page et ses fichiers avec la même expiration et produit le QR code. Côté admin, une route JSON mine ce lien pour une sélection de parties, appelée aussi bien au retour de la création en lot que depuis la barre d'outils du tableau. Les affiches restent générées à la volée par `PartyPosterGenerator`, sans stockage.

**Tech Stack:** Laravel 12, Inertia v2, React 19, Tailwind CSS 4, `endroid/qr-code`, Pest 4 (tests navigateur Playwright).

**Spec :** `docs/superpowers/specs/2026-09-05-partage-affiches-mobile-design.md`

## Global Constraints

- Toutes les commandes passent par Sail : `vendor/bin/sail artisan …`, `vendor/bin/sail npm …`, `vendor/bin/sail bin pint …`.
- PHP : accolades systématiques même sur une ligne, types de retour explicites, promotion de propriétés dans les constructeurs, PHPDoc plutôt que commentaires en ligne.
- Après toute modification PHP : `vendor/bin/sail bin pint --dirty --format agent`.
- Après toute modification front, reconstruire avant de lancer les tests navigateur : `vendor/bin/sail npm run build`.
- Les messages de validation et les commentaires de code sont en anglais ; les textes affichés sur la page téléphone sont en français, comme le reste de la galerie publique.
- Deux styles front coexistent, il faut suivre celui du fichier voisin : l'admin (`components/parties`, `pages/admin`, `components/data-table`) écrit en guillemets doubles sans point-virgule ; le public (`pages/party`, `components/party`, `hooks`) écrit en guillemets simples avec point-virgule. Indentation de 4 espaces des deux côtés.
- La page publique **doit** s'appeler `party/posters` : `resources/views/app.blade.php` ne sert le bundle CSS public qu'aux composants Inertia préfixés `party/`, et `resources/css/app.css` ne scanne que `pages/party`, `components/party` et `hooks`. Ailleurs, la page sort sans aucun style.
- Aucune nouvelle dépendance : `endroid/qr-code` est déjà installé et utilisé par `PartyPosterGenerator`.
- `tests/Unit` ne charge pas l'application Laravel — `tests/Pest.php` n'étend `Tests\TestCase` que dans `Feature` et `Web`. Tout test qui touche aux routes, aux URLs signées ou à la base va dans `tests/Feature`.
- Les tests de `tests/Web/` de cette branche subissent encore les timeouts intermittents du plugin navigateur ; le correctif (`browserPage(fn () => visit(...))`) vit sur la PR #32. Tant qu'elle n'est pas fusionnée ici, écrire `visit(...)` comme le reste de la branche et relancer un test navigateur qui échoue avant d'enquêter. Après fusion, convertir les nouveaux tests à `browserPage`.

## File Structure

| Fichier | Responsabilité |
| --- | --- |
| `app/Http/Controllers/PosterController.php` (créé) | Sert la page publique du lot et le PNG d'une affiche |
| `app/Services/PosterBatchLink.php` (créé) | Fabrique les URLs signées d'un lot et le QR code de sa page |
| `app/Http/Controllers/Admin/PosterLinkController.php` (créé) | Renvoie en JSON le lien et le QR code d'une sélection |
| `app/Http/Requests/PosterLinkRequest.php` (créé) | Valide la sélection reçue par la route d'admin |
| `app/Http/Controllers/Admin/PartyController.php` (modifié) | `bulkStore` dépose les identifiants créés en session, `index` les passe en prop |
| `routes/web.php` (modifié) | Déclare les deux routes publiques et la route d'admin |
| `resources/js/components/data-table/data-table-main.tsx` (modifié) | Remonte la sélection de lignes au parent |
| `resources/js/components/data-table.tsx` (modifié) | Passe la nouvelle prop au tableau |
| `resources/js/components/parties/posters-dialog.tsx` (créé) | Boîte de dialogue : récupère le lien, affiche le QR code et les erreurs |
| `resources/js/pages/admin/parties/index.tsx` (modifié) | Ouvre la boîte après création et depuis la barre d'outils |
| `resources/js/hooks/use-poster-share.ts` (créé) | Téléchargement des affiches, appel à `navigator.share`, états |
| `resources/js/pages/party/posters.tsx` (créé) | Page téléphone : liste, bouton de partage, repli |
| `tests/Feature/PosterFileTest.php` (créé) | Couvre la route de fichier |
| `tests/Feature/PosterBatchPageTest.php` (créé) | Couvre la page publique et `PosterBatchLink::posters()` |
| `tests/Feature/AdminPosterLinkTest.php` (créé) | Couvre la route d'admin et `PosterBatchLink::forParties()` |
| `tests/Feature/AdminPartiesBulkCreateTest.php` (modifié) | Couvre les identifiants créés |
| `tests/Web/PostersTest.php` (créé) | Couvre la page téléphone au navigateur |
| `tests/Web/AdminPartiesTest.php` (modifié) | Couvre les deux entrées de la boîte de dialogue |

---

### Task 1 : La route de fichier signée

**Files:**
- Create: `app/Http/Controllers/PosterController.php`
- Create: `tests/Feature/PosterFileTest.php`
- Modify: `routes/web.php`

**Interfaces:**
- Consumes: `App\Services\PartyPosterGenerator::generate(Party $party): string`, `App\Models\Party`.
- Produces: la route nommée `posters.file`, d'URL `/affiches/{party}/png` liée par identifiant, protégée par `signed` et `throttle:30,1`. Elle répond 200 avec les octets PNG et l'en-tête `Content-Disposition: inline; filename="<slug>-affiche.png"`, 403 sans signature valide, 404 si la party n'existe plus.

- [ ] **Step 1: Écrire les tests de la route**

Créer `tests/Feature/PosterFileTest.php` :

```php
<?php

use App\Models\Party;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

/**
 * The signed URL a batch page would hand to the phone.
 */
function signedPosterUrl(int $partyId, ?DateTimeInterface $expiresAt = null): string
{
    return URL::temporarySignedRoute(
        'posters.file',
        $expiresAt ?? now()->addHours(6),
        ['party' => $partyId],
    );
}

it('serves the poster of a party to whoever holds a signed link', function () {
    $party = Party::factory()->create(['name' => 'my-party']);

    $response = $this->get(signedPosterUrl($party->id));

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Content-Disposition', 'inline; filename="my-party-affiche.png"');

    [$width, $height] = getimagesizefromstring($response->getContent());

    expect([$width, $height])->toBe([4000, 2828]);
});

it('refuses an unsigned link', function () {
    $party = Party::factory()->create();

    $this->get('/affiches/'.$party->id.'/png')->assertForbidden();
});

it('refuses a link tampered with to point at another party', function () {
    $party = Party::factory()->create();
    $other = Party::factory()->create();

    $url = str_replace(
        '/affiches/'.$party->id.'/',
        '/affiches/'.$other->id.'/',
        signedPosterUrl($party->id),
    );

    $this->get($url)->assertForbidden();
});

it('refuses an expired link', function () {
    $party = Party::factory()->create();

    $url = signedPosterUrl($party->id, now()->addHours(6));

    $this->travel(7)->hours();

    $this->get($url)->assertForbidden();
});

it('answers not found for a party deleted since the link was minted', function () {
    $party = Party::factory()->create();

    $url = signedPosterUrl($party->id);

    $party->delete();

    $this->get($url)->assertNotFound();
});

/**
 * A poster costs almost a second of CPU and 54 MB, so the limiter is asserted
 * on the route rather than by hammering it.
 */
it('rate limits the poster route', function () {
    expect(app('router')->getRoutes()->getByName('posters.file')->gatherMiddleware())
        ->toContain('throttle:30,1');
});
```

- [ ] **Step 2: Lancer les tests pour les voir échouer**

Run: `vendor/bin/sail artisan test --compact --filter=PosterFileTest`
Expected: FAIL — `Route [posters.file] not defined.`

- [ ] **Step 3: Écrire le contrôleur**

Créer `app/Http/Controllers/PosterController.php` :

```php
<?php

namespace App\Http\Controllers;

use App\Models\Party;
use App\Services\PartyPosterGenerator;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Str;

class PosterController extends Controller
{
    /**
     * Stream the poster of a party to whoever holds a signed link.
     *
     * The disposition is inline so that the page can fetch the bytes for the
     * share sheet, and so that opening the link shows the poster.
     */
    public function file(Party $party, PartyPosterGenerator $partyPosterGenerator): HttpResponse
    {
        $filename = Str::slug($party->name).'-affiche.png';

        return response($partyPosterGenerator->generate($party), 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
```

- [ ] **Step 4: Déclarer la route**

Dans `routes/web.php`, ajouter l'import :

```php
use App\Http\Controllers\PosterController;
```

puis déclarer la route **avant** le groupe `Route::prefix('{party}')`, qui capture tout segment unique :

```php
Route::get('affiches/{party:id}/png', [PosterController::class, 'file'])
    ->middleware(['signed', 'throttle:30,1'])
    ->name('posters.file');
```

- [ ] **Step 5: Lancer les tests pour les voir passer**

Run: `vendor/bin/sail artisan test --compact --filter=PosterFileTest`
Expected: PASS — 6 tests.

- [ ] **Step 6: Formater et committer**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add app/Http/Controllers/PosterController.php routes/web.php tests/Feature/PosterFileTest.php
git commit -m "feat(posters): serve a party poster from a signed link"
```

---

### Task 2 : La page publique du lot

**Files:**
- Create: `app/Services/PosterBatchLink.php`
- Create: `tests/Feature/PosterBatchPageTest.php`
- Modify: `app/Http/Controllers/PosterController.php`
- Modify: `routes/web.php`

**Interfaces:**
- Consumes: la route `posters.file` de la tâche 1.
- Produces:
  - `App\Services\PosterBatchLink::LIFETIME_IN_HOURS` (int, 6).
  - `App\Services\PosterBatchLink::posters(array $partyIds, DateTimeInterface $expiresAt): array` qui renvoie, dans l'ordre des identifiants reçus et en ignorant les parties disparues, une liste de `array{id: int, name: string, url: string}` où `url` est l'URL signée de `posters.file` expirant à `$expiresAt`.
  - `App\Services\PosterBatchLink::parseIds(?string $value): array` qui transforme `"12,13"` en `[12, 13]`, en écartant tout ce qui n'est pas un entier positif.
  - La route nommée `posters.index`, d'URL `/affiches?parties=12,13`, protégée par `signed`, qui rend le composant Inertia `party/posters` avec la prop `posters`.

- [ ] **Step 1: Écrire les tests du service et de la page**

Créer `tests/Feature/PosterBatchPageTest.php` :

```php
<?php

use App\Models\Party;
use App\Services\PosterBatchLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

/**
 * The signed page URL the QR code would carry.
 */
function signedBatchUrl(array $partyIds, ?DateTimeInterface $expiresAt = null): string
{
    return URL::temporarySignedRoute(
        'posters.index',
        $expiresAt ?? now()->addHours(PosterBatchLink::LIFETIME_IN_HOURS),
        ['parties' => implode(',', $partyIds)],
    );
}

it('reads a comma separated list of ids', function () {
    expect(app(PosterBatchLink::class)->parseIds('12,13'))->toBe([12, 13]);
});

it('drops anything that is not a positive integer', function () {
    expect(app(PosterBatchLink::class)->parseIds('12,,abc,-3,0,13'))->toBe([12, 13]);
});

it('reads an empty list from a missing parameter', function () {
    expect(app(PosterBatchLink::class)->parseIds(null))->toBe([]);
});

it('signs every poster with the expiry of the page listing it', function () {
    $party = Party::factory()->create(['name' => 'signed-party']);

    $expiresAt = now()->addHours(6);

    $posters = app(PosterBatchLink::class)->posters([$party->id], $expiresAt);

    expect($posters)->toHaveCount(1)
        ->and($posters[0]['id'])->toBe($party->id)
        ->and($posters[0]['name'])->toBe('signed-party')
        ->and($posters[0]['url'])->toContain('expires='.$expiresAt->getTimestamp());
});

it('keeps the posters in the order of the requested ids', function () {
    $first = Party::factory()->create();
    $second = Party::factory()->create();

    $posters = app(PosterBatchLink::class)->posters([$second->id, $first->id], now()->addHour());

    expect(array_column($posters, 'id'))->toBe([$second->id, $first->id]);
});

it('renders the batch page for a signed link', function () {
    $first = Party::factory()->create(['name' => 'premiere-soiree']);
    $second = Party::factory()->create(['name' => 'seconde-soiree']);

    $this->get(signedBatchUrl([$first->id, $second->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('party/posters')
            ->has('posters', 2)
            ->where('posters.0.name', 'premiere-soiree')
            ->where('posters.1.name', 'seconde-soiree')
        );
});

it('hands out poster urls that the file route accepts', function () {
    $party = Party::factory()->create();

    $response = $this->get(signedBatchUrl([$party->id]));

    $url = $response->viewData('page')['props']['posters'][0]['url'];

    $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
});

it('skips a party deleted since the link was minted', function () {
    $kept = Party::factory()->create();
    $gone = Party::factory()->create();

    $url = signedBatchUrl([$kept->id, $gone->id]);

    $gone->delete();

    $this->get($url)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('party/posters')->has('posters', 1));
});

it('refuses an unsigned page link', function () {
    $party = Party::factory()->create();

    $this->get('/affiches?parties='.$party->id)->assertForbidden();
});

it('refuses an expired page link', function () {
    $party = Party::factory()->create();

    $url = signedBatchUrl([$party->id]);

    $this->travel(7)->hours();

    $this->get($url)->assertForbidden();
});
```

- [ ] **Step 2: Lancer les tests pour les voir échouer**

Run: `vendor/bin/sail artisan test --compact --filter=PosterBatchPageTest`
Expected: FAIL — `Class "App\Services\PosterBatchLink" not found`.

- [ ] **Step 3: Écrire le service**

Créer `app/Services/PosterBatchLink.php` :

```php
<?php

namespace App\Services;

use App\Models\Party;
use DateTimeInterface;
use Illuminate\Support\Facades\URL;

/**
 * Builds the signed links of a poster batch.
 *
 * A batch is nothing but a list of party identifiers carried by a signed URL,
 * so no state is stored: the signature is what makes the list trustworthy.
 */
class PosterBatchLink
{
    /**
     * How long a batch link stays valid, in hours.
     */
    public const LIFETIME_IN_HOURS = 6;

    /**
     * The posters of a batch, in the order of the requested identifiers.
     *
     * The files expire with the page that lists them, so that no poster dies
     * in the middle of a sharing session.
     *
     * @param  array<int, int>  $partyIds
     * @return array<int, array{id: int, name: string, url: string}>
     */
    public function posters(array $partyIds, DateTimeInterface $expiresAt): array
    {
        $parties = Party::query()
            ->whereIn('id', $partyIds)
            ->get(['id', 'name'])
            ->keyBy('id');

        return collect($partyIds)
            ->map(fn (int $partyId): ?Party => $parties->get($partyId))
            ->filter()
            ->map(fn (Party $party): array => [
                'id' => $party->id,
                'name' => $party->name,
                'url' => URL::temporarySignedRoute('posters.file', $expiresAt, ['party' => $party->id]),
            ])
            ->values()
            ->all();
    }

    /**
     * The identifiers carried by a batch link, as a comma separated list.
     *
     * @return array<int, int>
     */
    public function parseIds(?string $value): array
    {
        return collect(explode(',', (string) $value))
            ->map(fn (string $id): int => (int) trim($id))
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();
    }
}
```

- [ ] **Step 4: Ajouter l'action de page au contrôleur**

Dans `app/Http/Controllers/PosterController.php`, ajouter les imports :

```php
use App\Services\PosterBatchLink;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
```

et l'action, au-dessus de `file()` :

```php
/**
 * List the posters of a batch, for the phone that scanned its QR code.
 *
 * The signature guarantees the list of parties, and its expiry is reused to
 * sign each poster so that page and files die together.
 */
public function index(Request $request, PosterBatchLink $posterBatchLink): Response
{
    $expiresAt = now()->setTimestamp($request->integer('expires'));

    return Inertia::render('party/posters', [
        'posters' => $posterBatchLink->posters(
            $posterBatchLink->parseIds($request->string('parties')->toString()),
            $expiresAt,
        ),
    ]);
}
```

- [ ] **Step 5: Déclarer la route**

Dans `routes/web.php`, au-dessus de la route `posters.file` :

```php
Route::get('affiches', [PosterController::class, 'index'])
    ->middleware('signed')
    ->name('posters.index');
```

- [ ] **Step 6: Lancer les tests pour les voir passer**

Run: `vendor/bin/sail artisan test --compact --filter=PosterBatchPageTest`
Expected: PASS — 10 tests. `assertInertia` inspecte la réponse JSON d'Inertia sans monter le composant React, donc la page `party/posters`, écrite à la tâche 6, ne manque pas encore.

- [ ] **Step 7: Formater et committer**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add app/Services/PosterBatchLink.php app/Http/Controllers/PosterController.php routes/web.php tests/Feature/PosterBatchPageTest.php
git commit -m "feat(posters): list a signed batch of posters for the phone"
```

---

### Task 3 : Le lien et son QR code depuis l'admin

**Files:**
- Create: `app/Http/Controllers/Admin/PosterLinkController.php`
- Create: `app/Http/Requests/PosterLinkRequest.php`
- Create: `tests/Feature/AdminPosterLinkTest.php`
- Modify: `app/Services/PosterBatchLink.php`
- Modify: `routes/web.php`

**Interfaces:**
- Consumes: `PosterBatchLink::LIFETIME_IN_HOURS`, la route `posters.index` de la tâche 2.
- Produces:
  - `App\Services\PosterBatchLink::forParties(array $partyIds): array` qui renvoie `array{url: string, qr: string}`, où `url` est l'URL signée de `posters.index` valable `LIFETIME_IN_HOURS` heures et `qr` un data-URI `data:image/png;base64,…` de son QR code.
  - La route nommée `admin.parties.posters-link`, d'URL `/admin/parties/posters-link?parties=12,13`, sous `auth`, qui renvoie ce tableau en JSON.

- [ ] **Step 1: Écrire les tests**

Créer `tests/Feature/AdminPosterLinkTest.php` :

```php
<?php

use App\Models\Party;
use App\Models\User;
use App\Services\PosterBatchLink;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('mints a signed page link with its qr code', function () {
    $this->actingAs(User::factory()->create());

    $party = Party::factory()->create();

    $response = $this->getJson(route('admin.parties.posters-link', ['parties' => (string) $party->id]));

    $response->assertOk()
        ->assertJsonStructure(['url', 'qr']);

    expect($response->json('qr'))->toStartWith('data:image/png;base64,');

    $this->get($response->json('url'))->assertOk();
});

it('carries every requested party in the link', function () {
    $this->actingAs(User::factory()->create());

    $first = Party::factory()->create();
    $second = Party::factory()->create();

    $url = app(PosterBatchLink::class)->forParties([$first->id, $second->id])['url'];

    $this->get($url)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('party/posters')->has('posters', 2));
});

it('rejects an empty selection', function () {
    $this->actingAs(User::factory()->create());

    $this->getJson(route('admin.parties.posters-link', ['parties' => '']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('parties');
});

it('rejects a selection of unknown parties', function () {
    $this->actingAs(User::factory()->create());

    $this->getJson(route('admin.parties.posters-link', ['parties' => '404,405']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('parties');
});

it('keeps the link behind the admin session', function () {
    $party = Party::factory()->create();

    $this->getJson(route('admin.parties.posters-link', ['parties' => (string) $party->id]))
        ->assertUnauthorized();
});
```

- [ ] **Step 2: Lancer les tests pour les voir échouer**

Run: `vendor/bin/sail artisan test --compact --filter=AdminPosterLinkTest`
Expected: FAIL — `Route [admin.parties.posters-link] not defined.`

- [ ] **Step 3: Ajouter la fabrication du lien au service**

Dans `app/Services/PosterBatchLink.php`, ajouter les imports :

```php
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\PngWriter;
```

et la méthode, au-dessus de `posters()` :

```php
/**
 * The signed page URL of a batch, with the QR code that opens it.
 *
 * @param  array<int, int>  $partyIds
 * @return array{url: string, qr: string}
 */
public function forParties(array $partyIds): array
{
    $url = URL::temporarySignedRoute(
        'posters.index',
        now()->addHours(self::LIFETIME_IN_HOURS),
        ['parties' => implode(',', $partyIds)],
    );

    return [
        'url' => $url,
        'qr' => (new Builder(
            writer: new PngWriter,
            data: $url,
            errorCorrectionLevel: ErrorCorrectionLevel::Medium,
            size: 512,
            margin: 16,
        ))->build()->getDataUri(),
    ];
}
```

- [ ] **Step 4: Écrire la validation**

Créer `app/Http/Requests/PosterLinkRequest.php` :

```php
<?php

namespace App\Http\Requests;

use App\Models\Party;
use App\Services\PosterBatchLink;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Validator;

class PosterLinkRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'parties' => ['required', 'string'],
        ];
    }

    /**
     * The selection is a comma separated list, so the identifiers are checked
     * once parsed rather than through an array rule.
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                if ($this->partyIds() === []) {
                    $validator->errors()->add('parties', 'Please select at least one party.');
                }
            },
        ];
    }

    /**
     * The selected party identifiers that actually exist.
     *
     * @return array<int, int>
     */
    public function partyIds(): array
    {
        $ids = app(PosterBatchLink::class)->parseIds($this->string('parties')->toString());

        $existing = Party::query()->whereIn('id', $ids)->pluck('id')->all();

        return array_values(array_filter(
            $ids,
            fn (int $id): bool => in_array($id, $existing, true),
        ));
    }
}
```

- [ ] **Step 5: Écrire le contrôleur**

Créer `app/Http/Controllers/Admin/PosterLinkController.php` :

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PosterLinkRequest;
use App\Services\PosterBatchLink;
use Illuminate\Http\JsonResponse;

class PosterLinkController extends Controller
{
    /**
     * The signed link and QR code opening the posters of a selection.
     */
    public function show(PosterLinkRequest $request, PosterBatchLink $posterBatchLink): JsonResponse
    {
        return response()->json($posterBatchLink->forParties($request->partyIds()));
    }
}
```

- [ ] **Step 6: Déclarer la route**

Dans `routes/web.php`, ajouter l'import :

```php
use App\Http\Controllers\Admin\PosterLinkController;
```

et, dans le groupe `admin`, **avant** `Route::resource('parties', …)` pour que `posters-link` ne soit pas pris pour une party :

```php
Route::get('parties/posters-link', [PosterLinkController::class, 'show'])->name('parties.posters-link');
```

- [ ] **Step 7: Lancer les tests pour les voir passer**

Run: `vendor/bin/sail artisan test --compact --filter=AdminPosterLinkTest`
Expected: PASS — 5 tests.

- [ ] **Step 8: Formater et committer**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add app/Http/Controllers/Admin/PosterLinkController.php app/Http/Requests/PosterLinkRequest.php app/Services/PosterBatchLink.php routes/web.php tests/Feature/AdminPosterLinkTest.php
git commit -m "feat(posters): mint the batch link and its qr code from the admin"
```

---

### Task 4 : Les parties créées reviennent à la liste

**Files:**
- Modify: `app/Http/Controllers/Admin/PartyController.php`
- Modify: `tests/Feature/AdminPartiesBulkCreateTest.php`

**Interfaces:**
- Consumes: `bulkStore` existant.
- Produces: la clé de session `created_party_ids` (`array<int, int>`) déposée par `bulkStore`, et la prop Inertia `createdPartyIds` de `admin/parties/index`, qui vaut cette liste au retour d'une création en lot et `[]` partout ailleurs.

- [ ] **Step 1: Écrire les tests**

Ajouter à la fin de `tests/Feature/AdminPartiesBulkCreateTest.php` :

```php
it('hands the created party ids back to the parties list', function () {
    $response = $this->post('/admin/parties/bulk', [
        'parties' => [
            ['name' => 'premiere-soiree', 'source_event_id' => 'stub:1'],
            ['name' => 'seconde-soiree', 'source_event_id' => 'stub:2'],
        ],
    ]);

    $expected = Party::query()
        ->whereIn('name', ['premiere-soiree', 'seconde-soiree'])
        ->orderBy('id')
        ->pluck('id')
        ->all();

    $response->assertSessionHas('created_party_ids', $expected);

    $this->followingRedirects()
        ->post('/admin/parties/bulk', [
            'parties' => [['name' => 'troisieme-soiree', 'source_event_id' => 'stub:3']],
        ])
        ->assertInertia(fn ($page) => $page
            ->component('admin/parties/index')
            ->has('createdPartyIds', 1)
            ->etc()
        );
});

it('leaves the created party ids empty on a plain visit', function () {
    $this->get('/admin/parties')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('createdPartyIds', [])->etc());
});
```

- [ ] **Step 2: Lancer les tests pour les voir échouer**

Run: `vendor/bin/sail artisan test --compact --filter=AdminPartiesBulkCreateTest`
Expected: FAIL — `Session is missing expected key [created_party_ids]`.

- [ ] **Step 3: Déposer les identifiants et les repasser en prop**

Dans `app/Http/Controllers/Admin/PartyController.php`, remplacer le corps de `bulkStore` par :

```php
public function bulkStore(BulkStorePartiesRequest $request): RedirectResponse
{
    $parties = $request->validated('parties');

    $createdIds = DB::transaction(function () use ($parties): array {
        $ids = [];

        foreach ($parties as $party) {
            $sourceEventId = $party['source_event_id'] ?? null;

            $ids[] = Party::query()->create([
                'name' => $party['name'],
                'source_key' => $sourceEventId === null ? null : Str::before($sourceEventId, ':'),
                'source_event_id' => $sourceEventId,
            ])->id;
        }

        return $ids;
    });

    return to_route('admin.parties.index')->with('created_party_ids', $createdIds);
}
```

Puis, dans `index()`, ajouter la prop à côté de `partyDomain` :

```php
'createdPartyIds' => $request->session()->get('created_party_ids', []),
```

- [ ] **Step 4: Lancer les tests pour les voir passer**

Run: `vendor/bin/sail artisan test --compact --filter=AdminPartiesBulkCreateTest`
Expected: PASS.

- [ ] **Step 5: Formater et committer**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add app/Http/Controllers/Admin/PartyController.php tests/Feature/AdminPartiesBulkCreateTest.php
git commit -m "feat(parties): hand the created party ids back to the list"
```

---

### Task 5 : La boîte de dialogue du QR code

**Files:**
- Create: `resources/js/components/parties/posters-dialog.tsx`
- Modify: `resources/js/components/data-table/data-table-main.tsx`
- Modify: `resources/js/components/data-table.tsx`
- Modify: `resources/js/pages/admin/parties/index.tsx`
- Modify: `tests/Web/AdminPartiesTest.php`

**Interfaces:**
- Consumes: la route `admin.parties.posters-link` (tâche 3), la prop `createdPartyIds` (tâche 4).
- Produits attendus dans le DOM, sur lesquels s'appuient les tests : le bouton de barre d'outils `#open-posters-dialog`, l'image du QR code `#posters-qr`, le lien textuel `#posters-link`, le message d'erreur `#posters-error`.

- [ ] **Step 1: Écrire les tests navigateur**

Ajouter à la fin de `tests/Web/AdminPartiesTest.php` :

```php
// ── Affiches ──────────────────────────────────────────────────────────────────

it('shows the poster qr code right after a bulk creation', function () {
    $page = visit(route('admin.parties.index'));

    $page->click('Create from events')
        ->assertVisible('#bulk-create-party-name-0')
        ->click('#bulk-create-select-0')
        ->fill('#bulk-create-party-name-0', 'soiree-affiches')
        ->click('Create 1 party')
        ->assertVisible('#posters-qr')
        ->assertNoJavaScriptErrors();

    expect($page->attribute('#posters-qr', 'src'))->toStartWith('data:image/png;base64,');
});

it('opens the poster qr code for a selection of parties', function () {
    Party::factory()->create();

    $page = visit(route('admin.parties.index'));

    $page->assertPresent('table tbody tr:first-child')
        ->click('table tbody tr:first-child [aria-label="Select row"]')
        ->click('#open-posters-dialog')
        ->assertVisible('#posters-qr')
        ->assertNoJavaScriptErrors();
});

it('keeps the posters button disabled without a selection', function () {
    Party::factory()->create();

    $page = visit(route('admin.parties.index'));

    $page->assertPresent('#open-posters-dialog')
        ->assertNoJavaScriptErrors();

    expect($page->attribute('#open-posters-dialog', 'disabled'))->not->toBeNull();
});
```

- [ ] **Step 2: Lancer les tests pour les voir échouer**

Run: `vendor/bin/sail npm run build && vendor/bin/sail artisan test --compact --testsuite=Web --filter="poster"`
Expected: FAIL — l'élément `#posters-qr` n'existe pas.

- [ ] **Step 3: Remonter la sélection du tableau**

Dans `resources/js/components/data-table/data-table-main.tsx`, ajouter la prop à l'interface :

```tsx
interface DataTableMainProps {
    tableData: CrudTableData
    rowEditHrefBase?: string
    toolbarAction?: React.ReactNode
    onRowClick?: (row: CrudRow) => void
    rowActions?: CrudRowAction[]
    onSelectionChange?: (rows: CrudRow[]) => void
}
```

à la signature :

```tsx
export function DataTableMain({ tableData, rowEditHrefBase, toolbarAction, onRowClick, rowActions = [], onSelectionChange }: DataTableMainProps) {
```

puis, juste après la déclaration de `table`, l'effet qui remonte la sélection :

```tsx
    /*
     * The callback is held in a ref and left out of the dependencies: it is
     * usually an inline function, and depending on it would fire the effect on
     * every render, which the parent's state update would then feed back into.
     */
    const selectionListener = React.useRef(onSelectionChange)
    selectionListener.current = onSelectionChange

    React.useEffect(() => {
        selectionListener.current?.(table.getSelectedRowModel().rows.map((row) => row.original))
    }, [rowSelection, table])
```

- [ ] **Step 4: Passer la prop par le composant d'enrobage**

Dans `resources/js/components/data-table.tsx` :

```tsx
import { DataTableMain } from "@/components/data-table/index"
import type { CrudRow, CrudRowAction, CrudTableData } from "@/components/data-table/types"

interface DataTableProps {
    tableData: CrudTableData
    rowEditHrefBase?: string
    toolbarAction?: React.ReactNode
    onRowClick?: (row: CrudRow) => void
    rowActions?: CrudRowAction[]
    onSelectionChange?: (rows: CrudRow[]) => void
}

export function DataTable({ tableData, rowEditHrefBase, toolbarAction, onRowClick, rowActions, onSelectionChange }: DataTableProps) {
    return (
        <DataTableMain
            tableData={tableData}
            rowEditHrefBase={rowEditHrefBase}
            toolbarAction={toolbarAction}
            onRowClick={onRowClick}
            rowActions={rowActions}
            onSelectionChange={onSelectionChange}
        />
    )
}
```

- [ ] **Step 5: Écrire la boîte de dialogue**

Créer `resources/js/components/parties/posters-dialog.tsx` :

```tsx
import { useEffect, useState } from "react"

import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog"

type PostersLink = {
    url: string
    qr: string
}

type PostersDialogProps = {
    open: boolean
    onOpenChange: (open: boolean) => void
    partyIds: number[]
}

/**
 * Shows the QR code that opens the posters of a batch on a phone. The link is
 * minted by the server, which alone can sign it.
 */
export function PartyPostersDialog({ open, onOpenChange, partyIds }: PostersDialogProps) {
    const [link, setLink] = useState<PostersLink | null>(null)
    const [hasFailed, setFailed] = useState(false)

    // The joined ids keep the effect from firing on every render, since the
    // array itself is rebuilt by the parent each time.
    const selection = partyIds.join(",")

    useEffect(() => {
        if (!open || selection === "") {
            return
        }

        setLink(null)
        setFailed(false)

        fetch(`/admin/parties/posters-link?parties=${encodeURIComponent(selection)}`, {
            headers: { Accept: "application/json" },
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error("The poster link could not be minted.")
                }

                return response.json()
            })
            .then((payload: PostersLink) => setLink(payload))
            .catch(() => setFailed(true))
    }, [open, selection])

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>Posters</DialogTitle>
                    <DialogDescription>
                        Scan this code with the phone that prints the posters, then share them
                        all at once.
                    </DialogDescription>
                </DialogHeader>

                <div className="mt-4 flex flex-col items-center gap-4">
                    {hasFailed && (
                        <p id="posters-error" className="text-sm text-red-500">
                            The link could not be generated. Close the dialog and try again.
                        </p>
                    )}

                    {link && (
                        <>
                            <img
                                id="posters-qr"
                                src={link.qr}
                                alt="QR code of the posters"
                                className="size-64"
                            />
                            <a
                                id="posters-link"
                                href={link.url}
                                className="text-xs break-all text-muted-foreground underline"
                            >
                                {link.url}
                            </a>
                        </>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    )
}
```

- [ ] **Step 6: Brancher les deux entrées sur la liste des parties**

Dans `resources/js/pages/admin/parties/index.tsx` :

Ajouter l'import du composant, à côté de celui de `PartyBulkCreateDialog` :

```tsx
import { PartyPostersDialog } from "@/components/parties/posters-dialog"
```

Ajouter `createdPartyIds: number[]` au type `PageProps`, et `createdPartyIds` aux paramètres déstructurés du composant.

Ajouter les états et la sélection, à côté des autres `useState` :

```tsx
    const [isPostersModalOpen, setPostersModalOpen] = useState(false)
    const [postersPartyIds, setPostersPartyIds] = useState<number[]>([])
    const [selectedPartyIds, setSelectedPartyIds] = useState<number[]>([])
```

Ajouter, après les déclarations d'état, l'ouverture automatique au retour d'une création en lot :

```tsx
    // The list is only non-empty on the redirect that follows a bulk creation.
    const createdSelection = createdPartyIds.join(",")

    useEffect(() => {
        if (createdSelection === "") {
            return
        }

        setPostersPartyIds(createdSelection.split(",").map(Number))
        setPostersModalOpen(true)
    }, [createdSelection])
```

et compléter l'import de React en tête de fichier : `import { FormEvent, useEffect, useState } from "react"`.

Passer la sélection au tableau et ajouter le bouton de barre d'outils :

```tsx
            <DataTable
                tableData={tableData}
                onRowClick={openPartyDetails}
                rowActions={rowActions}
                onSelectionChange={(rows) =>
                    setSelectedPartyIds(rows.map((row) => Number(row.id)))
                }
                toolbarAction={
                    <>
                        <Button
                            id="open-posters-dialog"
                            size="sm"
                            variant="outline"
                            disabled={selectedPartyIds.length === 0}
                            onClick={() => {
                                setPostersPartyIds(selectedPartyIds)
                                setPostersModalOpen(true)
                            }}
                        >
                            <QrCodeIcon className="size-4" />
                            Posters
                        </Button>
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => setBulkCreateModalOpen(true)}
                        >
                            <WandSparklesIcon className="size-4" />
                            Create from events
                        </Button>
                        <Button size="sm" onClick={() => setCreateModalOpen(true)}>
                            Create party
                        </Button>
                    </>
                }
            />
```

en complétant l'import d'icônes : `import { DownloadIcon, QrCodeIcon, WandSparklesIcon } from "lucide-react"`.

Enfin, monter la boîte à côté de `PartyBulkCreateDialog` :

```tsx
            <PartyPostersDialog
                open={isPostersModalOpen}
                onOpenChange={setPostersModalOpen}
                partyIds={postersPartyIds}
            />
```

- [ ] **Step 7: Reconstruire et lancer les tests**

Run: `vendor/bin/sail npm run build && vendor/bin/sail artisan test --compact --testsuite=Web --filter="poster"`
Expected: PASS — 3 tests.

- [ ] **Step 8: Vérifier que le tableau n'a pas régressé ailleurs**

Run: `vendor/bin/sail artisan test --compact --testsuite=Web tests/Web/AdminPartiesTest.php`
Expected: PASS.

- [ ] **Step 9: Committer**

```bash
git add resources/js tests/Web/AdminPartiesTest.php
git commit -m "feat(parties): open the poster qr code from the list and after a bulk creation"
```

---

### Task 6 : La page téléphone et son partage

**Files:**
- Create: `resources/js/hooks/use-poster-share.ts`
- Create: `resources/js/pages/party/posters.tsx`
- Create: `tests/Web/PostersTest.php`

**Interfaces:**
- Consumes: la prop `posters` (`array<{ id: number; name: string; url: string }>`) rendue par `posters.index` (tâche 2).
- Produces : `usePosterShare(onError: (message: string) => void): PosterShare` avec `PosterShare = { supported: boolean; status: 'idle' | 'preparing' | 'sharing'; progress: { done: number; total: number }; share: (posters: SharePoster[]) => void }` et `SharePoster = { id: number; name: string; url: string }`. Dans le DOM : le bouton `#share-posters`, les liens de repli `[data-poster-link]`.

- [ ] **Step 1: Écrire les tests navigateur**

Créer `tests/Web/PostersTest.php` :

```php
<?php

use App\Models\Party;
use App\Services\PosterBatchLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

/**
 * The signed page URL a QR code would carry.
 */
function postersPageUrl(array $partyIds): string
{
    return URL::temporarySignedRoute(
        'posters.index',
        now()->addHours(PosterBatchLink::LIFETIME_IN_HOURS),
        ['parties' => implode(',', $partyIds)],
    );
}

/**
 * Stubs the Web Share API and the poster fetch, so the test covers the wiring
 * without generating real posters. The bytes have their own coverage in
 * tests/Feature/PosterFileTest.php.
 */
function stubPosterShare(object $page, string $shareResult = 'resolve'): void
{
    // visit() hands back the page while the navigation is still in flight, and
    // script() runs at once instead of retrying like the assertions do. Without
    // this barrier the stubs land on the transient about:blank document, which
    // the real page then replaces.
    $page->assertPresent('#share-posters');

    $page->script(<<<JS
        window.fetch = () => Promise.resolve(new Response(new Blob(['x'], { type: 'image/png' })));
        navigator.canShare = () => true;
        navigator.share = (data) => {
            document.title = 'shared:' + data.files.length;

            return '{$shareResult}' === 'resolve'
                ? Promise.resolve()
                : Promise.reject(new DOMException('cancelled', 'AbortError'));
        };

        // The evaluated script must not return a function: Playwright cannot
        // serialise one back to the test process.
        true;
    JS);
}

it('lists the parties of the batch', function () {
    $first = Party::factory()->create(['name' => 'premiere-soiree']);
    $second = Party::factory()->create(['name' => 'seconde-soiree']);

    $page = visit(postersPageUrl([$first->id, $second->id]));

    $page->assertSee('premiere-soiree')
        ->assertSee('seconde-soiree')
        ->assertNoJavaScriptErrors();
});

it('shares every poster of the batch at once', function () {
    $first = Party::factory()->create();
    $second = Party::factory()->create();

    $page = visit(postersPageUrl([$first->id, $second->id]));

    stubPosterShare($page);

    $page->click('#share-posters')
        ->assertTitleContains('shared:2')
        ->assertNoJavaScriptErrors();
});

it('stays quiet when the share sheet is dismissed', function () {
    $party = Party::factory()->create();

    $page = visit(postersPageUrl([$party->id]));

    stubPosterShare($page, 'reject');

    $page->click('#share-posters')
        ->assertDontSee('Le téléchargement des affiches a échoué')
        ->assertNoJavaScriptErrors();
});

/*
 * No stub here on purpose: none of the three browsers driven by this suite
 * implements sharing files on Linux, so the page naturally renders its fallback.
 * The plugin offers no hook to run a script before the page's own JavaScript,
 * which is what removing the API after the fact would require.
 */
it('falls back to download links without native sharing', function () {
    $party = Party::factory()->create(['name' => 'sans-partage']);

    $page = visit(postersPageUrl([$party->id]));

    $page->assertVisible('[data-poster-link]')
        ->assertMissing('#share-posters')
        ->assertNoJavaScriptErrors();

    expect($page->attribute('[data-poster-link]', 'download'))->toBe('sans-partage-affiche.png');
});
```

- [ ] **Step 2: Lancer les tests pour les voir échouer**

Run: `vendor/bin/sail npm run build && vendor/bin/sail artisan test --compact --testsuite=Web tests/Web/PostersTest.php`
Expected: FAIL — le composant `party/posters` est introuvable.

- [ ] **Step 3: Écrire le hook de partage**

Créer `resources/js/hooks/use-poster-share.ts` :

```ts
import { useCallback, useState } from 'react';

export type ShareStatus = 'idle' | 'preparing' | 'sharing';

export interface ShareProgress {
    done: number;
    total: number;
}

export interface SharePoster {
    id: number;
    name: string;
    url: string;
}

export interface PosterShare {
    supported: boolean;
    status: ShareStatus;
    progress: ShareProgress;
    share: (posters: SharePoster[]) => void;
}

/** Posters fetched at once, so the server never stacks more than two renders. */
const CONCURRENCY = 2;

const NO_PROGRESS: ShareProgress = { done: 0, total: 0 };

const DOWNLOAD_ERROR = 'Le téléchargement des affiches a échoué. Réessaie dans un instant.';

/**
 * Whether this browser can share files. The probe file matters: several
 * browsers expose navigator.share without accepting files at all.
 */
export function detectShareSupport(): boolean {
    if (
        typeof navigator === 'undefined' ||
        typeof navigator.share !== 'function' ||
        typeof navigator.canShare !== 'function'
    ) {
        return false;
    }

    try {
        return navigator.canShare({
            files: [new File([new Uint8Array(1)], 'affiche.png', { type: 'image/png' })],
        });
    } catch {
        return false;
    }
}

/**
 * Native sharing of a batch of posters.
 *
 * The posters are generated on demand by the server, so they are downloaded
 * two at a time before being handed to the share sheet in one call.
 */
export default function usePosterShare(onError: (message: string) => void): PosterShare {
    const [supported] = useState<boolean>(() => detectShareSupport());
    const [status, setStatus] = useState<ShareStatus>('idle');
    const [progress, setProgress] = useState<ShareProgress>(NO_PROGRESS);

    const download = useCallback(async (posters: SharePoster[]): Promise<File[]> => {
        const files: File[] = new Array(posters.length);
        let next = 0;
        let done = 0;

        const worker = async (): Promise<void> => {
            while (next < posters.length) {
                const index = next++;
                const response = await fetch(posters[index].url);

                if (!response.ok) {
                    throw new Error(`Failed to download the poster of party ${posters[index].id}`);
                }

                const blob = await response.blob();

                files[index] = new File([blob], `${posters[index].name}-affiche.png`, {
                    type: 'image/png',
                });

                done += 1;
                setProgress({ done, total: posters.length });
            }
        };

        await Promise.all(Array.from({ length: Math.min(CONCURRENCY, posters.length) }, worker));

        return files;
    }, []);

    const share = useCallback(
        (posters: SharePoster[]) => {
            setStatus('preparing');
            setProgress({ done: 0, total: posters.length });

            void download(posters)
                .then(async (files) => {
                    setStatus('sharing');

                    try {
                        await navigator.share({ files });
                    } catch (error) {
                        // A dismissed share sheet is not a failure.
                        if (!(error instanceof DOMException && error.name === 'AbortError')) {
                            throw error;
                        }
                    }

                    setStatus('idle');
                    setProgress(NO_PROGRESS);
                })
                .catch(() => {
                    setStatus('idle');
                    setProgress(NO_PROGRESS);
                    onError(DOWNLOAD_ERROR);
                });
        },
        [download, onError],
    );

    return { supported, status, progress, share };
}
```

- [ ] **Step 4: Écrire la page**

Créer `resources/js/pages/party/posters.tsx` :

```tsx
import { useCallback, useState } from 'react';
import { Head } from '@inertiajs/react';

import usePosterShare, { type SharePoster } from '@/hooks/use-poster-share';

interface PostersProps {
    posters: SharePoster[];
}

export default function Posters({ posters }: PostersProps) {
    const [error, setError] = useState<string | null>(null);
    const onError = useCallback((message: string) => setError(message), []);
    const { supported, status, progress, share } = usePosterShare(onError);

    const label =
        status === 'preparing'
            ? `${progress.done}/${progress.total} affiches prêtes`
            : 'Tout partager';

    return (
        <div className="mx-auto flex min-h-screen max-w-md flex-col gap-6 p-6">
            <Head title="Affiches" />

            <header className="space-y-1">
                <h1 className="text-2xl font-semibold">Affiches</h1>
                <p className="text-sm text-gray-500">
                    {posters.length} affiche{posters.length > 1 ? 's' : ''} à imprimer.
                </p>
            </header>

            <ul className="space-y-2">
                {posters.map((poster) => (
                    <li key={poster.id} className="rounded-md border border-gray-200 px-4 py-3">
                        <span className="text-sm">{poster.name}</span>

                        {!supported && (
                            <a
                                data-poster-link
                                href={poster.url}
                                download={`${poster.name}-affiche.png`}
                                className="ml-2 text-sm underline"
                            >
                                Télécharger
                            </a>
                        )}
                    </li>
                ))}
            </ul>

            {supported && (
                <button
                    id="share-posters"
                    type="button"
                    disabled={status !== 'idle'}
                    onClick={() => share(posters)}
                    className="rounded-md bg-black px-4 py-3 text-white disabled:opacity-60"
                >
                    {label}
                </button>
            )}

            {error && <p className="text-sm text-red-500">{error}</p>}
        </div>
    );
}
```

- [ ] **Step 5: Reconstruire et lancer les tests**

Run: `vendor/bin/sail npm run build && vendor/bin/sail artisan test --compact --testsuite=Web tests/Web/PostersTest.php`
Expected: PASS — 4 tests.

- [ ] **Step 6: Vérifier la suite complète**

Run: `vendor/bin/sail artisan test --compact`
Expected: PASS.

- [ ] **Step 7: Committer**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add resources/js tests/Web/PostersTest.php
git commit -m "feat(posters): share a whole batch from the phone"
```

---

## Vérification finale

- [ ] **Parcours manuel**

Sur `https://<app>/admin/parties`, créer deux parties depuis « Create from events », vérifier que la boîte « Posters » s'ouvre avec un QR code, l'ouvrir sur un téléphone et partager les deux affiches. Vérifier ensuite qu'une sélection dans le tableau ouvre la même boîte.

- [ ] **Suites complètes**

```bash
vendor/bin/sail bin pint --format agent
vendor/bin/sail npm run build
vendor/bin/sail artisan test --compact
```
