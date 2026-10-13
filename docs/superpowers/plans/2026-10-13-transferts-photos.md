# Transferts de photos — plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal :** depuis l'administration, déposer le dossier d'une borne, lui donner un nom, l'envoyer photo par photo et copier un lien `/transfers/<uuid>` qui ouvre la galerie publique existante.

**Architecture :** un transfert est une `Party` de `kind = transfer` dont `name` est un uuid ; il réutilise `pictures`, les miniatures, la page `party/gallery` et le ZIP. L'upload passe par l'endpoint admin existant `party.dashboard.pictures.store`, rendu idempotent par une colonne `pictures.original_name` (chemin relatif dans le dossier déposé) sous index unique. Le front parcourt le dossier, le résume, puis envoie les fichiers un par un via une petite file à concurrence fixe avec relances.

**Tech Stack :** Laravel 13, PHP 8.5, MariaDB, Inertia v2 + React 19 + TypeScript, Tailwind v4, Ziggy (`route()`), Pest v4 (Feature + Browser via Playwright), Sail.

**Spec :** `docs/superpowers/specs/2026-10-13-transferts-photos.md`

## Global Constraints

- Toutes les commandes passent par Sail : `vendor/bin/sail artisan …`, `vendor/bin/sail npm …`.
- Tests : `vendor/bin/sail artisan test --compact --filter=<Nom>` ; tests navigateur dans `tests/Web/`.
- Aucune nouvelle dépendance composer ou npm.
- Libellés de l'administration en anglais, comme le reste du backoffice (`Parties`, `Users`, `Create party`) : l'entrée de sidebar s'appelle **Transfers**.
- Style front admin : double quotes, pas de point-virgule, indentation 4 espaces (voir `resources/js/pages/admin/parties/index.tsx`).
- Lancer `vendor/bin/sail bin pint --dirty` avant chaque commit PHP.
- Limite par photo : 10 Mo (`max:10240` dans `StoreAdminPicturesRequest`), soit `10 * 1024 * 1024` octets côté front.
- Concurrence d'envoi : 3. Relances : après 1 s, 3 s, 9 s.
- `original_name` : 255 caractères au plus.
- Écart assumé avec la spec : `original_name` n'est renseigné **que** quand la requête porte `paths`. Sans `paths` (formulaire du dashboard), il reste `null` et aucune déduplication n'a lieu, sinon deux photos homonymes de deux téléphones (`IMG_1234.jpg`) envoyées depuis le dashboard s'écraseraient l'une l'autre.

## Review Focus

1. **Upload de la borne (`Api\PictureController`) et formulaire du dashboard** : ils n'envoient pas `paths`, leurs photos gardent `original_name = null`, plusieurs `null` coexistent sous l'index unique et deux fichiers homonymes sont tous deux stockés. Testé en Task 3.
2. **Suppression via `admin.transfers.destroy` d'une party événement** : doit répondre 404 et ne rien supprimer, sinon un id deviné efface un événement. Testé en Task 2.
3. **`paths` invalide (entrée de plus de 255 caractères)** : 422, pas une erreur SQL. Testé en Task 3.
4. **Transferts dans les listes d'événements** : ni l'écran Parties ni le sélecteur de party de la sidebar ne doivent montrer un uuid. Testé en Task 1.
5. **Session expirée au milieu d'un envoi** : la file s'arrête net au premier 401/419 au lieu d'épuiser ses relances sur 480 fichiers. Vérifié à la main en Task 6 (pas de runner JS dans le projet).

---

## Carte des fichiers

| Fichier | Rôle |
|---|---|
| `app/Enums/PartyKind.php` (créé) | Enum `Event` / `Transfer` |
| `database/migrations/2026_10_13_220000_add_kind_to_parties_table.php` (créé) | Colonne `parties.kind` |
| `database/migrations/2026_10_13_220001_add_original_name_to_pictures_table.php` (créé) | Colonne `pictures.original_name` + index unique |
| `app/Models/Party.php` | Cast `kind`, `isTransfer()`, `publicUrl()` |
| `app/Models/Picture.php` | `original_name` fillable |
| `database/factories/PartyFactory.php` | État `transfer()` |
| `app/Services/AdminDashboardDataService.php` | Sidebar : événements seulement |
| `app/Http/Controllers/Admin/PartyController.php` | Index : événements seulement |
| `app/Http/Controllers/PartyController.php` | 404 sur `transfers.show` pour un événement |
| `app/Http/Controllers/Admin/TransferController.php` (créé) | Index, création JSON, suppression |
| `app/Http/Requests/StoreTransferRequest.php` (créé) | Validation de la création |
| `app/Http/Requests/StoreAdminPicturesRequest.php` | Champ `paths` |
| `app/Http/Controllers/Admin/GalleryController.php` | Déduplication et réponse JSON |
| `routes/web.php` | `transfers.show` et `admin.transfers.*` |
| `resources/js/lib/folder-drop.ts` (créé) | Parcours du dossier et analyse |
| `resources/js/lib/upload-queue.ts` (créé) | File d'envoi, relances, en-têtes XSRF |
| `resources/js/components/transfers/folder-upload.tsx` (créé) | Zone de dépôt, récapitulatif, progression |
| `resources/js/pages/admin/transfers/index.tsx` (créé) | Écran Transfers |
| `resources/js/components/app-sidebar.tsx` | Entrée Transfers |
| `tests/Feature/TransfersTest.php` (créé) | Modèle, route publique, filtres |
| `tests/Feature/AdminTransfersTest.php` (créé) | Écran admin |
| `tests/Feature/AdminGalleryTest.php` | Upload : `paths`, déduplication, JSON |
| `tests/Web/AdminTransfersTest.php` (créé) | Rendu navigateur de l'écran |

---

### Task 1 : `parties.kind`, URL publique et filtres des listes

**Files:**
- Create: `app/Enums/PartyKind.php`
- Create: `database/migrations/2026_10_13_220000_add_kind_to_parties_table.php`
- Modify: `app/Models/Party.php` (`$fillable`, `casts()`, `publicUrl()`)
- Modify: `database/factories/PartyFactory.php`
- Modify: `app/Services/AdminDashboardDataService.php:22-25`
- Modify: `app/Http/Controllers/Admin/PartyController.php` (requête `rows` de `index`)
- Test: `tests/Feature/TransfersTest.php`

**Interfaces:**
- Produces : `App\Enums\PartyKind` (`Event = 'event'`, `Transfer = 'transfer'`) ; `Party::isTransfer(): bool` ; `Party::publicUrl()` renvoie `route('transfers.show', $party)` pour un transfert ; `PartyFactory::transfer(): static` (kind transfer, `name` uuid, `display_name` renseigné).
- `route('transfers.show')` n'existe qu'à la Task 2 : `publicUrl()` d'un transfert n'est donc testé qu'en Task 2.

- [ ] **Step 1 : Écrire les tests qui échouent**

`tests/Feature/TransfersTest.php` :

