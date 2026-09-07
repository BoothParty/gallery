# Gallery Auto-Refresh Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make the public gallery pick up new pictures, deletions and freshly generated thumbnails on its own, without the visitor doing anything and without moving the page under their fingers.

**Architecture:** A cached aggregate — `{count, last_change}` — is the whole signal, invalidated by Eloquent events on `Picture` rather than by a TTL. A stateless probe endpoint serves it; a client hook polls it on an adaptive cadence and compares against a baseline the page ships as a prop. When the signal moves, the page either reloads silently or offers a pill, depending on whether the visitor is busy.

**Tech Stack:** Laravel 12, Inertia 2 + React 19, Tailwind 4, Pest 4 (feature + browser), MySQL, database cache and session drivers.

**Spec:** `docs/superpowers/specs/2026-09-06-rafraichissement-automatique-galerie.md`

## Global Constraints

- **Every command runs through Sail.** `vendor/bin/sail artisan …`, `vendor/bin/sail bin pint …`, `vendor/bin/sail npm …`. Never bare `php`/`artisan`/`npm`.
- **Run tests narrowly:** `vendor/bin/sail artisan test --compact --filter=…`. Full suite only at Task 7.
- **Public components live in `resources/js/components/party/`** and may use **only** Tailwind utilities and the project's CSS custom properties. **No shadcn, no Radix, no `@/components/ui/*`** — those compile into `admin.css` only, and a public component importing them silently loses its styles.
- **Available public design tokens:** `bg-surface`, `bg-placeholder`, `bg-ink`, `text-ink`, `text-cream`, `text-muted`, `text-subtle`, `border-line`, `font-sans`, `font-serif`.
- **Code style** under `resources/js/components/party/`, `resources/js/pages/party/`, `resources/js/hooks/`: **single quotes, semicolons, 4-space indent, print width 120**. Prettier is **not** a project dependency — do not run it without those flags, and never `npx prettier` with defaults (it rewrites the whole file to double quotes).
- **PHP:** explicit return types everywhere, curly braces always, PHPDoc blocks rather than inline comments, constructor property promotion, `Model::query()` over `DB::`.
- **After touching any PHP file:** `vendor/bin/sail bin pint --dirty --format agent`.
- **`tests/Web/` browser tests flake on 5 s timeouts.** Re-run once before investigating. Use the `browserPage()` helper (`tests/BrowserPage.php`), never a bare `visit()` when clicking.
- **Visitor-facing copy is in French.** Exact strings are given verbatim in the tasks; do not paraphrase them, the tests assert on them.
- Cadence defaults, in **seconds**: hot **10**, warm **30**, cold **60**, warm threshold **120**, cold threshold **300**, cache guard TTL **600**. The client converts to milliseconds.
- Throttle ceiling for `party-state`: **600 per minute**, keyed `ip:party`.
- Silent-refresh scroll limit: **200 px**.

---

## File Structure

**Created**

| File | Responsibility |
| --- | --- |
| `database/migrations/2026_09_06_100000_add_party_updated_index_to_pictures_table.php` | Composite index `(party_id, updated_at)` |
| `app/Services/PartyPicturesState.php` | The aggregate, its cache and its invalidation |
| `app/Http/Controllers/Api/PartyStateController.php` | The probe endpoint |
| `resources/js/hooks/use-gallery-changes.ts` | Polling cadence and change detection |
| `resources/js/components/party/new-pictures-pill.tsx` | The pill shown when the visitor is busy |
| `tests/Feature/PartyPicturesStateTest.php` | The service and its invalidation |
| `tests/Feature/PartyStateTest.php` | The endpoint, its shape and its throttle |
| `tests/Web/GalleryAutoRefreshTest.php` | The behaviour in a real browser |

**Modified**

| File | Change |
| --- | --- |
| `config/party.php` | The `refresh` block |
| `app/Models/Picture.php` | `saved` / `deleted` invalidation hooks |
| `app/Http/Controllers/PartyController.php` | `picturesState` and `refresh` props |
| `app/Providers/AppServiceProvider.php` | `party-state` limiter |
| `routes/api.php` | The probe route |
| `resources/js/hooks/use-photoswipe.ts` | Return whether the lightbox is open |
| `resources/js/pages/party/gallery.tsx` | Wire the hook, the pill and the reload; empty-state copy |
| `resources/js/components/party/gallery-sticky-bar.tsx` | `data-gallery-select` test handle |
| `tests/Feature/GalleryTest.php` | The two new props |

---

## Task 1: The signal and its cache

**Files:**
- Create: `database/migrations/2026_09_06_100000_add_party_updated_index_to_pictures_table.php`
- Create: `app/Services/PartyPicturesState.php`
- Modify: `config/party.php`
- Modify: `app/Models/Picture.php`
- Test: `tests/Feature/PartyPicturesStateTest.php`

**Interfaces:**
- Consumes: `App\Models\Party::hidePictures(): bool`, `App\Models\Party::pictures(): HasMany`.
- Produces: `App\Services\PartyPicturesState` with `for(Party $party): array{count: int, last_change: string|null}`, `forget(int $partyId): void`, and `static key(int $partyId): string`. Tasks 2 and 3 resolve it from the container.

- [ ] **Step 1: Add the `refresh` block to the config**

Append inside the array returned by `config/party.php`, after the `download` block:

```php
    /*
    |--------------------------------------------------------------------------
    | Gallery Auto-Refresh
    |--------------------------------------------------------------------------
    |
    | Cadence, in seconds, at which an open gallery polls for changes. The
    | gallery slows down as the party quiets: `hot` while a change is fresher
    | than `warm_after`, `warm` up to `cold_after`, `cold` beyond. An empty
    | gallery stays hot, it being the case the whole feature exists for.
    |
    | `cache_ttl` is only a guard. The aggregate the poll reads is invalidated
    | by picture writes, so the TTL exists to bound the damage of a future
    | write that skips the model events, not to keep the value fresh.
    |
    */

    'refresh' => [
        'hot' => (int) env('PARTY_REFRESH_HOT', 10),
        'warm' => (int) env('PARTY_REFRESH_WARM', 30),
        'cold' => (int) env('PARTY_REFRESH_COLD', 60),
        'warm_after' => (int) env('PARTY_REFRESH_WARM_AFTER', 120),
        'cold_after' => (int) env('PARTY_REFRESH_COLD_AFTER', 300),
        'cache_ttl' => (int) env('PARTY_REFRESH_CACHE_TTL', 600),
    ],
```

- [ ] **Step 2: Write the failing tests**

Create `tests/Feature/PartyPicturesStateTest.php`:

```php
<?php

use App\Models\Party;
use App\Models\Picture;
use App\Services\PartyPicturesState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
});

function picturesState(Party $party): array
{
    return app(PartyPicturesState::class)->for($party);
}

it('reports an empty gallery as zero without a last change', function () {
    $party = Party::factory()->create();

    expect(picturesState($party))->toBe(['count' => 0, 'last_change' => null]);
});

it('counts the pictures and reports the most recent change', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(3)->for($party)->create();
    $latest = Picture::factory()->for($party)->create(['updated_at' => now()->addMinute()]);

    $state = picturesState($party);

    expect($state['count'])->toBe(4)
        ->and($state['last_change'])->toBe($latest->updated_at->toIso8601String());
});

it('ignores the pictures of other parties', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(2)->for($party)->create();
    Picture::factory()->count(5)->for(Party::factory()->create())->create();

    expect(picturesState($party)['count'])->toBe(2);
});

it('drops the count when a picture is deleted', function () {
    $party = Party::factory()->create();
    $pictures = Picture::factory()->count(3)->for($party)->create();

    expect(picturesState($party)['count'])->toBe(3);

    $pictures->first()->delete();

    expect(picturesState($party)['count'])->toBe(2);
});

it('moves the last change when a thumbnail is written, without touching the count', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create(['thumbnail_path' => null]);
    $before = picturesState($party);

    $this->travel(1)->minutes();
    $picture->update(['thumbnail_path' => 'thumbnails/x.jpg']);

    $after = picturesState($party);

    expect($after['count'])->toBe($before['count'])
        ->and($after['last_change'])->not->toBe($before['last_change']);
});

it('reports zeros for an expired party even when it holds pictures', function () {
    $party = Party::factory()->expired()->create();
    Picture::factory()->count(4)->for($party)->create();

    expect(picturesState($party))->toBe(['count' => 0, 'last_change' => null]);
});

it('serves the aggregate from cache between writes', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(2)->for($party)->create();

    picturesState($party);

    Picture::withoutEvents(fn () => Picture::factory()->for($party)->create());

    expect(picturesState($party)['count'])->toBe(2);
});

it('invalidates the cache when a picture is created', function () {
    $party = Party::factory()->create();
    picturesState($party);

    Picture::factory()->for($party)->create();

    expect(picturesState($party)['count'])->toBe(1);
});

it('invalidates the cache when the dashboard deletes pictures in bulk', function () {
    $party = Party::factory()->create();
    $pictures = Picture::factory()->count(3)->for($party)->create();
    picturesState($party);

    $party->pictures()->whereIn('id', $pictures->take(2)->pluck('id'))->get()->each->delete();

    expect(picturesState($party)['count'])->toBe(1);
});
```

- [ ] **Step 3: Run the tests to verify they fail**

Run: `vendor/bin/sail artisan test --compact --filter=PartyPicturesState`
Expected: FAIL — `Target class [App\Services\PartyPicturesState] does not exist.`

- [ ] **Step 4: Write the migration**

Create `database/migrations/2026_09_06_100000_add_party_updated_index_to_pictures_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The gallery polls an aggregate of `COUNT(*)` and `MAX(updated_at)` per
     * party. The foreign key index already covers the count; the composite
     * one lets the maximum resolve without touching a single row.
     */
    public function up(): void
    {
        Schema::table('pictures', function (Blueprint $table) {
            $table->index(['party_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::table('pictures', function (Blueprint $table) {
            $table->dropIndex(['party_id', 'updated_at']);
        });
    }
};
```

- [ ] **Step 5: Write the service**

Create `app/Services/PartyPicturesState.php`:

```php
<?php

namespace App\Services;

use App\Models\Party;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class PartyPicturesState
{
    /**
     * The signal an open gallery polls to know whether its content moved.
     *
     * `count` catches an arrival and a deletion, `last_change` catches an
     * arrival and a thumbnail finishing its job. Neither is enough on its
     * own: a deletion leaves `MAX(updated_at)` where it was, and a deletion
     * followed by an arrival leaves the count where it was.
     *
     * An expired party always reads as empty. The page shows nothing in that
     * case, and a client left open across the expiry date must not start
     * announcing pictures it will never display.
     *
     * @return array{count: int, last_change: string|null}
     */
    public function for(Party $party): array
    {
        if ($party->hidePictures()) {
            return ['count' => 0, 'last_change' => null];
        }

        return Cache::remember(
            self::key($party->id),
            (int) config('party.refresh.cache_ttl'),
            fn (): array => $this->compute($party),
        );
    }

    /**
     * Drop the memoised aggregate. Called from the `Picture` model events, so
     * that the cache expires on writes rather than on a clock.
     */
    public function forget(int $partyId): void
    {
        Cache::forget(self::key($partyId));
    }

    public static function key(int $partyId): string
    {
        return "party:{$partyId}:pictures-state";
    }

    /**
     * @return array{count: int, last_change: string|null}
     */
    private function compute(Party $party): array
    {
        $lastChange = $party->pictures()->max('updated_at');

        return [
            'count' => $party->pictures()->count(),
            'last_change' => $lastChange === null ? null : Carbon::parse($lastChange)->toIso8601String(),
        ];
    }
}
```

- [ ] **Step 6: Hook the invalidation onto the model**

In `app/Models/Picture.php`, add `use App\Services\PartyPicturesState;` to the imports and extend the existing `booting()` method so it reads:

```php
    protected static function booting(): void
    {
        static::creating(function (Picture $picture): void {
            $picture->path ??= 'pictures/'.Str::uuid().'.jpg';
        });

        static::saved(function (Picture $picture): void {
            app(PartyPicturesState::class)->forget($picture->party_id);
        });

        static::deleted(function (Picture $picture): void {
            $paths = array_filter([$picture->path, $picture->thumbnail_path]);
            Storage::disk('s3')->delete($paths);

            app(PartyPicturesState::class)->forget($picture->party_id);
        });
    }
```

`saved` covers both creation and update, so the thumbnail job invalidates through the same hook as an arrival. Every write path in the application goes through a model instance — `$party->pictures()->create()` in both `Api\PictureController` and `Admin\GalleryController`, `$picture->update()` in `CreatePictureThumbnail`, `->get()->each->delete()` in `Admin\GalleryController::bulkDelete`, `$picture->delete()` in `Api\PictureController::destroy` — so the events see all of them. The model already depends on this contract to clean up S3.

- [ ] **Step 7: Run the migration and the tests**

Run:
```bash
vendor/bin/sail artisan migrate
vendor/bin/sail artisan test --compact --filter=PartyPicturesState
```
Expected: PASS, 9 tests.

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add config/party.php app/Services/PartyPicturesState.php app/Models/Picture.php \
    database/migrations/2026_09_06_100000_add_party_updated_index_to_pictures_table.php \
    tests/Feature/PartyPicturesStateTest.php
git commit -m "feat(gallery): aggregate the pictures state behind a write-invalidated cache"
```

---

## Task 2: The probe endpoint

**Files:**
- Create: `app/Http/Controllers/Api/PartyStateController.php`
- Modify: `routes/api.php`
- Modify: `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/PartyStateTest.php`

**Interfaces:**
- Consumes: `App\Services\PartyPicturesState::for()` from Task 1.
- Produces: route `api.parties.state` at `GET /api/parties/{party}/state`, returning `{"count": int, "last_change": string|null}`. Task 5's hook fetches this URL.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/PartyStateTest.php`:

```php
<?php

use App\Models\Party;
use App\Models\Picture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
});

it('returns the picture count and the last change', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create();

    $this->getJson(route('api.parties.state', $party))
        ->assertOk()
        ->assertExactJson([
            'count' => 1,
            'last_change' => $picture->updated_at->toIso8601String(),
        ]);
});

it('returns zeros for an empty gallery', function () {
    $party = Party::factory()->create();

    $this->getJson(route('api.parties.state', $party))
        ->assertOk()
        ->assertExactJson(['count' => 0, 'last_change' => null]);
});

it('returns zeros for an expired party', function () {
    $party = Party::factory()->expired()->create();
    Picture::factory()->count(3)->for($party)->create();

    $this->getJson(route('api.parties.state', $party))
        ->assertOk()
        ->assertExactJson(['count' => 0, 'last_change' => null]);
});

it('needs no authentication', function () {
    $party = Party::factory()->create();

    $this->getJson(route('api.parties.state', $party))->assertOk();
});

it('reflects a new picture on the next call', function () {
    $party = Party::factory()->create();

    $this->getJson(route('api.parties.state', $party))->assertJsonPath('count', 0);

    Picture::factory()->for($party)->create();

    $this->getJson(route('api.parties.state', $party))->assertJsonPath('count', 1);
});

it('throttles repeated state requests', function () {
    $party = Party::factory()->create();

    foreach (range(1, 600) as $ignored) {
        $this->getJson(route('api.parties.state', $party))->assertOk();
    }

    $this->getJson(route('api.parties.state', $party))->assertStatus(429);
});

it('keeps state throttling independent per party', function () {
    $throttledParty = Party::factory()->create();

    foreach (range(1, 600) as $ignored) {
        $this->getJson(route('api.parties.state', $throttledParty))->assertOk();
    }

    $this->getJson(route('api.parties.state', $throttledParty))->assertStatus(429);
    $this->getJson(route('api.parties.state', Party::factory()->create()))->assertOk();
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/sail artisan test --compact --filter=PartyStateTest`
Expected: FAIL — `Route [api.parties.state] not defined.`