```php
<?php

use App\Enums\PartyKind;
use App\Models\Party;
use App\Models\User;

it('creates events by default', function () {
    $party = Party::factory()->create();

    expect($party->refresh()->kind)->toBe(PartyKind::Event)
        ->and($party->isTransfer())->toBeFalse();
});

it('keeps the subdomain url for an event', function () {
    $party = Party::factory()->create(['name' => 'mariage-julie']);

    expect($party->publicUrl())->toBe('https://mariage-julie.'.config('party.domain'));
});

it('builds transfers named by a uuid', function () {
    $transfer = Party::factory()->transfer()->create();

    expect($transfer->isTransfer())->toBeTrue()
        ->and(Str::isUuid($transfer->name))->toBeTrue();
});

it('lists only events on the parties screen', function () {
    $this->actingAs(User::factory()->create());
    $event = Party::factory()->create();
    Party::factory()->transfer()->create();

    $this->get(route('admin.parties.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('rows', 1)
            ->where('rows.0.id', $event->id)
        );
});

it('keeps transfers out of the sidebar party switcher', function () {
    $this->actingAs(User::factory()->create());
    $event = Party::factory()->create();
    $transfer = Party::factory()->transfer()->create();

    $this->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('searchableParties', fn ($parties) => collect($parties)->pluck('id')->all() === [$event->id])
        );
});
```

Ajouter `use Illuminate\Support\Str;` en tête du fichier.

- [ ] **Step 2 : Lancer les tests, vérifier l'échec**

Run : `vendor/bin/sail artisan test --compact --filter=TransfersTest`
Expected : FAIL, `Class "App\Enums\PartyKind" not found`.

- [ ] **Step 3 : Enum**

`app/Enums/PartyKind.php` :

```php
<?php

namespace App\Enums;

/**
 * What a party is for. An event is published under its own subdomain with a
 * poster; a transfer only delivers a photobooth's pictures after the fact,
 * behind an unguessable link.
 */
enum PartyKind: string
{
    case Event = 'event';
    case Transfer = 'transfer';
}
```

- [ ] **Step 4 : Migration**

`database/migrations/2026_10_13_220000_add_kind_to_parties_table.php` :

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('parties', function (Blueprint $table): void {
            $table->string('kind')->default('event')->after('name');
        });
    }

    public function down(): void
    {
        Schema::table('parties', function (Blueprint $table): void {
            $table->dropColumn('kind');
        });
    }
};
```

- [ ] **Step 5 : Modèle**

Dans `app/Models/Party.php` :

- ajouter `'kind',` à `$fillable` ;
- ajouter `'kind' => 'event',` à `$attributes` (l'instance fraîchement créée doit répondre `Event` avant relecture) ;
- ajouter `'kind' => PartyKind::class,` dans `casts()` ;
- importer `use App\Enums\PartyKind;` ;
- ajouter, avant `publicHost()` :

```php
    public function isTransfer(): bool
    {
        return $this->kind === PartyKind::Transfer;
    }
```

- remplacer le corps de `publicUrl()` :

```php
    /**
     * Absolute URL of the party's gallery, as encoded in the poster QR code.
     * A transfer has no subdomain: its gallery lives under the app's own
     * domain, behind the uuid it is named by.
     */
    public function publicUrl(): string
    {
        if ($this->isTransfer()) {
            return route('transfers.show', $this);
        }

        return 'https://'.$this->publicHost();
    }
```

- [ ] **Step 6 : Factory**

Dans `database/factories/PartyFactory.php`, importer `use App\Enums\PartyKind;` et ajouter :

```php
    public function transfer(): static
    {
        return $this->state(fn (array $attributes) => [
            'kind' => PartyKind::Transfer,
            'name' => fake()->uuid(),
            'display_name' => fake()->words(2, true),
        ]);
    }
```

- [ ] **Step 7 : Filtres**

`app/Services/AdminDashboardDataService.php`, dans `sidebarData()` :

```php
        $searchableParties = Party::query()
            ->where('kind', PartyKind::Event)
            ->orderBy('name')
```

(importer `use App\Enums\PartyKind;`).

`app/Http/Controllers/Admin/PartyController.php`, dans `index()` :

```php
            'rows' => Party::query()
                ->where('kind', PartyKind::Event)
                ->with('user:id,name')
```

(importer `use App\Enums\PartyKind;`).

- [ ] **Step 8 : Migrer et relancer**

Run : `vendor/bin/sail artisan migrate && vendor/bin/sail artisan test --compact --filter=TransfersTest`
Expected : PASS (5 tests).

Run : `vendor/bin/sail artisan test --compact --filter='AdminPartiesTest|AdminUsersTest|AdminGalleryTest'`
Expected : PASS, aucune régression.

- [ ] **Step 9 : Commit**

```bash
vendor/bin/sail bin pint --dirty
git add app/Enums/PartyKind.php database/migrations/2026_10_13_220000_add_kind_to_parties_table.php app/Models/Party.php database/factories/PartyFactory.php app/Services/AdminDashboardDataService.php app/Http/Controllers/Admin/PartyController.php tests/Feature/TransfersTest.php
git commit -m "feat(transfers): distingue les transferts des événements sur parties"
```

---

### Task 2 : routes publique et admin des transferts

**Files:**
- Create: `app/Http/Controllers/Admin/TransferController.php`
- Create: `app/Http/Requests/StoreTransferRequest.php`
- Modify: `routes/web.php`
- Modify: `app/Http/Controllers/PartyController.php` (`show()`)
- Test: `tests/Feature/TransfersTest.php`, `tests/Feature/AdminTransfersTest.php`

**Interfaces:**
- Consumes : `PartyKind`, `Party::isTransfer()`, `PartyFactory::transfer()` (Task 1).
- Produces :
  - route `transfers.show` : `GET /transfers/{party}` ;
  - `admin.transfers.index` : Inertia `admin/transfers/index` avec `rows: { id, name, display_name, pictures_count, expire_at, created_at, public_url }[]` + données de sidebar ;
  - `admin.transfers.store` : `POST`, corps JSON `{ display_name: string, expire_at: string|null }`, réponse `201 { name, public_url, upload_url }` ; 422 JSON standard en cas d'erreur de validation ;
  - `admin.transfers.destroy` : `DELETE /admin/transfers/{transfer}`, redirige vers l'index, 404 si la party n'est pas un transfert.

- [ ] **Step 1 : Tests de la route publique**

Ajouter à `tests/Feature/TransfersTest.php` :

```php
it('serves a transfer gallery under /transfers', function () {
    $transfer = Party::factory()->transfer()->create();

    $this->get('/transfers/'.$transfer->name)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('party/gallery')
            ->where('party.id', $transfer->id)
        );
});

it('does not serve an event under /transfers', function () {
    $event = Party::factory()->create(['name' => 'mariage-julie']);

    $this->get('/transfers/mariage-julie')->assertNotFound();
});

it('points the public url of a transfer at /transfers', function () {
    $transfer = Party::factory()->transfer()->create();

    expect($transfer->publicUrl())->toBe(url('/transfers/'.$transfer->name));
});
```

- [ ] **Step 2 : Tests admin**

`tests/Feature/AdminTransfersTest.php` :

```php
<?php

use App\Enums\PartyKind;
use App\Models\Party;
use App\Models\Picture;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('lists only transfers, newest first, with their link and picture count', function () {
    Party::factory()->create();
    $older = Party::factory()->transfer()->create(['created_at' => now()->subDay()]);
    $newer = Party::factory()->transfer()->create();
    Picture::factory()->for($newer)->count(2)->create();

    $this->get(route('admin.transfers.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/transfers/index')
            ->has('rows', 2)
            ->where('rows.0.id', $newer->id)
            ->where('rows.0.pictures_count', 2)
            ->where('rows.0.public_url', url('/transfers/'.$newer->name))
            ->where('rows.1.id', $older->id)
            ->has('parties')
        );
});