- [ ] **Step 3: Write the controller**

Create `app/Http/Controllers/Api/PartyStateController.php`:

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Party;
use App\Services\PartyPicturesState;
use Illuminate\Http\JsonResponse;

class PartyStateController extends Controller
{
    /**
     * The signal an open gallery polls to know whether its content moved.
     */
    public function __invoke(Party $party, PartyPicturesState $partyPicturesState): JsonResponse
    {
        return response()->json($partyPicturesState->for($party));
    }
}
```

- [ ] **Step 4: Register the route**

In `routes/api.php`, add `use App\Http\Controllers\Api\PartyStateController;` to the imports and append, **outside** the `auth:sanctum` group:

```php
/**
 * The probe the public gallery polls for changes.
 *
 * It lives here rather than in `web.php` on purpose: the `web` group runs
 * `StartSession`, which writes the database-backed session on every request.
 * With a phone per guest polling all evening, those writes would cost far
 * more than the aggregate this endpoint takes care to cache. The `api` group
 * is stateless and already carries `SubstituteBindings`, which the route needs
 * to resolve `{party}` by its name.
 */
Route::get('parties/{party}/state', PartyStateController::class)
    ->middleware('throttle:party-state')
    ->name('api.parties.state');
```

- [ ] **Step 5: Register the limiter**

In `app/Providers/AppServiceProvider.php`, append inside `boot()`, after the `party-metadata` limiter:

```php
        /**
         * Every phone in the room polls this endpoint, so the ceiling is per
         * event rather than per device: eighty guests at the fastest cadence
         * spend 480 of it. Keyed like its neighbours, by address and party,
         * because the guests of one venue share a single wifi address.
         */
        RateLimiter::for('party-state', function (Request $request): Limit {
            $party = (string) $request->route('party');

            return Limit::perMinute(600)->by($request->ip().':'.$party);
        });
```

- [ ] **Step 6: Verify the route and run the tests**

Run:
```bash
vendor/bin/sail artisan route:list --path=state
vendor/bin/sail artisan test --compact --filter=PartyStateTest
```
Expected: the listing shows `GET api/parties/{party}/state ... api.parties.state`, and 7 tests pass. If the route name came out prefixed differently, fix the `->name()` call so it is exactly `api.parties.state`.

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add app/Http/Controllers/Api/PartyStateController.php routes/api.php \
    app/Providers/AppServiceProvider.php tests/Feature/PartyStateTest.php
git commit -m "feat(gallery): expose a stateless probe for the pictures state"
```

---

## Task 3: The baseline and the cadence, as props

**Files:**
- Modify: `app/Http/Controllers/PartyController.php`
- Modify: `resources/js/pages/party/gallery.tsx`
- Test: `tests/Feature/GalleryTest.php`

**Interfaces:**
- Consumes: `App\Services\PartyPicturesState::for()` from Task 1.
- Produces: two Inertia props on `party/gallery` — `picturesState: {count: number, last_change: string|null}` and `refresh: {hot, warm, cold, warm_after, cold_after}` (all integers, seconds). Exported TypeScript types `PicturesState` and `RefreshConfig` from `resources/js/pages/party/gallery.tsx`, consumed by Task 5.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/GalleryTest.php`:

```php
it('passes the pictures state as the polling baseline', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create();

    $this->get(route('party.gallery', $party))
        ->assertInertia(fn (Assert $page) => $page
            ->where('picturesState.count', 1)
            ->where('picturesState.last_change', $picture->updated_at->toIso8601String())
        );
});

it('passes a zeroed pictures state for an expired party', function () {
    $party = Party::factory()->expired()->create();
    Picture::factory()->count(2)->for($party)->create();

    $this->get(route('party.gallery', $party))
        ->assertInertia(fn (Assert $page) => $page
            ->where('picturesState.count', 0)
            ->where('picturesState.last_change', null)
        );
});

it('passes the refresh cadence to the page', function () {
    $party = Party::factory()->create();

    $this->get(route('party.gallery', $party))
        ->assertInertia(fn (Assert $page) => $page
            ->where('refresh.hot', 10)
            ->where('refresh.warm', 30)
            ->where('refresh.cold', 60)
            ->where('refresh.warm_after', 120)
            ->where('refresh.cold_after', 300)
        );
});
```

Check the head of `tests/Feature/GalleryTest.php`: if `Inertia\Testing\AssertableInertia as Assert` and `App\Models\Picture` are not already imported, add them.

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/sail artisan test --compact --filter="polling baseline|refresh cadence|zeroed pictures state"`
Expected: FAIL — `Inertia property [picturesState] does not exist.`

- [ ] **Step 3: Add the props to the controller**

In `app/Http/Controllers/PartyController.php`, add `use App\Services\PartyPicturesState;` to the imports, take the service as a parameter of `show()`, and add the two props:

```php
    public function show(Request $request, Party $party, PartyPicturesState $partyPicturesState): Response
    {
        $hidePictures = $party->hidePictures();
        $picturesPage = $this->pictures($party, $hidePictures);

        return Inertia::render('party/gallery', [
            'party' => PartyResource::make($party)->resolve(),
            'pictures' => Inertia::scroll(
                fn () => PictureResource::collection($picturesPage)
            ),
            'picturesCount' => $picturesPage->total(),
            'picturesState' => $partyPicturesState->for($party),
            'refresh' => [
                'hot' => (int) config('party.refresh.hot'),
                'warm' => (int) config('party.refresh.warm'),
                'cold' => (int) config('party.refresh.cold'),
                'warm_after' => (int) config('party.refresh.warm_after'),
                'cold_after' => (int) config('party.refresh.cold_after'),
            ],
            'hidePictures' => $hidePictures,
            'canEdit' => ResolvePartyHost::isHost($request, $party),
            'maxDownloadPictures' => (int) config('party.download.max_pictures'),
            'csrfToken' => csrf_token(),
        ]);
    }
```