it('creates a transfer named by a uuid and answers with its urls', function () {
    $response = $this->postJson(route('admin.transfers.store'), [
        'display_name' => 'Mariage Julie & Tom',
        'expire_at' => null,
    ])->assertCreated();

    $transfer = Party::query()->sole();

    expect($transfer->kind)->toBe(PartyKind::Transfer)
        ->and(Str::isUuid($transfer->name))->toBeTrue()
        ->and($transfer->display_name)->toBe('Mariage Julie & Tom')
        ->and($transfer->user_id)->toBeNull();

    $response->assertExactJson([
        'name' => $transfer->name,
        'public_url' => url('/transfers/'.$transfer->name),
        'upload_url' => route('party.dashboard.pictures.store', $transfer),
    ]);
});

it('stores the optional expiry date of a transfer', function () {
    $this->postJson(route('admin.transfers.store'), [
        'display_name' => 'Gala',
        'expire_at' => '2026-12-31T22:00:00.000Z',
    ])->assertCreated();

    expect(Party::query()->sole()->expire_at->toIso8601String())->toBe('2026-12-31T22:00:00+00:00');
});

it('requires a name to create a transfer', function () {
    $this->postJson(route('admin.transfers.store'), ['display_name' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('display_name');

    expect(Party::query()->exists())->toBeFalse();
});

it('deletes a transfer', function () {
    Storage::fake('s3');
    $transfer = Party::factory()->transfer()->create();

    $this->delete(route('admin.transfers.destroy', $transfer))
        ->assertRedirect(route('admin.transfers.index'));

    $this->assertModelMissing($transfer);
});

it('refuses to delete an event through the transfers route', function () {
    $event = Party::factory()->create();

    $this->delete(route('admin.transfers.destroy', $event))->assertNotFound();

    $this->assertModelExists($event);
});

it('keeps the transfers screen behind authentication', function () {
    auth()->logout();

    $this->get(route('admin.transfers.index'))->assertRedirect(route('login'));
});
```

- [ ] **Step 3 : Lancer les tests, vérifier l'échec**

Run : `vendor/bin/sail artisan test --compact --filter='TransfersTest|AdminTransfersTest'`
Expected : FAIL, `Route [admin.transfers.index] not defined` et 404 sur `/transfers/…`.

- [ ] **Step 4 : Form request**

`app/Http/Requests/StoreTransferRequest.php` :

```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTransferRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'display_name' => ['required', 'string', 'max:255'],
            'expire_at' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'display_name.required' => 'Please provide a name for the transfer.',
            'display_name.max' => 'The name must not exceed 255 characters.',
            'expire_at.date' => 'The expiry date must be a valid date.',
        ];
    }
}
```

- [ ] **Step 5 : Contrôleur admin**

`app/Http/Controllers/Admin/TransferController.php` :

```php
<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PartyKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransferRequest;
use App\Models\Party;
use App\Services\AdminDashboardDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TransferController extends Controller
{
    public function index(AdminDashboardDataService $adminDashboardDataService): Response
    {
        return Inertia::render('admin/transfers/index', [
            ...$adminDashboardDataService->sidebarData(),
            'rows' => Party::query()
                ->where('kind', PartyKind::Transfer)
                ->withCount('pictures')
                ->latest()
                ->latest('id')
                ->get()
                ->map(fn (Party $transfer): array => [
                    'id' => $transfer->id,
                    'name' => $transfer->name,
                    'display_name' => $transfer->display_name,
                    'pictures_count' => $transfer->pictures_count,
                    'expire_at' => $transfer->expire_at?->toIso8601String(),
                    'created_at' => $transfer->created_at?->toIso8601String(),
                    'public_url' => $transfer->publicUrl(),
                ]),
        ]);
    }

    /**
     * Answered in JSON rather than by a redirect: the dialog that calls it goes
     * straight on to post the folder's pictures to the returned upload URL.
     */
    public function store(StoreTransferRequest $request): JsonResponse
    {
        $transfer = Party::query()->create([
            ...$request->validated(),
            'kind' => PartyKind::Transfer,
            'name' => (string) Str::uuid(),
        ]);

        return response()->json([
            'name' => $transfer->name,
            'public_url' => $transfer->publicUrl(),
            'upload_url' => route('party.dashboard.pictures.store', $transfer),
        ], 201);
    }

    public function destroy(Party $transfer): RedirectResponse
    {
        abort_unless($transfer->isTransfer(), 404);

        $transfer->delete();

        return to_route('admin.transfers.index');
    }
}
```

- [ ] **Step 6 : Routes**

Dans `routes/web.php`, importer `use App\Http\Controllers\Admin\TransferController as AdminTransferController;`, puis insérer **juste avant** `Route::prefix('{party}')` :

```php
/**
 * Declared ahead of the `{party}` group, which would otherwise read
 * `transfers` as a party name and capture `/transfers/dashboard`.
 */
Route::get('transfers/{party}', [PartyController::class, 'show'])
    ->middleware(ResolvePartyHost::class)
    ->name('transfers.show');
```

Et dans le groupe `admin`, après la ligne `Route::resource('parties', …)` :

```php
    Route::resource('transfers', AdminTransferController::class)->only(['index', 'store', 'destroy']);
```

- [ ] **Step 7 : 404 d'un événement sous `/transfers`**

Dans `app/Http/Controllers/PartyController.php`, première ligne de `show()` :

```php
        abort_if($request->routeIs('transfers.show') && ! $party->isTransfer(), 404);
```

- [ ] **Step 8 : Relancer**

Run : `vendor/bin/sail artisan test --compact --filter='TransfersTest|AdminTransfersTest'`
Expected : PASS (8 + 7 tests). Le test d'index échoue tant que la page `admin/transfers/index` n'existe pas si `assertInertia` vérifie l'existence du composant : dans ce cas, créer dès maintenant un fichier minimal `resources/js/pages/admin/transfers/index.tsx` contenant `export default function TransfersIndex() { return null }`, remplacé en Task 5.

Run : `vendor/bin/sail artisan test --compact --filter='GalleryTest|PartyController'`
Expected : PASS, la galerie des événements n'a pas bougé.

- [ ] **Step 9 : Commit**

```bash
vendor/bin/sail bin pint --dirty
git add app/Http/Controllers/Admin/TransferController.php app/Http/Requests/StoreTransferRequest.php routes/web.php app/Http/Controllers/PartyController.php tests/Feature/TransfersTest.php tests/Feature/AdminTransfersTest.php resources/js/pages/admin/transfers/index.tsx
git commit -m "feat(transfers): routes publique et admin des transferts"
```

(n'ajouter `index.tsx` que s'il a été créé au Step 8.)

---

### Task 3 : upload idempotent par chemin relatif

**Files:**
- Create: `database/migrations/2026_10_13_220001_add_original_name_to_pictures_table.php`
- Modify: `app/Models/Picture.php:21-28` (`$fillable`)
- Modify: `app/Http/Requests/StoreAdminPicturesRequest.php`
- Modify: `app/Http/Controllers/Admin/GalleryController.php` (`store()`)
- Test: `tests/Feature/AdminGalleryTest.php`

**Interfaces:**
- Produces : `POST /{party}/dashboard/pictures` accepte `photos[]` et `paths[]` optionnel (même index). Avec `Accept: application/json`, répond `200 { created: int, skipped: int }` ; sinon redirige comme avant vers `party.dashboard.pictures`.
- Une photo dont le `paths[i]` existe déjà comme `original_name` pour la party est comptée `skipped`, sans écriture S3.

- [ ] **Step 1 : Tests**

Ajouter à `tests/Feature/AdminGalleryTest.php` :

```php
it('records the relative path of a picture sent with paths and answers in json', function () {
    Storage::fake('s3');
    Queue::fake();
    $party = Party::factory()->transfer()->create();

    $this->postJson(route('party.dashboard.pictures.store', $party), [
        'photos' => [UploadedFile::fake()->image('IMG_0042.jpg', 1200, 800)],
        'paths' => ['raw/IMG_0042.jpg'],
    ])->assertOk()->assertExactJson(['created' => 1, 'skipped' => 0]);

    expect($party->pictures()->sole()->original_name)->toBe('raw/IMG_0042.jpg');
    Queue::assertPushed(CreatePictureThumbnail::class, 1);
});

it('skips a picture already sent under the same path without touching storage', function () {
    Storage::fake('s3');
    Queue::fake();
    $party = Party::factory()->transfer()->create();
    Picture::factory()->for($party)->create(['original_name' => 'IMG_0042.jpg']);

    $this->postJson(route('party.dashboard.pictures.store', $party), [
        'photos' => [UploadedFile::fake()->image('IMG_0042.jpg')],
        'paths' => ['IMG_0042.jpg'],
    ])->assertOk()->assertExactJson(['created' => 0, 'skipped' => 1]);

    expect($party->pictures()->count())->toBe(1);
    expect(Storage::disk('s3')->allFiles())->toBe([]);
    Queue::assertNothingPushed();
    expect($party->activities()->exists())->toBeFalse();
});

it('accepts the same path in two different parties', function () {
    Storage::fake('s3');
    Queue::fake();
    $other = Party::factory()->transfer()->create();
    Picture::factory()->for($other)->create(['original_name' => 'IMG_0042.jpg']);
    $party = Party::factory()->transfer()->create();

    $this->postJson(route('party.dashboard.pictures.store', $party), [
        'photos' => [UploadedFile::fake()->image('IMG_0042.jpg')],
        'paths' => ['IMG_0042.jpg'],
    ])->assertOk()->assertExactJson(['created' => 1, 'skipped' => 0]);
});

it('stores homonymous pictures sent from the dashboard form without paths', function () {
    Storage::fake('s3');
    Queue::fake();
    $party = Party::factory()->create();

    foreach ([1, 2] as $attempt) {
        $this->post(route('party.dashboard.pictures.store', $party), [
            'photos' => [UploadedFile::fake()->image('IMG_1234.jpg')],
        ])->assertRedirect(route('party.dashboard.pictures', $party));
    }

    expect($party->pictures()->count())->toBe(2)
        ->and($party->pictures()->whereNull('original_name')->count())->toBe(2);
});

it('rejects a path longer than 255 characters', function () {
    $party = Party::factory()->transfer()->create();

    $this->postJson(route('party.dashboard.pictures.store', $party), [
        'photos' => [UploadedFile::fake()->image('a.jpg')],
        'paths' => [str_repeat('a', 256).'.jpg'],
    ])->assertUnprocessable()->assertJsonValidationErrors('paths.0');
});
```

- [ ] **Step 2 : Lancer, vérifier l'échec**

Run : `vendor/bin/sail artisan test --compact --filter=AdminGalleryTest`
Expected : FAIL, colonne `original_name` inconnue et réponse 302 au lieu de 200.

- [ ] **Step 3 : Migration**

`database/migrations/2026_10_13_220001_add_original_name_to_pictures_table.php` :

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Path of the picture inside the folder it was dropped from, root folder
     * excluded. Pictures sent by the booth leave it null, and nulls never
     * collide under the unique index.
     */
    public function up(): void
    {
        Schema::table('pictures', function (Blueprint $table): void {
            $table->string('original_name')->nullable()->after('path');
            $table->unique(['party_id', 'original_name']);
        });
    }

    public function down(): void
    {
        Schema::table('pictures', function (Blueprint $table): void {
            $table->dropUnique(['party_id', 'original_name']);
            $table->dropColumn('original_name');
        });
    }
};
```

Ajouter `'original_name',` au `$fillable` de `app/Models/Picture.php`.

- [ ] **Step 4 : Validation**

Dans `StoreAdminPicturesRequest::rules()` :

```php
        return [
            'photos' => ['required', 'array', 'min:1'],
            'photos.*' => ['required', 'image', 'max:10240'],
            'paths' => ['sometimes', 'array'],
            'paths.*' => ['required', 'string', 'max:255'],
        ];
```

- [ ] **Step 5 : Contrôleur**

Remplacer `GalleryController::store()` (imports à ajouter : `Illuminate\Database\UniqueConstraintViolationException`, `Illuminate\Http\JsonResponse`) :

```php
    /**
     * A photo sent with a `paths` entry is recorded under that path, and sent
     * again it is skipped: the transfer upload retries after timeouts and is
     * resumed by dropping the same folder twice. Without `paths`, as from the
     * dashboard form, every photo is stored, homonyms included.
     */
    public function store(StoreAdminPicturesRequest $request, Party $party): RedirectResponse|JsonResponse
    {
        /** @var UploadedFile[] $photos */
        $photos = $request->file('photos');
        /** @var array<int, string> $paths */
        $paths = $request->validated('paths', []);
        $created = 0;

        foreach ($photos as $index => $photo) {
            $originalName = $paths[$index] ?? null;

            if ($originalName !== null && $party->pictures()->where('original_name', $originalName)->exists()) {
                continue;
            }

            $dimensions = getimagesize($photo->getPathname());

            abort_if($dimensions === false, 422, 'Une des photos est illisible.');

            $path = Storage::disk('s3')->putFile("pictures/{$party->id}", $photo);

            abort_if($path === false, 500, 'Le stockage des photos est indisponible.');

            try {
                $picture = $party->pictures()->create([
                    'path' => $path,
                    'original_name' => $originalName,
                    'width' => $dimensions[0],
                    'height' => $dimensions[1],
                ]);
            } catch (UniqueConstraintViolationException) {
                // A concurrent request stored the same path first.
                Storage::disk('s3')->delete($path);

                continue;
            }

            CreatePictureThumbnail::dispatch($picture);
            $created++;
        }

        if ($created > 0) {
            $party->recordActivity(PartyActivityType::PicturesUploaded, ['count' => $created]);
        }

        if ($request->wantsJson()) {
            return response()->json(['created' => $created, 'skipped' => count($photos) - $created]);
        }

        return redirect()->route('party.dashboard.pictures', $party);
    }
```

`wantsJson()` et non `expectsJson()` : les requêtes Inertia sont en XHR mais acceptent `text/html`, elles doivent continuer à recevoir la redirection.

- [ ] **Step 6 : Migrer et relancer**

Run : `vendor/bin/sail artisan migrate && vendor/bin/sail artisan test --compact --filter='AdminGalleryTest|PictureControllerTest|Api'`
Expected : PASS, nouveaux tests compris ; l'upload de la borne (API) est inchangé.

- [ ] **Step 7 : Commit**