Add a PHPDoc block above `show()` explaining the baseline:

```php
    /**
     * `picturesState` is the reference the page's polling starts from. It has
     * to travel with the render rather than be established by the first poll:
     * a picture arriving between the two would otherwise be absorbed into the
     * reference and never displayed.
     */
```

- [ ] **Step 4: Declare the props on the page**

In `resources/js/pages/party/gallery.tsx`, add the two exported types after the `PicturePage` interface:

```tsx
export interface PicturesState {
    count: number;
    last_change: string | null;
}

export interface RefreshConfig {
    /** Seconds between polls while the gallery is empty or just moved. */
    hot: number;
    warm: number;
    cold: number;
    /** Seconds since the last change at which the cadence steps down. */
    warm_after: number;
    cold_after: number;
}
```

and extend `Props`:

```tsx
interface Props {
    party: Party;
    pictures: PicturePage;
    picturesCount: number;
    picturesState: PicturesState;
    refresh: RefreshConfig;
    hidePictures: boolean;
    canEdit: boolean;
    maxDownloadPictures: number;
    csrfToken: string;
}
```

Add `picturesState` and `refresh` to the destructured parameter list of `export default function Gallery({ … }: Props)`. They are unused for now; that is fine, Task 6 wires them.

- [ ] **Step 5: Run the tests to verify they pass**

Run: `vendor/bin/sail artisan test --compact --filter=GalleryTest`
Expected: PASS.

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add app/Http/Controllers/PartyController.php resources/js/pages/party/gallery.tsx tests/Feature/GalleryTest.php
git commit -m "feat(gallery): ship the polling baseline and cadence as props"
```

---

## Task 4: Let the page know the lightbox is open

**Files:**
- Modify: `resources/js/hooks/use-photoswipe.ts`
- Test: `tests/Web/GalleryTest.php`

**Interfaces:**
- Produces: `usePhotoSwipe(gallerySelector: string, enabled?: boolean): boolean` — `true` while the lightbox is showing. Task 6 uses it as the third silent-refresh condition.

- [ ] **Step 1: Write the failing test**

Append to `tests/Web/GalleryTest.php`:

```php
it('marks the page while the lightbox is open', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(2)->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->assertMissing('[data-lightbox-open]')
        ->click('#gallery a')
        ->assertPresent('[data-lightbox-open]')
        ->assertNoJavaScriptErrors();
});
```

- [ ] **Step 2: Run the test to verify it fails**

Run: `vendor/bin/sail artisan test --compact --filter="lightbox is open"`
Expected: FAIL — the `[data-lightbox-open]` element is never present.

- [ ] **Step 3: Return the open state from the hook**

Rewrite `resources/js/hooks/use-photoswipe.ts` so it tracks and returns the state. Change the imports to `import { useEffect, useState } from 'react';`, change the signature and body as follows, and leave the `uiRegister` block exactly as it is:

```ts
/**
 * Initialize a PhotoSwipe lightbox on the given gallery selector.
 * Includes a custom download button in the lightbox UI.
 *
 * Returns whether the lightbox is currently showing, which the gallery uses
 * to hold back an automatic refresh rather than yank a slide away.
 */
export default function usePhotoSwipe(gallerySelector: string, enabled: boolean = true): boolean {
    const [open, setOpen] = useState(false);

    useEffect(() => {
        setOpen(false);

        if (!enabled) {
            return;
        }

        const lightbox = new PhotoSwipeLightbox({
            gallery: gallerySelector,
            children: 'a',
            initialZoomLevel: 'fit',
            pswpModule: () => import('photoswipe'),
        });

        lightbox.on('uiRegister', () => {
            /* Keep the existing download-button registration byte for byte. */
        });

        lightbox.on('beforeOpen', () => setOpen(true));
        lightbox.on('close', () => setOpen(false));

        lightbox.init();

        return () => {
            lightbox.destroy();
        };
    }, [gallerySelector, enabled]);

    return open;
}
```

- [ ] **Step 4: Surface the state in the page for the test**

In `resources/js/pages/party/gallery.tsx`, capture the return value:

```tsx
    const lightboxOpen = usePhotoSwipe('#gallery', !selection.selectMode);
```

and render a marker just inside the `<PartyLayout>`, right after `<Head title={title} />`:

```tsx
            {lightboxOpen && <div data-lightbox-open hidden />}