```bash
vendor/bin/sail bin pint --dirty
git add database/migrations/2026_10_13_220001_add_original_name_to_pictures_table.php app/Models/Picture.php app/Http/Requests/StoreAdminPicturesRequest.php app/Http/Controllers/Admin/GalleryController.php tests/Feature/AdminGalleryTest.php
git commit -m "feat(transfers): upload admin idempotent par chemin relatif"
```

---

### Task 4 : parcours du dossier et file d'envoi (front, sans UI)

**Files:**
- Create: `resources/js/lib/folder-drop.ts`
- Create: `resources/js/lib/upload-queue.ts`

**Interfaces:**
- Produces (`folder-drop.ts`) :
  - `type DroppedFile = { file: File, path: string }` (chemin relatif, dossier racine retiré) ;
  - `type FolderGroup = { folder: string, items: DroppedFile[] }` (`folder` vaut `""` pour la racine) ;
  - `type FolderAnalysis = { groups: FolderGroup[], tooLarge: DroppedFile[], ignoredCount: number }` ;
  - `collectFromDataTransfer(dataTransfer: DataTransfer): Promise<DroppedFile[]>` (rejette si plusieurs dossiers sont déposés à la fois) ;
  - `collectFromInput(files: FileList): DroppedFile[]` ;
  - `analyseFolder(files: DroppedFile[]): FolderAnalysis` ;
  - `MAX_FILE_BYTES = 10 * 1024 * 1024`.
- Produces (`upload-queue.ts`) :
  - `type UploadOutcome = "created" | "skipped" | "failed"` ;
  - `class SessionExpiredError extends Error` ;
  - `xsrfHeaders(): Record<string, string>` ;
  - `runUploadQueue(url: string, items: DroppedFile[], signal: AbortSignal, onSettled: (item: DroppedFile, outcome: UploadOutcome) => void): Promise<void>` — rejette `SessionExpiredError` dès un 401/419.

Le projet n'a pas de runner de tests JS et la contrainte « aucune nouvelle dépendance » interdit d'en ajouter un : ces modules sont vérifiés par le typage (Step 3) et à la main en Task 6.

- [ ] **Step 1 : `folder-drop.ts`**

```ts
/**
 * Turns a dropped or picked photobooth folder into the list of pictures to
 * send, each one keyed by its path inside that folder. The path is what the
 * server deduplicates on, so it leaves out the root folder's own name: the same
 * folder dropped again under another name is still recognised.
 */

export type DroppedFile = { file: File, path: string }

export type FolderGroup = { folder: string, items: DroppedFile[] }

export type FolderAnalysis = {
    groups: FolderGroup[]
    tooLarge: DroppedFile[]
    ignoredCount: number
}

/** Mirrors the `max:10240` rule of StoreAdminPicturesRequest. */
export const MAX_FILE_BYTES = 10 * 1024 * 1024

const ACCEPTED_TYPES = ["image/jpeg", "image/png"]
const ACCEPTED_EXTENSION = /\.(jpe?g|png)$/i

const byPath = (a: DroppedFile, b: DroppedFile) => a.path.localeCompare(b.path, undefined, { numeric: true })

function stripRoot(path: string): string {
    const segments = path.split("/").filter(Boolean)

    return (segments.length > 1 ? segments.slice(1) : segments).join("/")
}

/**
 * Chrome hands a directory's content over in batches of at most 100 entries:
 * reading once would silently drop the rest of a large folder.
 */
async function readAllEntries(reader: FileSystemDirectoryReader): Promise<FileSystemEntry[]> {
    const entries: FileSystemEntry[] = []

    for (;;) {
        const batch = await new Promise<FileSystemEntry[]>((resolve, reject) => reader.readEntries(resolve, reject))

        if (batch.length === 0) {
            return entries
        }

        entries.push(...batch)
    }
}

async function walk(entry: FileSystemEntry, files: DroppedFile[]): Promise<void> {
    if (entry.isFile) {
        const file = await new Promise<File>((resolve, reject) => (entry as FileSystemFileEntry).file(resolve, reject))
        files.push({ file, path: stripRoot(entry.fullPath) })

        return
    }

    if (entry.isDirectory) {
        for (const child of await readAllEntries((entry as FileSystemDirectoryEntry).createReader())) {
            await walk(child, files)
        }
    }
}

/**
 * Must be called synchronously from the drop handler: the browser empties the
 * DataTransfer as soon as the handler yields, so the entries are taken before
 * the first await.
 */
export async function collectFromDataTransfer(dataTransfer: DataTransfer): Promise<DroppedFile[]> {
    const entries = Array.from(dataTransfer.items)
        .map((item) => item.webkitGetAsEntry())
        .filter((entry): entry is FileSystemEntry => entry !== null)

    // Two roots stripped of their names could yield the same paths, and the
    // server would take the second picture for a copy of the first.
    if (entries.filter((entry) => entry.isDirectory).length > 1) {
        throw new Error("Drop a single folder at a time.")
    }

    const files: DroppedFile[] = []

    for (const entry of entries) {
        await walk(entry, files)
    }

    return files
}

export function collectFromInput(files: FileList): DroppedFile[] {
    return Array.from(files).map((file) => ({ file, path: stripRoot(file.webkitRelativePath || file.name) }))
}

export function analyseFolder(files: DroppedFile[]): FolderAnalysis {
    const groups = new Map<string, DroppedFile[]>()
    const tooLarge: DroppedFile[] = []
    let ignoredCount = 0

    for (const item of files) {
        const segments = item.path.split("/")
        const name = segments[segments.length - 1]
        const isHidden = segments.some((segment) => segment.startsWith("."))
        const isImage = ACCEPTED_TYPES.includes(item.file.type) || ACCEPTED_EXTENSION.test(name)

        if (isHidden || name === "Thumbs.db" || !isImage) {
            ignoredCount++
            continue
        }

        if (item.file.size > MAX_FILE_BYTES) {
            tooLarge.push(item)
            continue
        }

        const folder = segments.slice(0, -1).join("/")
        groups.set(folder, [...(groups.get(folder) ?? []), item])
    }

    return {
        groups: Array.from(groups, ([folder, items]) => ({ folder, items: items.sort(byPath) }))
            .sort((a, b) => a.folder.localeCompare(b.folder)),
        tooLarge,
        ignoredCount,
    }
}
```

- [ ] **Step 2 : `upload-queue.ts`**

```ts
import type { DroppedFile } from "@/lib/folder-drop"

/**
 * Sends a folder's pictures one request each, a few at a time. Network errors,
 * 5xx and 429 are retried with a growing delay; any other refusal fails the
 * picture alone, except an expired session, which stops the whole queue since
 * every following request would be refused the same way.
 */

export type UploadOutcome = "created" | "skipped" | "failed"

export class SessionExpiredError extends Error {
    constructor() {
        super("Session expired, log in again then drop the folder again.")
    }
}

const CONCURRENCY = 3
const RETRY_DELAYS_MS = [1_000, 3_000, 9_000]

/**
 * Laravel refreshes the XSRF-TOKEN cookie on every response, so it is read
 * per request rather than once at page load.
 */
export function xsrfHeaders(): Record<string, string> {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)

    return {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
        "X-XSRF-TOKEN": match ? decodeURIComponent(match[1]) : "",
    }
}

const sleep = (ms: number, signal: AbortSignal) =>
    new Promise<void>((resolve, reject) => {
        const timer = setTimeout(resolve, ms)
        signal.addEventListener("abort", () => {
            clearTimeout(timer)
            reject(signal.reason)
        }, { once: true })
    })

const isRetryable = (status: number) => status === 429 || status >= 500

async function upload(url: string, item: DroppedFile, signal: AbortSignal): Promise<"created" | "skipped"> {
    for (let attempt = 0; ; attempt++) {
        const body = new FormData()
        body.append("photos[]", item.file)
        body.append("paths[]", item.path)

        let response: Response | null = null

        try {
            response = await fetch(url, { method: "POST", body, signal, headers: xsrfHeaders() })
        } catch (error) {
            if (signal.aborted) {
                throw error
            }
        }

        if (response?.ok) {
            const { created } = (await response.json()) as { created: number }

            return created > 0 ? "created" : "skipped"
        }

        if (response && (response.status === 401 || response.status === 419)) {
            throw new SessionExpiredError()
        }

        if ((response && !isRetryable(response.status)) || attempt >= RETRY_DELAYS_MS.length) {
            throw new Error(response ? `HTTP ${response.status}` : "Network error")
        }

        await sleep(RETRY_DELAYS_MS[attempt], signal)
    }
}

export async function runUploadQueue(
    url: string,
    items: DroppedFile[],
    signal: AbortSignal,
    onSettled: (item: DroppedFile, outcome: UploadOutcome) => void,
): Promise<void> {
    let next = 0
    let sessionExpired = false

    const worker = async () => {
        while (next < items.length && !signal.aborted && !sessionExpired) {
            const item = items[next++]

            try {
                onSettled(item, await upload(url, item, signal))
            } catch (error) {
                if (signal.aborted) {
                    return
                }

                if (error instanceof SessionExpiredError) {
                    sessionExpired = true

                    return
                }

                onSettled(item, "failed")
            }
        }
    }

    await Promise.all(Array.from({ length: CONCURRENCY }, worker))

    if (sessionExpired) {
        throw new SessionExpiredError()
    }
}
```

- [ ] **Step 3 : Typage**