```

- [ ] **Step 5: Build and run the test**

Run:
```bash
vendor/bin/sail npm run build
vendor/bin/sail artisan test --compact --filter="lightbox is open"
```
Expected: PASS. If it flakes on a timeout, re-run once before investigating.

- [ ] **Step 6: Commit**

```bash
git add resources/js/hooks/use-photoswipe.ts resources/js/pages/party/gallery.tsx tests/Web/GalleryTest.php
git commit -m "feat(gallery): report whether the lightbox is open"
```

---

## Task 5: The polling hook

**Files:**
- Create: `resources/js/hooks/use-gallery-changes.ts`

**Interfaces:**
- Consumes: `PicturesState` and `RefreshConfig` from `@/pages/party/gallery` (Task 3), and the `api.parties.state` route (Task 2).
- Produces: `useGalleryChanges(partyName: string, options: {enabled: boolean; initial: PicturesState; config: RefreshConfig}): GalleryChanges` where `GalleryChanges` is `{changed: boolean; delta: number; acknowledge: () => void}`. `acknowledge` is a stable `useCallback`, safe to use as an effect dependency. Task 6 consumes it.

There is no unit test for this hook: the project has no JS test runner, and its behaviour is asserted end to end by the browser tests in Task 6. Write it, then move straight to Task 6 and let those tests prove it.

- [ ] **Step 1: Write the hook**

Create `resources/js/hooks/use-gallery-changes.ts`:

```ts
import { useCallback, useEffect, useRef, useState } from 'react';

import type { PicturesState, RefreshConfig } from '@/pages/party/gallery';

/** Ceiling on the back-off after repeated network failures, in milliseconds. */
const MAX_BACKOFF = 60_000;

interface GalleryChangesOptions {
    /** False on an expired gallery, which never moves again. */
    enabled: boolean;
    /** The state the page was rendered from. */
    initial: PicturesState;
    config: RefreshConfig;
}

export interface GalleryChanges {
    /** The gallery moved since the last acknowledged state. */
    changed: boolean;
    /** Pictures gained since then; zero or negative when it shrank instead. */
    delta: number;
    /** Take the latest polled state as the new reference. */
    acknowledge: () => void;
}

/**
 * Poll the party's state endpoint and report whether the gallery moved.
 *
 * The hook owns the cadence and nothing else: it never reloads anything, so
 * the page stays free to decide between refreshing silently and asking first.
 * It slows down as the party quiets, sleeps while the tab is in the
 * background, and polls once immediately on the way back — someone putting
 * their phone down and picking it up again is the common case.
 */
export default function useGalleryChanges(
    partyName: string,
    { enabled, initial, config }: GalleryChangesOptions,
): GalleryChanges {
    const [baseline, setBaseline] = useState(initial);
    const [latest, setLatest] = useState(initial);

    const latestRef = useRef(latest);
    latestRef.current = latest;

    const timer = useRef<ReturnType<typeof setTimeout>>(undefined);
    const failures = useRef(0);

    const { hot, warm, cold, warm_after: warmAfter, cold_after: coldAfter } = config;

    /** Seconds to wait before the next poll, from how long ago the gallery moved. */
    const interval = useCallback(
        (state: PicturesState): number => {
            if (state.count === 0 || state.last_change === null) {
                return hot;
            }

            const age = (Date.now() - new Date(state.last_change).getTime()) / 1000;

            if (age < warmAfter) {
                return hot;
            }

            return age < coldAfter ? warm : cold;
        },
        [cold, coldAfter, hot, warm, warmAfter],
    );

    useEffect(() => {
        if (!enabled) {
            return;
        }

        let cancelled = false;

        const schedule = (delay: number) => {
            timer.current = setTimeout(() => void poll(), delay);
        };

        const poll = async () => {
            if (cancelled || document.hidden) {
                return;
            }

            try {
                const response = await fetch(`/api/parties/${encodeURIComponent(partyName)}/state`, {
                    headers: { Accept: 'application/json' },
                });

                if (!response.ok) {
                    throw new Error(String(response.status));
                }

                const state = (await response.json()) as PicturesState;

                if (cancelled) {
                    return;
                }

                failures.current = 0;
                setLatest(state);
                schedule(interval(state) * 1000);
            } catch {
                /*
                 * A guest has nothing to do with a network error on a
                 * background probe, so it stays silent and only backs off.
                 */
                if (cancelled) {
                    return;
                }

                failures.current += 1;
                schedule(Math.min(interval(latestRef.current) * 1000 * 2 ** failures.current, MAX_BACKOFF));
            }
        };

        const onVisibilityChange = () => {
            clearTimeout(timer.current);

            if (!document.hidden) {
                void poll();
            }
        };

        document.addEventListener('visibilitychange', onVisibilityChange);
        schedule(interval(latestRef.current) * 1000);

        return () => {
            cancelled = true;
            clearTimeout(timer.current);
            document.removeEventListener('visibilitychange', onVisibilityChange);
        };
    }, [enabled, interval, partyName]);

    const acknowledge = useCallback(() => setBaseline(latestRef.current), []);

    return {
        changed: latest.count !== baseline.count || latest.last_change !== baseline.last_change,
        delta: latest.count - baseline.count,
        acknowledge,
    };
}
```

- [ ] **Step 2: Check it compiles**

Run: `vendor/bin/sail npm run build`
Expected: the build succeeds. Pre-existing `tsc` errors in `resources/js/app.tsx` and `resources/js/components/data-table/data.ts` are unrelated — no new error may mention `use-gallery-changes`.

- [ ] **Step 3: Commit**

```bash
git add resources/js/hooks/use-gallery-changes.ts
git commit -m "feat(gallery): poll the state endpoint on an adaptive cadence"
```

---

## Task 6: Refresh silently, or offer the pill

**Files:**
- Create: `resources/js/components/party/new-pictures-pill.tsx`
- Modify: `resources/js/pages/party/gallery.tsx`
- Modify: `resources/js/components/party/gallery-sticky-bar.tsx`
- Test: `tests/Web/GalleryAutoRefreshTest.php`

**Interfaces:**
- Consumes: `useGalleryChanges` (Task 5), `usePhotoSwipe`'s boolean (Task 4), the `picturesState` and `refresh` props (Task 3).
- Produces: the finished behaviour. Nothing downstream depends on it.

- [ ] **Step 1: Write the failing tests**

Create `tests/Web/GalleryAutoRefreshTest.php`:

```php
<?php

use App\Models\Party;
use App\Models\Picture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
    config(['party.refresh.hot' => 1, 'party.refresh.warm' => 1, 'party.refresh.cold' => 1]);
});

it('shows a picture that arrives after the page loaded', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(2)->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->assertCount('#gallery img', 2);

    Picture::factory()->for($party)->create();

    $page->assertCount('#gallery img', 3)
        ->assertMissing('[data-gallery-pill]')
        ->assertNoJavaScriptErrors();
});

it('fills an empty gallery on its own', function () {
    $party = Party::factory()->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->assertPresent('[data-gallery-notice]');

    Picture::factory()->for($party)->create();

    $page->assertMissing('[data-gallery-notice]')
        ->assertCount('#gallery img', 1)
        ->assertNoJavaScriptErrors();
});

it('offers a pill instead of moving the grid while selecting', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(2)->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->click('[data-gallery-select]');

    Picture::factory()->for($party)->create();

    $page->assertSeeIn('[data-gallery-pill]', '1 nouvelle photo')
        ->assertCount('#gallery img', 2)
        ->assertNoJavaScriptErrors();
});

it('refreshes and hides the pill when it is tapped', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(2)->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->click('[data-gallery-select]');

    Picture::factory()->for($party)->create();

    $page->assertPresent('[data-gallery-pill]')
        ->click('[data-gallery-pill]')
        ->assertMissing('[data-gallery-pill]')
        ->assertCount('#gallery img', 3)
        ->assertNoJavaScriptErrors();
});

it('never polls an expired gallery', function () {
    $party = Party::factory()->expired()->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->assertSeeIn('[data-gallery-notice]', 'Cette galerie a expiré');

    Picture::factory()->for($party)->create();

    $page->assertSeeIn('[data-gallery-notice]', 'Cette galerie a expiré')
        ->assertMissing('[data-gallery-pill]')
        ->assertCount('#gallery img', 0)
        ->assertNoJavaScriptErrors();
});

it('tells an empty gallery to stay open rather than to reload', function () {
    $party = Party::factory()->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->assertSeeIn('[data-gallery-notice]', 'gardez cette page ouverte')
        ->assertDontSee('rafraîchissez la page')
        ->assertNoJavaScriptErrors();
});
```

- [ ] **Step 2: Run the tests to verify they fail**

Run: `vendor/bin/sail artisan test --compact tests/Web/GalleryAutoRefreshTest.php`
Expected: FAIL — the grid still holds 2 pictures after one is inserted.

- [ ] **Step 3: Give the select button a test handle**

In `resources/js/components/party/gallery-sticky-bar.tsx`, add `data-gallery-select` to the « Sélectionner » button, alongside its existing `type` and `onClick`:

```tsx
                        <button
                            type="button"
                            data-gallery-select
                            onClick={onEnterSelect}
                            className="rounded-full border border-ink/20 px-4 py-2 text-[13px] tracking-wide whitespace-nowrap transition-colors hover:border-ink hover:bg-ink/5"
                        >
                            Sélectionner
                        </button>
```

- [ ] **Step 4: Write the pill**

Create `resources/js/components/party/new-pictures-pill.tsx`:

```tsx
interface NewPicturesPillProps {
    /** Pictures gained since the last refresh; zero or negative when it shrank. */
    delta: number;
    onClick: () => void;
}

/**
 * Offered instead of an automatic refresh when the visitor is busy — scrolled
 * down, selecting, or looking at a picture full screen. Tapping it takes them
 * back to the top, where the new pictures are.
 */
export default function NewPicturesPill({ delta, onClick }: NewPicturesPillProps) {
    const label =
        delta === 1 ? '1 nouvelle photo' : delta > 1 ? `${delta} nouvelles photos` : 'Galerie mise à jour';

    return (
        <button
            type="button"
            data-gallery-pill
            onClick={onClick}
            className="fixed top-16 left-1/2 z-30 flex -translate-x-1/2 cursor-pointer items-center gap-2 rounded-full bg-ink px-5 py-2.5 text-[13px] whitespace-nowrap text-cream shadow-[0_10px_30px_rgba(20,18,15,0.3)]"
        >
            <svg
                width="12"
                height="12"
                viewBox="0 0 12 12"
                fill="none"
                stroke="currentColor"
                strokeWidth="1.7"
                strokeLinecap="round"
                strokeLinejoin="round"
                aria-hidden="true"
            >
                <path d="M6 10V2m0 0L2.6 5.4M6 2l3.4 3.4" />
            </svg>
            {label}
        </button>
    );
}
```

- [ ] **Step 5: Wire it into the page**

In `resources/js/pages/party/gallery.tsx`:

Add to the imports:

```tsx
import { Head, router } from '@inertiajs/react';
import NewPicturesPill from '@/components/party/new-pictures-pill';
import useGalleryChanges from '@/hooks/use-gallery-changes';
```

Replace the `EMPTY_MESSAGE` constant with the copy the auto-refresh makes true:

```tsx
const EMPTY_MESSAGE =
    'Aucune photo pour le moment. Les clichés apparaissent ici quelques secondes après leur prise, gardez cette page ouverte.';