Run : `vendor/bin/sail npx tsc --noEmit -p tsconfig.json 2>&1 | grep -E "lib/(folder-drop|upload-queue)" ; echo "exit: done"`
Expected : aucune ligne mentionnant ces deux fichiers (d'éventuelles erreurs préexistantes ailleurs ne bloquent pas).

- [ ] **Step 4 : Commit**

```bash
git add resources/js/lib/folder-drop.ts resources/js/lib/upload-queue.ts
git commit -m "feat(transfers): parcours de dossier et file d'envoi côté front"
```

---

### Task 5 : composant de dépôt, écran Transfers et sidebar

**Files:**
- Create: `resources/js/components/transfers/folder-upload.tsx`
- Create (ou remplacer le fichier minimal de la Task 2) : `resources/js/pages/admin/transfers/index.tsx`
- Modify: `resources/js/components/app-sidebar.tsx`
- Test: `tests/Web/AdminTransfersTest.php`

**Interfaces:**
- Consumes : `collectFromDataTransfer`, `collectFromInput`, `analyseFolder`, `DroppedFile`, `FolderAnalysis` (Task 4) ; `runUploadQueue`, `SessionExpiredError`, `xsrfHeaders` (Task 4) ; routes `admin.transfers.store|destroy`, `party.dashboard.pictures.store` (Tasks 2-3) ; props `rows` de l'index (Task 2).
- Produces : `FolderUpload` props `{ resolveUploadUrl: () => Promise<string>, disabled?: boolean, onBusyChange: (busy: boolean) => void, onFinished: () => void }`. Ids DOM stables pour les tests : `#transfer-name`, `#transfer-expire-at`, `#transfer-folder`.

- [ ] **Step 1 : Test navigateur qui échoue**

`tests/Web/AdminTransfersTest.php` :

```php
<?php

use App\Models\Party;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('lists transfers with their link', function () {
    $transfer = Party::factory()->transfer()->create(['display_name' => 'Mariage Julie']);

    $page = browserPage(fn () => visit(route('admin.transfers.index')));

    $page->assertSee('Mariage Julie')
        ->assertSee('Copy link')
        ->assertNoJavaScriptErrors();
});

it('offers a folder picker in the new transfer dialog and waits for a name and a folder', function () {
    $page = browserPage(fn () => visit(route('admin.transfers.index')));

    $page->click('New transfer')
        ->assertVisible('#transfer-name')
        ->assertAttribute('#transfer-expire-at', 'type', 'datetime-local')
        ->assertPresent('#transfer-folder[webkitdirectory]')
        ->assertSee('Drop the photobooth folder here')
        ->assertNoJavaScriptErrors();
});

it('reaches the transfers screen from the sidebar', function () {
    $page = browserPage(fn () => visit(route('admin.parties.index')));

    $page->click('Transfers')
        ->assertSeeIn('main > div > h1', 'Transfers')
        ->assertNoJavaScriptErrors();
});
```

Run : `vendor/bin/sail artisan test --compact --filter=AdminTransfersTest tests/Web/AdminTransfersTest.php`
Expected : FAIL (page vide ou absente).

- [ ] **Step 2 : Composant `folder-upload.tsx`**

```tsx
import { DragEvent, useEffect, useRef, useState } from "react"

import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import { Label } from "@/components/ui/label"
import {
    analyseFolder,
    collectFromDataTransfer,
    collectFromInput,
    type DroppedFile,
    type FolderAnalysis,
} from "@/lib/folder-drop"
import { runUploadQueue, SessionExpiredError } from "@/lib/upload-queue"

type FolderUploadProps = {
    /** Endpoint the pictures go to, creating the transfer first when needed. Resolved once. */
    resolveUploadUrl: () => Promise<string>
    /** Holds the send button back while the surrounding form is incomplete. */
    disabled?: boolean
    onBusyChange: (busy: boolean) => void
    onFinished: () => void
}

type Status = "idle" | "review" | "uploading" | "done" | "expired"

const formatBytes = (bytes: number) => `${(bytes / 1024 / 1024).toFixed(0)} MB`

export function FolderUpload({ resolveUploadUrl, disabled = false, onBusyChange, onFinished }: FolderUploadProps) {
    const [status, setStatus] = useState<Status>("idle")
    const [analysis, setAnalysis] = useState<FolderAnalysis | null>(null)
    const [excluded, setExcluded] = useState<Set<string>>(new Set())
    const [error, setError] = useState<string | null>(null)
    const [total, setTotal] = useState(0)
    const [counts, setCounts] = useState({ created: 0, skipped: 0, bytes: 0 })
    const [failed, setFailed] = useState<DroppedFile[]>([])
    const abortRef = useRef<AbortController | null>(null)
    const uploadUrlRef = useRef<string | null>(null)

    useEffect(() => onBusyChange(status === "uploading"), [status, onBusyChange])

    // Closing the dialog unmounts the component: stop the requests with it.
    useEffect(() => () => abortRef.current?.abort(), [])

    useEffect(() => {
        if (status !== "uploading") {
            return
        }

        const warn = (event: BeforeUnloadEvent) => event.preventDefault()
        window.addEventListener("beforeunload", warn)

        let lock: WakeLockSentinel | null = null
        let released = false
        navigator.wakeLock?.request("screen")
            .then((sentinel) => {
                lock = sentinel
                if (released) {
                    void sentinel.release()
                }
            })
            .catch(() => {})

        return () => {
            released = true
            window.removeEventListener("beforeunload", warn)
            void lock?.release()
        }
    }, [status])

    const load = (files: DroppedFile[]) => {
        setError(null)
        setAnalysis(analyseFolder(files))
        setExcluded(new Set())
        setStatus("review")
    }

    const onDrop = (event: DragEvent<HTMLDivElement>) => {
        event.preventDefault()
        collectFromDataTransfer(event.dataTransfer).then(load, (reason: Error) => setError(reason.message))
    }

    const selected = analysis?.groups.filter((group) => !excluded.has(group.folder)).flatMap((group) => group.items) ?? []

    const send = async (items: DroppedFile[]) => {
        setError(null)

        try {
            uploadUrlRef.current ??= await resolveUploadUrl()
        } catch (reason) {
            setError((reason as Error).message)

            return
        }

        const controller = new AbortController()
        const failures: DroppedFile[] = []
        abortRef.current = controller
        setTotal(items.length)
        setCounts({ created: 0, skipped: 0, bytes: 0 })
        setFailed([])
        setStatus("uploading")

        try {
            await runUploadQueue(uploadUrlRef.current, items, controller.signal, (item, outcome) => {
                if (outcome === "failed") {
                    failures.push(item)
                    setFailed([...failures])

                    return
                }

                setCounts((current) => ({
                    ...current,
                    [outcome]: current[outcome] + 1,
                    bytes: current.bytes + (outcome === "created" ? item.file.size : 0),
                }))
            })
            setStatus("done")
        } catch (reason) {
            if (reason instanceof SessionExpiredError) {
                setError(reason.message)
                setStatus("expired")

                return
            }

            throw reason
        } finally {
            onFinished()
        }
    }

    const settled = counts.created + counts.skipped + failed.length

    if (status === "idle") {
        return (
            <div
                onDragOver={(event) => event.preventDefault()}
                onDrop={onDrop}
                className="flex flex-col items-center gap-2 rounded-lg border border-dashed p-6 text-center text-sm"
            >
                <p>Drop the photobooth folder here</p>
                <Label htmlFor="transfer-folder" className="cursor-pointer underline">
                    or choose a folder
                </Label>
                <input
                    id="transfer-folder"
                    type="file"
                    multiple
                    className="sr-only"
                    {...{ webkitdirectory: "" }}
                    onChange={(event) => event.target.files && load(collectFromInput(event.target.files))}
                />
                {error && <p className="text-destructive">{error}</p>}
            </div>
        )
    }

    if (status === "review" && analysis) {
        return (
            <div className="space-y-3 text-sm">
                <ul className="space-y-1">
                    {analysis.groups.map((group) => (
                        <li key={group.folder} className="flex items-center gap-2">
                            <Checkbox
                                id={`transfer-group-${group.folder || "root"}`}
                                checked={!excluded.has(group.folder)}
                                onCheckedChange={(checked) => {
                                    const next = new Set(excluded)
                                    if (checked === true) {
                                        next.delete(group.folder)
                                    } else {
                                        next.add(group.folder)
                                    }
                                    setExcluded(next)
                                }}
                            />
                            <Label htmlFor={`transfer-group-${group.folder || "root"}`} className="font-normal">
                                {group.folder === "" ? "/" : `${group.folder}/`} — {group.items.length} photos
                            </Label>
                        </li>
                    ))}
                </ul>
                {analysis.tooLarge.length > 0 && (
                    <p className="text-destructive">
                        Over 10 MB, not sent: {analysis.tooLarge.map((item) => item.path).join(", ")}
                    </p>
                )}
                {analysis.ignoredCount > 0 && (
                    <p className="text-muted-foreground">{analysis.ignoredCount} non-image files ignored.</p>
                )}
                {error && <p className="text-destructive">{error}</p>}
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="outline" onClick={() => setStatus("idle")}>
                        Change folder
                    </Button>
                    <Button type="button" disabled={disabled || selected.length === 0} onClick={() => void send(selected)}>
                        Send {selected.length} photos
                    </Button>
                </div>
            </div>
        )
    }

    return (
        <div className="space-y-3 text-sm">
            <progress value={settled} max={total} className="w-full" />
            <p>
                {settled} / {total} — {formatBytes(counts.bytes)} sent, {counts.skipped} already online,{" "}
                {failed.length} failed
            </p>
            {error && <p className="text-destructive">{error}</p>}
            {status === "done" && failed.length > 0 && (
                <p className="text-destructive">
                    Failed: {failed.slice(0, 10).map((item) => item.path).join(", ")}
                    {failed.length > 10 && ` and ${failed.length - 10} more`}
                </p>
            )}
            <div className="flex justify-end gap-2">
                {status === "uploading" && (
                    <Button type="button" variant="outline" onClick={() => abortRef.current?.abort()}>
                        Cancel
                    </Button>
                )}
                {status === "done" && failed.length > 0 && (
                    <Button type="button" onClick={() => void send(failed)}>
                        Retry failed
                    </Button>
                )}
            </div>
        </div>
    )
}
```

- [ ] **Step 3 : Page `admin/transfers/index.tsx`**

```tsx
import { useCallback, useState } from "react"
import { router } from "@inertiajs/react"

import { AdminLayout, type SidebarData } from "@/components/admin-layout"
import { DataTable, dataColumn, type RowAction } from "@/components/data-table"
import { FormField } from "@/components/form-field"
import { FolderUpload } from "@/components/transfers/folder-upload"
import { Button } from "@/components/ui/button"
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { fromDateTimeLocalValue } from "@/lib/datetime"
import { xsrfHeaders } from "@/lib/upload-queue"

type TransferRow = {
    id: number
    name: string
    display_name: string | null
    pictures_count: number
    expire_at: string | null
    created_at: string | null
    public_url: string
}

type PageProps = SidebarData & { rows: TransferRow[] }

function CopyLinkButton({ url }: { url: string }) {
    const [copied, setCopied] = useState(false)

    return (
        <Button
            size="sm"
            variant="outline"
            onClick={(event) => {
                event.stopPropagation()
                void navigator.clipboard.writeText(url)
                setCopied(true)
            }}
        >
            {copied ? "Copied" : "Copy link"}
        </Button>
    )
}

const columns = [
    dataColumn<TransferRow>("display_name", "Name"),
    dataColumn<TransferRow>("pictures_count", "Pictures"),
    dataColumn<TransferRow>("created_at", "Created", { type: "datetime" }),
    dataColumn<TransferRow>("expire_at", "Expires", { type: "datetime" }),
    dataColumn<TransferRow>("public_url", "Link", { cell: (transfer) => <CopyLinkButton url={transfer.public_url} /> }),
]

/** "new" opens the creation dialog, a row opens the completion of that transfer. */
type DialogState = { transfer: TransferRow | null } | null

export default function TransfersIndex({ rows, ...sidebar }: PageProps) {
    const [dialog, setDialog] = useState<DialogState>(null)
    const [busy, setBusy] = useState(false)
    const [name, setName] = useState("")
    const [expireAt, setExpireAt] = useState("")
    const [createdUrl, setCreatedUrl] = useState<string | null>(null)
    const transfer = dialog?.transfer ?? null

    const open = (state: DialogState) => {
        setName("")
        setExpireAt("")
        setCreatedUrl(null)
        setDialog(state)
    }

    const createTransfer = async (): Promise<string> => {
        const response = await fetch(route("admin.transfers.store"), {
            method: "POST",
            headers: { ...xsrfHeaders(), "Content-Type": "application/json" },
            body: JSON.stringify({ display_name: name, expire_at: fromDateTimeLocalValue(expireAt) }),
        })
        const body = await response.json().catch(() => null)

        if (!response.ok) {
            throw new Error(body?.message ?? `HTTP ${response.status}`)
        }

        setCreatedUrl(body.public_url)

        return body.upload_url
    }

    const reloadRows = useCallback(() => router.reload({ only: ["rows"] }), [])

    const rowActions: RowAction<TransferRow>[] = [
        { label: "Open gallery", onClick: (row) => window.open(row.public_url, "_blank", "noopener") },
        { label: "Complete", onClick: (row) => open({ transfer: row }) },
        {
            label: "Delete",
            variant: "destructive",
            onClick: (row) => {
                if (window.confirm("Delete this transfer and its pictures?")) {
                    router.delete(route("admin.transfers.destroy", row.name))
                }
            },
        },
    ]

    return (
        <AdminLayout title="Administration / Transfers" sidebar={sidebar}>
            <DataTable
                data={rows}
                columns={columns}
                emptyMessage="No transfers yet."
                rowActions={rowActions}
                toolbarAction={
                    <Button size="sm" onClick={() => open({ transfer: null })}>
                        New transfer
                    </Button>
                }
            />

            <Dialog open={dialog !== null} onOpenChange={(isOpen) => !isOpen && !busy && setDialog(null)}>
                <DialogContent className="max-h-[85vh] max-w-xl overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>{transfer ? `Complete ${transfer.display_name}` : "New transfer"}</DialogTitle>
                        <DialogDescription>
                            {transfer
                                ? "Drop the same folder again: pictures already online are skipped."
                                : "Name the transfer, drop the photobooth folder, then send the link to the client."}
                        </DialogDescription>
                    </DialogHeader>

                    {!transfer && (
                        <div className="space-y-4">
                            <FormField id="transfer-name" label="Name">
                                <Input
                                    id="transfer-name"
                                    value={name}
                                    disabled={createdUrl !== null}
                                    onChange={(event) => setName(event.target.value)}
                                    required
                                />
                            </FormField>
                            <FormField
                                id="transfer-expire-at"
                                label="Expiry date (optional)"
                                hint="Leave empty to keep the gallery available indefinitely."
                            >
                                <Input
                                    id="transfer-expire-at"
                                    type="datetime-local"
                                    value={expireAt}
                                    disabled={createdUrl !== null}
                                    onChange={(event) => setExpireAt(event.target.value)}
                                />
                            </FormField>
                        </div>
                    )}

                    <FolderUpload
                        key={transfer?.id ?? "new"}
                        resolveUploadUrl={
                            transfer
                                ? async () => route("party.dashboard.pictures.store", transfer.name)
                                : createTransfer
                        }
                        disabled={!transfer && name.trim() === ""}
                        onBusyChange={setBusy}
                        onFinished={reloadRows}
                    />

                    {createdUrl && !busy && (
                        <FormField id="transfer-link" label="Link to send">
                            <div className="flex gap-2">
                                <Input id="transfer-link" readOnly value={createdUrl} onFocus={(event) => event.target.select()} />
                                <CopyLinkButton url={createdUrl} />
                            </div>
                        </FormField>
                    )}
                </DialogContent>
            </Dialog>
        </AdminLayout>
    )
}
```

Avant d'écrire, vérifier dans `resources/js/components/data-table/columns.tsx` que `dataColumn` accepte bien `cell` et `type: "datetime"` comme dans `admin/parties/index.tsx` (c'est le cas pour `pictures_count` et `expire_at` là-bas), et dans `form-field.tsx` que `error` est optionnel.