```

Add next to the other module constants:

```tsx
/** Past this many pixels of scroll, a refresh would move the grid under the reader. */
const SILENT_REFRESH_SCROLL_LIMIT = 200;
```

Add, after the `photoShare` declarations:

```tsx
    const changes = useGalleryChanges(party.name, {
        enabled: !hidePictures,
        initial: picturesState,
        config: refresh,
    });

    const { acknowledge } = changes;

    const refreshPictures = useCallback(() => {
        acknowledge();
        router.reload({ only: ['pictures', 'picturesCount'], reset: ['pictures'] });
    }, [acknowledge]);

    /*
     * `reset` is what keeps the refresh honest: `pictures` is a merged scroll
     * prop, so without it the fresh page 1 would stack on top of what is
     * already loaded and show every picture twice.
     *
     * The refresh only happens on its own when nothing would move under the
     * visitor's fingers. Otherwise the pill takes over and they decide.
     */
    useEffect(() => {
        if (!changes.changed || selection.selectMode || lightboxOpen) {
            return;
        }

        if (window.scrollY > SILENT_REFRESH_SCROLL_LIMIT) {
            return;
        }

        refreshPictures();
    }, [changes.changed, lightboxOpen, refreshPictures, selection.selectMode]);

    const onPillClick = useCallback(() => {
        window.scrollTo({ top: 0, behavior: 'smooth' });
        refreshPictures();
    }, [refreshPictures]);
```

Render the pill just before `<SelectionBar …>`:

```tsx
            {changes.changed && <NewPicturesPill delta={changes.delta} onClick={onPillClick} />}
```

- [ ] **Step 6: Build and run the tests**

Run:
```bash
vendor/bin/sail npm run build
vendor/bin/sail artisan test --compact tests/Web/GalleryAutoRefreshTest.php
```
Expected: PASS, 6 tests. Re-run once on a timeout before investigating.

**If every timing-dependent test fails at once**, the `config(['party.refresh.hot' => 1])`
override is not reaching the application the browser talks to. Check by loading the page
and reading the `refresh` prop out of the Inertia page object. The fallback is to set the
cadence through the environment instead, by adding to the `<php>` section of `phpunit.xml`:

```xml
        <env name="PARTY_REFRESH_HOT" value="1"/>
        <env name="PARTY_REFRESH_WARM" value="1"/>
        <env name="PARTY_REFRESH_COLD" value="1"/>
```

If you take the fallback, drop the `config([...])` line from the test's `beforeEach`, and
re-run the whole of `tests/Web/` — a one-second cadence applies to every gallery browser
test then, and one of them may start racing a reload.

- [ ] **Step 7: Fix the empty-state assertion in the existing suite**

`tests/Web/GalleryTest.php` asserts the old empty copy. Update that assertion:

```php
    $page->assertSeeIn('[data-gallery-notice]', 'La galerie se remplit bientôt')
        ->assertSeeIn('[data-gallery-notice]', 'Aucune photo pour le moment')
        ->assertNoJavaScriptErrors();
```

That assertion already reads on `Aucune photo pour le moment`, which the new copy keeps — run it and only change it if it fails.

Run: `vendor/bin/sail artisan test --compact tests/Web/GalleryTest.php`
Expected: PASS.

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add resources/js/components/party/new-pictures-pill.tsx resources/js/pages/party/gallery.tsx \
    resources/js/components/party/gallery-sticky-bar.tsx \
    tests/Web/GalleryAutoRefreshTest.php tests/Web/GalleryTest.php
git commit -m "feat(gallery): refresh on new pictures, silently or on a tap"
```

---

## Task 7: Whole-suite verification

**Files:** none created or modified unless a failure demands it.

- [ ] **Step 1: Remove the lightbox test marker if it is no longer earning its place**

The `<div data-lightbox-open hidden />` added in Task 4 exists only so a browser test can observe the hook. Keep it — it is one line, and it is the only way to assert that condition from outside. Confirm it is still rendered and still asserted by `tests/Web/GalleryTest.php`.

- [ ] **Step 2: Build the assets**

Run: `vendor/bin/sail npm run build`
Expected: success.

- [ ] **Step 3: Run the full test suite**

Run: `vendor/bin/sail artisan test --compact`
Expected: everything passes. `tests/Feature/AssetBundlingTest.php` guards the split between `app.css` and `admin.css`; if it fails, a public component imported something from `@/components/ui/*`.

- [ ] **Step 4: Check formatting**

Run: `vendor/bin/sail bin pint --dirty --format agent`
Expected: `{"result":"pass"}`.

- [ ] **Step 5: Commit anything the verification changed, then push**

```bash
git add -A
git commit -m "chore(gallery): verification pass on the auto-refresh"
git push -u origin HEAD
```

- [ ] **Step 6: Open the pull request**

Title: `Rafraîchissement automatique de la galerie publique`

Body: summarise the signal, the endpoint's placement outside the `web` group and why, the two refresh behaviours, and paste the final test counts. End it with:

```
🤖 Generated with [Claude Code](https://claude.com/claude-code)

https://claude.ai/code/session_01CXa7LoNCqzaRY1rvgFAkrr
```