- [ ] **Step 4 : Sidebar**

Dans `resources/js/components/app-sidebar.tsx`, ajouter `SendIcon` à l'import `lucide-react` et, entre les items Parties et Users :

```tsx
            <SidebarMenuItem>
              <SidebarMenuButton asChild tooltip="Transfers">
                <Link href={route("admin.transfers.index")}>
                  <SendIcon />
                  <span>Transfers</span>
                </Link>
              </SidebarMenuButton>
            </SidebarMenuItem>
```

(ce fichier est en indentation 2 espaces : la conserver.)

- [ ] **Step 5 : Build et tests**

Run : `vendor/bin/sail npm run build`
Expected : build réussi.

Run : `vendor/bin/sail npx tsc --noEmit -p tsconfig.json 2>&1 | grep -E "transfers|folder-upload" ; echo done`
Expected : aucune ligne.

Run : `vendor/bin/sail artisan test --compact tests/Web/AdminTransfersTest.php tests/Feature/AdminTransfersTest.php`
Expected : PASS.

- [ ] **Step 6 : Commit**

```bash
git add resources/js/components/transfers/folder-upload.tsx resources/js/pages/admin/transfers/index.tsx resources/js/components/app-sidebar.tsx tests/Web/AdminTransfersTest.php
git commit -m "feat(transfers): écran admin Transfers et dépôt de dossier"
```

---

### Task 6 : vérification manuelle sur un vrai dossier de borne

Pas de code. À faire dans Chrome puis Firefox, `vendor/bin/sail composer run dev` lancé, connecté à l'admin.

- [ ] **Step 1 : Dossier volumineux** — glisser-déposer un dossier de plus de 100 photos (idéalement un vrai dossier pibooth). Expected : le récapitulatif compte **toutes** les photos (preuve de la lecture par lots), les sous-dossiers apparaissent séparément, `.DS_Store` et autres n'apparaissent que dans « ignored ». Noter l'arborescence pibooth réelle (sous-dossier `raw/` ou non) dans la PR.
- [ ] **Step 2 : Sélecteur** — même dossier via « or choose a folder ». Expected : mêmes comptes.
- [ ] **Step 3 : Envoi complet** — nom saisi, « Send N photos ». Expected : progression jusqu'à N / N, lien affiché, la galerie `/transfers/<uuid>` montre la dernière photo de la soirée en tête, le tableau compte N photos.
- [ ] **Step 4 : Reprise** — « Complete » sur ce transfert, redéposer le même dossier **renommé**. Expected : `0 sent, N already online`, aucune photo en double dans la galerie.
- [ ] **Step 5 : Coupure** — pendant un envoi, couper le réseau (DevTools → Offline) quelques secondes puis le rétablir. Expected : les fichiers en cours finissent après relance ; si la coupure dépasse ~13 s, ils passent en échec et « Retry failed » les renvoie.
- [ ] **Step 6 : Session expirée** — pendant un envoi, supprimer le cookie de session dans DevTools. Expected : la file s'arrête presque aussitôt avec le message de session expirée, sans enchaîner les relances.
- [ ] **Step 7 : Garde-fous** — pendant un envoi, Échap ou clic hors de la boîte : elle reste ouverte ; fermer l'onglet : le navigateur demande confirmation ; « Cancel » : l'envoi s'arrête, les photos déjà parties restent.
- [ ] **Step 8 : Deux dossiers déposés ensemble** — Expected : « Drop a single folder at a time. », rien n'est envoyé.
- [ ] **Step 9 : Proxy de production** — vérifier `client_max_body_size` (ou équivalent) du proxy devant l'app : au moins 11 Mo.
- [ ] **Step 10 : Suite complète**

Run : `vendor/bin/sail artisan test --compact`
Expected : tout passe.
