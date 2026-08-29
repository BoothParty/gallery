# Host Gallery Editor Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let the host of a party edit the gallery title, description and cover picture — framing included — straight from the public gallery, using a tokenised link instead of an account.

**Architecture:** A `host_token` column on `parties` composes an edit link. A middleware trades that token for a session flag and strips it from the URL; a second middleware guards a single public write endpoint. The cover framing is stored as three numbers and applied in CSS by the hero, so the banner is stored once and stays re-croppable without loss.

**Tech Stack:** Laravel 12, Inertia 2 + React 19, Tailwind 4, Pest 4 (feature + browser), Intervention Image 3, S3/Minio.

**Spec:** `docs/superpowers/specs/2026-08-29-editeur-hote-galerie.md`

## Global Constraints

- **Every command runs through Sail.** `vendor/bin/sail artisan …`, `vendor/bin/sail bin pint …`. Never bare `php`/`artisan`.
- **Run tests narrowly:** `vendor/bin/sail artisan test --compact --filter=…`. Full suite only at Task 11.
- **Public components live in `resources/js/components/party/`** and may use **only** Tailwind utilities and the project's CSS custom properties. **No shadcn, no Radix, no `@/components/ui/*`** — those compile into `admin.css` only, and a public component importing them silently loses its styles.
- **Available public design tokens:** `bg-surface`, `bg-placeholder`, `bg-ink`, `text-ink`, `text-cream`, `font-sans`, `font-serif`, and the CSS variables `--color-ink`, `--color-shade`, `--color-cream`, `--color-surface`.
- **Code style:** files under `resources/js/components/party/`, `resources/js/pages/party/`, `resources/js/hooks/` use **single quotes, semicolons, 4-space indent**. `resources/js/pages/admin/parties/index.tsx` uses **double quotes, no semicolons**. Match the file you are in.
- **PHP:** explicit return types everywhere, curly braces always, PHPDoc blocks rather than inline comments, constructor property promotion.
- **Validation rules are arrays**, never pipe strings.
- **Host-facing validation messages are in French.** Admin-facing ones stay English.
- **After touching any PHP file:** `vendor/bin/sail bin pint --dirty --format agent`.
- **`tests/Web/` browser tests are known to flake on 5 s timeouts.** Re-run once before investigating a failure.
- Exact character caps: `display_name` **60**, `description` **90**, zoom **1 → 3**, positions **0 → 100**.

---

## File Structure

**Created**

| File | Responsibility |
| --- | --- |
| `database/migrations/…_add_host_token_to_parties_table.php` | `host_token` column + backfill |
| `database/migrations/…_add_banner_framing_to_parties_table.php` | The three framing columns |
| `app/Http/Middleware/ResolvePartyHost.php` | Token → session, then strip the token from the URL |
| `app/Http/Middleware/EnsurePartyHost.php` | 403 unless the session carries this party's host flag |
| `app/Http/Requests/UpdatePartyMetadataRequest.php` | Host-side validation, French messages |
| `app/Http/Controllers/PartyMetadataController.php` | The single write action |
| `resources/js/hooks/use-cover-crop.ts` | Crop state and the pointer maths |
| `resources/js/components/party/cover-cropper.tsx` | The 16:7 frame, zoom slider, file picker |
| `resources/js/components/party/gallery-editor.tsx` | The modal and its Inertia form |
| `tests/Feature/PartyHostAccessTest.php` | Token → session behaviour |
| `tests/Feature/PartyMetadataTest.php` | The write endpoint |
| `tests/Web/GalleryEditorTest.php` | The editor in a real browser |

**Modified**

| File | Change |
| --- | --- |
| `app/Models/Party.php` | `creating` hook, casts, fillable, `hostEditUrl()` |
| `database/factories/PartyFactory.php` | Framing defaults |
| `app/Http/Resources/PartyResource.php` | Expose the framing |
| `app/Http/Controllers/PartyController.php` | `canEdit` prop |
| `app/Http/Controllers/Admin/PartyController.php` | `host_edit_url`, `regenerateHostToken` |
| `app/Providers/AppServiceProvider.php` | `party-metadata` limiter |
| `bootstrap/app.php` | `party.host` alias |
| `routes/web.php` | Two routes, one middleware |
| `resources/js/components/party/gallery-hero.tsx` | Framing + edit button |
| `resources/js/pages/party/gallery.tsx` | Wire the editor |
| `resources/js/pages/admin/parties/index.tsx` | Host edit link block |
| `tests/Feature/PartyTest.php`, `GalleryTest.php`, `AdminPartiesTest.php` | Extensions |

---

## Task 1: The host token column

**Files:**
- Create: `database/migrations/2026_08_29_100000_add_host_token_to_parties_table.php`
- Modify: `app/Models/Party.php`
- Test: `tests/Feature/PartyTest.php`

**Interfaces:**
- Consumes: nothing.
- Produces: `Party::$host_token` (`?string`), `Party::hostEditUrl(): string`, and the guarantee that every created party has a token.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/PartyTest.php`:

```php
// ── Host edit link ────────────────────────────────────────────────────────────

it('generates a host token when a party is created', function () {
    $party = Party::factory()->create();

    expect($party->host_token)->toBeString()->toHaveLength(48);
});

it('gives each party its own host token', function () {
    $first = Party::factory()->create();
    $second = Party::factory()->create();

    expect($first->host_token)->not->toBe($second->host_token);
});

it('keeps a host token that was set explicitly', function () {
    $party = Party::factory()->create(['host_token' => 'a-token-we-chose']);

    expect($party->host_token)->toBe('a-token-we-chose');
});

it('composes the host edit url from the public url and the token', function () {
    $party = Party::factory()->create(['name' => 'noces']);

    expect($party->hostEditUrl())
        ->toBe('https://noces.'.config('party.domain').'/?token='.$party->host_token);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

```bash
vendor/bin/sail artisan test --compact --filter=PartyTest
```

Expected: FAIL — `host_token` is not a column, `hostEditUrl` does not exist.

- [ ] **Step 3: Write the migration**

`database/migrations/2026_08_29_100000_add_host_token_to_parties_table.php`:

```php
<?php

use App\Models\Party;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    /**
     * Secret of the link that lets a host edit their own gallery without an
     * account. Stored in the clear so the administration can show the link
     * again at any time; a hash would make the link unrecoverable.
     */
    public function up(): void
    {
        Schema::table('parties', function (Blueprint $table): void {
            $table->string('host_token', 64)->nullable()->unique()->after('name');
        });

        Party::query()
            ->whereNull('host_token')
            ->each(function (Party $party): void {
                $party->forceFill(['host_token' => Str::random(48)])->save();
            });
    }

    public function down(): void
    {
        Schema::table('parties', function (Blueprint $table): void {
            $table->dropUnique(['host_token']);
            $table->dropColumn('host_token');
        });
    }
};
```

- [ ] **Step 4: Extend the model**

In `app/Models/Party.php`, add `Str` to the imports (`use Illuminate\Support\Str;`) and put the `creating` hook alongside the existing `deleted` one:

```php
    protected static function booting(): void
    {
        static::creating(function (Party $party): void {
            $party->host_token ??= Str::random(48);
        });

        static::deleted(function (Party $party): void {
            if ($party->banner_path) {
                Storage::disk('s3')->delete($party->banner_path);
            }
        });
    }
```

`host_token` stays **out of `$fillable`** on purpose — only this hook and the
administration's regeneration write it, so no form submission can pin it to a
chosen value. Assigning it on the instance, as the factory does in the test
above, is not mass assignment and still works.

Then add, next to `publicUrl()`:

```php
    /**
     * Link the host uses to edit their own gallery. The only place that knows
     * the shape of that link.
     */
    public function hostEditUrl(): string
    {
        return $this->publicUrl().'/?token='.$this->host_token;
    }
```

- [ ] **Step 5: Run the tests to verify they pass**

```bash
vendor/bin/sail artisan test --compact --filter=PartyTest
```

Expected: PASS.

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add app/Models/Party.php database/migrations tests/Feature/PartyTest.php
git commit -m "feat(gallery): give every party a host edit token"
```

---

## Task 2: The banner framing columns

**Files:**
- Create: `database/migrations/2026_08_29_100001_add_banner_framing_to_parties_table.php`
- Modify: `app/Models/Party.php`, `database/factories/PartyFactory.php`
- Test: `tests/Feature/PartyTest.php`

**Interfaces:**
- Consumes: Task 1's migration ordering.
- Produces: `Party::$banner_position_x`, `$banner_position_y`, `$banner_zoom`, all `float`, all fillable, defaulting to `50`, `50`, `1`.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/PartyTest.php`:

```php
// ── Banner framing ────────────────────────────────────────────────────────────

it('centres the banner framing by default', function () {
    $party = Party::factory()->create();

    expect($party->banner_position_x)->toBe(50.0)
        ->and($party->banner_position_y)->toBe(50.0)
        ->and($party->banner_zoom)->toBe(1.0);
});

it('casts the banner framing to floats', function () {
    $party = Party::factory()->create([
        'banner_position_x' => 12.5,
        'banner_position_y' => 80,
        'banner_zoom' => 2.25,
    ]);

    expect($party->fresh()->banner_position_x)->toBe(12.5)
        ->and($party->fresh()->banner_position_y)->toBe(80.0)
        ->and($party->fresh()->banner_zoom)->toBe(2.25);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

```bash
vendor/bin/sail artisan test --compact --filter=PartyTest
```

Expected: FAIL — unknown columns.

- [ ] **Step 3: Write the migration**

`database/migrations/2026_08_29_100001_add_banner_framing_to_parties_table.php`:

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * How the banner sits behind the gallery hero. The crop is applied in CSS
     * rather than cut into the file, so the stored banner stays whole and the
     * host can re-frame it as often as they like without losing quality.
     *
     * The columns carry a default instead of being nullable: "no framing
     * chosen" and "centred, unzoomed" render the same image, and a default
     * saves every consumer a null coalesce.
     */
    public function up(): void
    {
        Schema::table('parties', function (Blueprint $table): void {
            $table->decimal('banner_position_x', 5, 2)->default(50)->after('banner_path');
            $table->decimal('banner_position_y', 5, 2)->default(50)->after('banner_position_x');
            $table->decimal('banner_zoom', 4, 2)->default(1)->after('banner_position_y');
        });
    }

    public function down(): void
    {
        Schema::table('parties', function (Blueprint $table): void {
            $table->dropColumn(['banner_position_x', 'banner_position_y', 'banner_zoom']);
        });
    }
};
```

- [ ] **Step 4: Extend the model**

In `app/Models/Party.php`, add the three columns to `$fillable` after `'description'` — unlike `banner_path` they are numbers the validation bounds, with no capacity to point at an arbitrary file:

```php
    /** @var list<string> */
    protected $fillable = [
        'name',
        'display_name',
        'description',
        'banner_position_x',
        'banner_position_y',
        'banner_zoom',
        'user_id',
        'expire_at',
        'source_key',
        'source_event_id',
    ];
```

and extend `casts()`:

```php
    protected function casts(): array
    {
        return [
            'expire_at' => 'datetime',
            'banner_position_x' => 'float',
            'banner_position_y' => 'float',
            'banner_zoom' => 'float',
        ];
    }
```

- [ ] **Step 5: Extend the factory**

In `database/factories/PartyFactory.php`, add to `definition()` after `'banner_path' => null,`:

```php
            'banner_position_x' => 50,
            'banner_position_y' => 50,
            'banner_zoom' => 1,
```

- [ ] **Step 6: Run the tests to verify they pass**

```bash
vendor/bin/sail artisan test --compact --filter=PartyTest
```

Expected: PASS.

- [ ] **Step 7: Format and commit**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add app/Models/Party.php database/factories/PartyFactory.php database/migrations tests/Feature/PartyTest.php
git commit -m "feat(gallery): store how the banner is framed behind the hero"
```

---

## Task 3: Trading the token for a session

**Files:**
- Create: `app/Http/Middleware/ResolvePartyHost.php`, `app/Http/Middleware/EnsurePartyHost.php`
- Modify: `bootstrap/app.php`, `routes/web.php`, `app/Http/Controllers/PartyController.php`
- Test: `tests/Feature/PartyHostAccessTest.php`

**Interfaces:**
- Consumes: `Party::$host_token` from Task 1.
- Produces: `ResolvePartyHost::sessionKey(Party $party): string` (returns `"party_host.{id}"`), the `party.host` middleware alias, and a `canEdit` boolean prop on the `party/gallery` page.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/PartyHostAccessTest.php`:

```php
<?php

use App\Models\Party;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
});

// ── Opening a host session ────────────────────────────────────────────────────

it('opens a host session and strips the token from the url', function () {
    $party = Party::factory()->create();

    $this->get(route('party.gallery', $party).'?token='.$party->host_token)
        ->assertRedirect(route('party.gallery', $party))
        ->assertSessionHas('party_host.'.$party->id, true);
});

it('redirects the same way for a wrong token, granting nothing', function () {
    $party = Party::factory()->create();

    $this->get(route('party.gallery', $party).'?token=not-the-right-token')
        ->assertRedirect(route('party.gallery', $party))
        ->assertSessionMissing('party_host.'.$party->id);
});

it('leaves a plain visit untouched', function () {
    $party = Party::factory()->create();

    $this->get(route('party.gallery', $party))
        ->assertOk()
        ->assertSessionMissing('party_host.'.$party->id);
});

// ── The canEdit prop ──────────────────────────────────────────────────────────

it('reports canEdit false for a plain visitor', function () {
    $party = Party::factory()->create();

    $this->get(route('party.gallery', $party))
        ->assertInertia(fn ($page) => $page->where('canEdit', false));
});

it('reports canEdit true once the host session is open', function () {
    $party = Party::factory()->create();

    $this->withSession(['party_host' => [$party->id => true]])
        ->get(route('party.gallery', $party))
        ->assertInertia(fn ($page) => $page->where('canEdit', true));
});

it('keeps the host editing an expired gallery', function () {
    $party = Party::factory()->expired()->create();

    $this->withSession(['party_host' => [$party->id => true]])
        ->get(route('party.gallery', $party))
        ->assertInertia(fn ($page) => $page
            ->where('hidePictures', true)
            ->where('canEdit', true)
        );
});

it('does not let the session of one party edit another', function () {
    $mine = Party::factory()->create();
    $other = Party::factory()->create();

    $this->withSession(['party_host' => [$mine->id => true]])
        ->get(route('party.gallery', $other))
        ->assertInertia(fn ($page) => $page->where('canEdit', false));
});
```

- [ ] **Step 2: Run the tests to verify they fail**

```bash
vendor/bin/sail artisan test --compact --filter=PartyHostAccessTest
```

Expected: FAIL — no redirect, no `canEdit` prop.

- [ ] **Step 3: Write `ResolvePartyHost`**

`app/Http/Middleware/ResolvePartyHost.php`:

```php
<?php

namespace App\Http\Middleware;

use App\Models\Party;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolvePartyHost
{
    /**
     * Trade a valid `?token=` for a session flag, then send the visitor to the
     * same page without it.
     *
     * The redirect happens whether or not the token was right, so that a
     * correct and an incorrect token are indistinguishable from outside: an
     * attacker gets no oracle to guess a token by trial. It also keeps the
     * secret out of the address bar, the history, outgoing `Referer` headers
     * and any URL the host copies to their guests.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->query('token');

        if (! is_string($token)) {
            return $next($request);
        }

        $party = $request->route('party');

        if ($party instanceof Party
            && is_string($party->host_token)
            && hash_equals($party->host_token, $token)) {
            $request->session()->put(self::sessionKey($party), true);
        }

        return redirect()->to($request->fullUrlWithoutQuery('token'));
    }

    /**
     * Session key carrying the right to edit one party. Both middlewares and
     * the gallery controller read it through here rather than spelling it out.
     */
    public static function sessionKey(Party $party): string
    {
        return 'party_host.'.$party->id;
    }
}
```

- [ ] **Step 4: Write `EnsurePartyHost`**

`app/Http/Middleware/EnsurePartyHost.php`:

```php
<?php

namespace App\Http\Middleware;

use App\Models\Party;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePartyHost
{
    /**
     * Guard the host's write endpoint. Reading a gallery never goes through
     * here; only editing one does.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $party = $request->route('party');

        abort_unless(
            $party instanceof Party
                && $request->session()->boolean(ResolvePartyHost::sessionKey($party)),
            403,
        );

        return $next($request);
    }
}
```

- [ ] **Step 5: Register the alias**

In `bootstrap/app.php`, inside `withMiddleware`:

```php
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);

        $middleware->alias([
            'party.host' => EnsurePartyHost::class,
        ]);
    })
```

with `use App\Http\Middleware\EnsurePartyHost;` added to the imports.

- [ ] **Step 6: Attach the middleware to the gallery route**

In `routes/web.php`, add `use App\Http\Middleware\ResolvePartyHost;` and change the gallery line to:

```php
    Route::get('/', [PartyController::class, 'show'])
        ->middleware(ResolvePartyHost::class)
        ->name('gallery');
```

Route middleware runs after the `web` group, so route model binding has already
turned `{party}` into a `Party` by the time `ResolvePartyHost` reads it.

- [ ] **Step 7: Expose the prop**

In `app/Http/Controllers/PartyController.php`, add `use App\Http\Middleware\ResolvePartyHost;` and `use Illuminate\Http\Request;`, then take the request and pass the flag:

```php
    public function show(Request $request, Party $party): Response
    {
        $hidePictures = $party->hidePictures();
        $picturesPage = $this->pictures($party, $hidePictures);

        return Inertia::render('party/gallery', [
            'party' => PartyResource::make($party)->resolve(),
            'pictures' => Inertia::scroll(
                fn () => PictureResource::collection($picturesPage)
            ),
            'picturesCount' => $picturesPage->total(),
            'hidePictures' => $hidePictures,
            'canEdit' => $request->session()->boolean(ResolvePartyHost::sessionKey($party)),
            'maxDownloadPictures' => (int) config('party.download.max_pictures'),
            'csrfToken' => csrf_token(),
        ]);
    }
```

An expired gallery hides its pictures but stays editable by its host — it is
their gallery, and fixing a title after the fact is legitimate. `canEdit`
therefore never consults `hidePictures()`.

- [ ] **Step 8: Run the tests to verify they pass**

```bash
vendor/bin/sail artisan test --compact --filter=PartyHostAccessTest
```

Expected: PASS. Then confirm nothing regressed:

```bash
vendor/bin/sail artisan test --compact --filter=GalleryTest
```

- [ ] **Step 9: Format and commit**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add app/Http bootstrap/app.php routes/web.php tests/Feature/PartyHostAccessTest.php
git commit -m "feat(gallery): open a host session from a tokenised link"
```

---

## Task 4: The host write endpoint

**Files:**
- Create: `app/Http/Requests/UpdatePartyMetadataRequest.php`, `app/Http/Controllers/PartyMetadataController.php`
- Modify: `routes/web.php`, `app/Providers/AppServiceProvider.php`
- Test: `tests/Feature/PartyMetadataTest.php`

**Interfaces:**
- Consumes: `party.host` alias and `ResolvePartyHost::sessionKey()` from Task 3; `PartyBannerStorage::store()` / `remove()`, already in the codebase.
- Produces: route `party.metadata.update` at `POST /{party}/metadata`, accepting `display_name`, `description`, `banner`, `remove_banner`, `banner_position_x`, `banner_position_y`, `banner_zoom`.

- [ ] **Step 1: Write the failing tests**

Create `tests/Feature/PartyMetadataTest.php`:

```php
<?php

use App\Models\Party;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
});

/**
 * @param  array<string, mixed>  $payload
 */
function hostUpdate(Party $party, array $payload = []): Illuminate\Testing\TestResponse
{
    return test()
        ->withSession(['party_host' => [$party->id => true]])
        ->post(route('party.metadata.update', $party), [
            '_method' => 'put',
            'display_name' => 'Camille & Antoine',
            'description' => 'Samedi 12 septembre · Domaine des Ormes',
            'banner_position_x' => 50,
            'banner_position_y' => 50,
            'banner_zoom' => 1,
            ...$payload,
        ]);
}

// ── Authorisation ─────────────────────────────────────────────────────────────

it('rejects a write without a host session', function () {
    $party = Party::factory()->create();

    $this->post(route('party.metadata.update', $party), [
        'display_name' => 'Pirate',
        'banner_position_x' => 50,
        'banner_position_y' => 50,
        'banner_zoom' => 1,
    ])->assertForbidden();

    expect($party->fresh()->display_name)->toBeNull();
});

it('rejects a write carrying the host session of another party', function () {
    $mine = Party::factory()->create();
    $other = Party::factory()->create();

    $this->withSession(['party_host' => [$mine->id => true]])
        ->post(route('party.metadata.update', $other), [
            'display_name' => 'Pirate',
            'banner_position_x' => 50,
            'banner_position_y' => 50,
            'banner_zoom' => 1,
        ])->assertForbidden();
});

// ── Writing the metadata ──────────────────────────────────────────────────────

it('writes the title, the description and the framing', function () {
    $party = Party::factory()->create();

    hostUpdate($party, [
        'banner_position_x' => 24.5,
        'banner_position_y' => 76,
        'banner_zoom' => 1.8,
    ])->assertRedirect();

    $party->refresh();

    expect($party->display_name)->toBe('Camille & Antoine')
        ->and($party->description)->toBe('Samedi 12 septembre · Domaine des Ormes')
        ->and($party->banner_position_x)->toBe(24.5)
        ->and($party->banner_position_y)->toBe(76.0)
        ->and($party->banner_zoom)->toBe(1.8);
});

it('stores an uploaded banner', function () {
    $party = Party::factory()->create();

    hostUpdate($party, ['banner' => UploadedFile::fake()->image('cover.jpg', 3000, 1200)]);

    $party->refresh();

    expect($party->banner_path)->not->toBeNull();
    Storage::disk('s3')->assertExists($party->banner_path);
});

it('drops the banner and recentres the framing on removal', function () {
    $party = Party::factory()->create([
        'banner_position_x' => 10,
        'banner_position_y' => 90,
        'banner_zoom' => 2.4,
    ]);

    hostUpdate($party, ['banner' => UploadedFile::fake()->image('cover.jpg', 3000, 1200)]);
    $storedPath = $party->fresh()->banner_path;

    hostUpdate($party->fresh(), [
        'remove_banner' => true,
        'banner_position_x' => 10,
        'banner_position_y' => 90,
        'banner_zoom' => 2.4,
    ]);

    $party->refresh();

    expect($party->banner_path)->toBeNull()
        ->and($party->banner_position_x)->toBe(50.0)
        ->and($party->banner_position_y)->toBe(50.0)
        ->and($party->banner_zoom)->toBe(1.0);
    Storage::disk('s3')->assertMissing($storedPath);
});

it('lets removal win over an upload sent in the same submission', function () {
    $party = Party::factory()->create();

    hostUpdate($party, [
        'remove_banner' => true,
        'banner' => UploadedFile::fake()->image('cover.jpg', 3000, 1200),
    ]);

    expect($party->fresh()->banner_path)->toBeNull();
});

// ── Validation ────────────────────────────────────────────────────────────────

it('rejects a title longer than 60 characters', function () {
    $party = Party::factory()->create();

    hostUpdate($party, ['display_name' => str_repeat('a', 61)])
        ->assertSessionHasErrors('display_name');
});

it('rejects a description longer than 90 characters', function () {
    $party = Party::factory()->create();

    hostUpdate($party, ['description' => str_repeat('a', 91)])
        ->assertSessionHasErrors('description');
});

it('rejects a zoom outside 1 to 3', function () {
    $party = Party::factory()->create();

    hostUpdate($party, ['banner_zoom' => 3.5])->assertSessionHasErrors('banner_zoom');
});

it('rejects a position outside 0 to 100', function () {
    $party = Party::factory()->create();

    hostUpdate($party, ['banner_position_x' => 140])->assertSessionHasErrors('banner_position_x');
});

it('rejects a banner that is not an image', function () {
    $party = Party::factory()->create();

    hostUpdate($party, ['banner' => UploadedFile::fake()->create('notes.pdf', 12)])
        ->assertSessionHasErrors('banner');
});
```

- [ ] **Step 2: Run the tests to verify they fail**

```bash
vendor/bin/sail artisan test --compact --filter=PartyMetadataTest
```

Expected: FAIL — the route `party.metadata.update` is not defined.

- [ ] **Step 3: Write the Form Request**

`app/Http/Requests/UpdatePartyMetadataRequest.php`:

```php
<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class UpdatePartyMetadataRequest extends FormRequest
{
    /**
     * The right to edit is settled by the `party.host` middleware, not here,
     * as in the neighbouring requests.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * The 60 and 90 character caps are tighter than the 255 the administration
     * allows: past them the serif title and its small-caps subtitle overflow
     * the hero. The administration keeps its headroom because it knows what it
     * is doing.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'display_name' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:90'],
            'banner' => ['nullable', 'image', 'max:10240'],
            'remove_banner' => ['nullable', 'boolean'],
            'banner_position_x' => ['required', 'numeric', 'between:0,100'],
            'banner_position_y' => ['required', 'numeric', 'between:0,100'],
            'banner_zoom' => ['required', 'numeric', 'between:1,3'],
        ];
    }

    /**
     * In French, unlike the administration's requests: these are read by the
     * host, inside an interface that is French throughout.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'display_name.max' => 'Le nom de la galerie ne peut pas dépasser 60 caractères.',
            'description.max' => 'La description ne peut pas dépasser 90 caractères.',
            'banner.image' => 'La photo de couverture doit être une image.',
            'banner.max' => 'La photo de couverture ne peut pas dépasser 10 Mo.',
            'banner_position_x.between' => 'Le cadrage horizontal est hors limites.',
            'banner_position_y.between' => 'Le cadrage vertical est hors limites.',
            'banner_zoom.between' => 'Le zoom doit être compris entre 1 et 3.',
        ];
    }
}
```

- [ ] **Step 4: Write the controller**

`app/Http/Controllers/PartyMetadataController.php`:

```php
<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePartyMetadataRequest;
use App\Models\Party;
use App\Services\PartyBannerStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;

class PartyMetadataController extends Controller
{
    /**
     * Apply the host's edits to their own gallery.
     *
     * A removal wins over an upload sent in the same submission, as in
     * `Admin\PartyController::update`, so that two contradictory intents have a
     * settled outcome. Removing the cover also recentres the framing: an
     * absent picture has no framing worth keeping.
     */
    public function __invoke(
        UpdatePartyMetadataRequest $request,
        Party $party,
        PartyBannerStorage $partyBannerStorage,
    ): RedirectResponse {
        $party->update(Arr::only($request->validated(), [
            'display_name',
            'description',
            'banner_position_x',
            'banner_position_y',
            'banner_zoom',
        ]));

        if ($request->boolean('remove_banner')) {
            $partyBannerStorage->remove($party);

            $party->update([
                'banner_position_x' => 50,
                'banner_position_y' => 50,
                'banner_zoom' => 1,
            ]);
        } elseif ($request->hasFile('banner')) {
            $partyBannerStorage->store($party, $request->file('banner'));
        }

        return back();
    }
}
```

- [ ] **Step 5: Add the route**

In `routes/web.php`, add `use App\Http\Controllers\PartyMetadataController;` and place the route inside the `{party}` group, after `download`:

```php
    Route::post('metadata', PartyMetadataController::class)
        ->middleware(['party.host', 'throttle:party-metadata'])
        ->name('metadata.update');
```

`POST` rather than `PUT` because the banner travels as multipart, which a `PUT`
cannot carry; the front adds `_method: 'put'`.

- [ ] **Step 6: Add the limiter**

In `app/Providers/AppServiceProvider.php`, after the `party-picture` limiter:

```php
        /**
         * Editing a gallery is a rare gesture; the ceiling exists only to bound
         * abuse. Keyed like its neighbours, by address and party.
         */
        RateLimiter::for('party-metadata', function (Request $request): Limit {
            $party = (string) $request->route('party');

            return Limit::perMinute(20)->by($request->ip().':'.$party);
        });
```

- [ ] **Step 7: Run the tests to verify they pass**

```bash
vendor/bin/sail artisan test --compact --filter=PartyMetadataTest
```

Expected: PASS.

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add app/Http app/Providers/AppServiceProvider.php routes/web.php tests/Feature/PartyMetadataTest.php
git commit -m "feat(gallery): let the host write their gallery metadata"
```

---

## Task 5: Framing and the edit button in the hero

**Files:**
- Modify: `app/Http/Resources/PartyResource.php`, `resources/js/components/party/gallery-hero.tsx`, `resources/js/pages/party/gallery.tsx`
- Test: `tests/Feature/GalleryTest.php`

**Interfaces:**
- Consumes: the framing columns from Task 2, the `canEdit` prop from Task 3.
- Produces: `GalleryHeroProps` = `{ title: string; subtitle: string | null; bannerUrl: string | null; position: { x: number; y: number }; zoom: number; canEdit: boolean; onEdit: () => void }`, and the `party.banner_position_x` / `_y` / `banner_zoom` props on the page.

- [ ] **Step 1: Write the failing test**

Append to `tests/Feature/GalleryTest.php`:

```php
it('passes the banner framing to the gallery page', function () {
    $party = Party::factory()->create([
        'banner_position_x' => 30,
        'banner_position_y' => 70,
        'banner_zoom' => 1.5,
    ]);

    $this->get(route('party.gallery', $party))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('party.banner_position_x', 30.0)
            ->where('party.banner_position_y', 70.0)
            ->where('party.banner_zoom', 1.5)
        );
});
```

- [ ] **Step 2: Run the test to verify it fails**

```bash
vendor/bin/sail artisan test --compact --filter="banner framing to the gallery page"
```

Expected: FAIL — the props are absent.

- [ ] **Step 3: Expose the framing**

In `app/Http/Resources/PartyResource.php`, after `'banner_url'`:

```php
            'banner_position_x' => (float) $this->banner_position_x,
            'banner_position_y' => (float) $this->banner_position_y,
            'banner_zoom' => (float) $this->banner_zoom,
```

- [ ] **Step 4: Run the test to verify it passes**

```bash
vendor/bin/sail artisan test --compact --filter=GalleryTest
```

Expected: PASS.

- [ ] **Step 5: Rewrite the hero**

Replace `resources/js/components/party/gallery-hero.tsx` in full:

```tsx
export interface HeroPosition {
    x: number;
    y: number;
}

interface GalleryHeroProps {
    title: string;
    subtitle: string | null;
    bannerUrl: string | null;
    /** Percentages, as `object-position` reads them. */
    position: HeroPosition;
    /** 1 to 3, applied on top of the `cover` fit. */
    zoom: number;
    canEdit: boolean;
    onEdit: () => void;
}

export default function GalleryHero({
    title,
    subtitle,
    bannerUrl,
    position,
    zoom,
    canEdit,
    onEdit,
}: GalleryHeroProps) {
    return (
        <div
            data-gallery-hero
            className="relative flex h-[62vh] max-h-[560px] min-h-[340px] items-end overflow-hidden bg-placeholder"
            style={{
                backgroundImage:
                    'repeating-linear-gradient(135deg, color-mix(in srgb, var(--color-ink) 5.5%, transparent) 0 2px, transparent 2px 11px)',
            }}
        >
            {bannerUrl && (
                <img
                    data-gallery-banner
                    src={bannerUrl}
                    alt=""
                    fetchPriority="high"
                    className="absolute inset-0 h-full w-full object-cover"
                    style={{
                        objectPosition: `${position.x}% ${position.y}%`,
                        transform: `scale(${zoom})`,
                    }}
                />
            )}

            <div
                className="absolute inset-0"
                style={{
                    background:
                        'linear-gradient(to top, color-mix(in srgb, var(--color-shade) 62%, transparent) 0%, color-mix(in srgb, var(--color-shade) 18%, transparent) 45%, transparent 75%)',
                }}
            />

            {canEdit && (
                <button
                    data-gallery-edit
                    type="button"
                    onClick={onEdit}
                    className="absolute top-3.5 right-3.5 z-20 flex cursor-pointer items-center gap-2 rounded-full border border-cream/40 bg-shade/40 px-4.5 py-2.5 text-[13px] whitespace-nowrap text-cream backdrop-blur-[10px] transition-colors hover:border-cream/75 hover:bg-shade/65 sm:top-5 sm:right-6 lg:right-10"
                >
                    <svg
                        width="14"
                        height="14"
                        viewBox="0 0 16 16"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="1.4"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        aria-hidden="true"
                    >
                        <path d="M10.6 2.6l2.8 2.8L6 12.8l-3.2.4.4-3.2 7.4-7.4z" />
                    </svg>
                    Modifier la galerie
                </button>
            )}

            <div className="relative flex w-full flex-col items-center gap-3.5 px-5 pb-7 text-center sm:px-10 lg:px-16 lg:pb-12">
                <h1 className="font-serif text-[clamp(34px,7vw,68px)] leading-none tracking-tight text-cream text-pretty">
                    {title}
                </h1>
                {subtitle && <div className="text-xs tracking-[0.18em] text-cream/80 uppercase">{subtitle}</div>}
            </div>
        </div>
    );
}
```

If `bg-shade` / `text-cream` opacity modifiers do not resolve, check the token
names in `resources/css/app.css` and use `color-mix(in srgb, var(--color-shade) 40%, transparent)`
inline, as the surrounding gradients already do.

- [ ] **Step 6: Wire the page temporarily**

In `resources/js/pages/party/gallery.tsx`, extend the `Party` interface with

```tsx
    banner_position_x: number;
    banner_position_y: number;
    banner_zoom: number;
```

and `Props` with `canEdit: boolean;`, take `canEdit` in the signature, and pass
the new hero props. The editor arrives in Task 8; for now `onEdit` is a no-op
placeholder that Task 8 replaces:

```tsx
            <GalleryHero
                title={title}
                subtitle={party.description}
                bannerUrl={party.banner_url}
                position={{ x: party.banner_position_x, y: party.banner_position_y }}
                zoom={party.banner_zoom}
                canEdit={canEdit}
                onEdit={() => setEditorOpen(true)}
            />
```

with, next to the other state:

```tsx
    const [editorOpen, setEditorOpen] = useState(false);
```

- [ ] **Step 7: Build and eyeball**

```bash
vendor/bin/sail npm run build
```

Expected: no TypeScript or Vite error.

- [ ] **Step 8: Commit**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add app/Http/Resources/PartyResource.php resources/js tests/Feature/GalleryTest.php
git commit -m "feat(gallery): frame the hero banner and offer the host an edit button"
```

---

## Task 6: The crop hook

**Files:**
- Create: `resources/js/hooks/use-cover-crop.ts`

**Interfaces:**
- Consumes: nothing.
- Produces:

```ts
export interface CoverCrop { x: number; y: number; zoom: number }
export const DEFAULT_CROP: CoverCrop
export default function useCoverCrop(initial: CoverCrop): {
    crop: CoverCrop;
    setCrop: (crop: CoverCrop) => void;
    setZoom: (zoom: number) => void;
    reset: () => void;
    frameRef: RefObject<HTMLDivElement | null>;
    onImageLoad: (event: SyntheticEvent<HTMLImageElement>) => void;
    onPointerDown: (event: ReactPointerEvent<HTMLDivElement>) => void;
    draggable: boolean;
}
```

- [ ] **Step 1: Write the hook**

There is no unit-test harness for hooks in this project — the behaviour is
covered by the browser test in Task 10. Create
`resources/js/hooks/use-cover-crop.ts`:

```ts
import { useCallback, useRef, useState, type PointerEvent as ReactPointerEvent, type SyntheticEvent } from 'react';

export interface CoverCrop {
    /** Horizontal `object-position`, in percent. */
    x: number;
    /** Vertical `object-position`, in percent. */
    y: number;
    /** Applied on top of the `cover` fit, 1 to 3. */
    zoom: number;
}

export const DEFAULT_CROP: CoverCrop = { x: 50, y: 50, zoom: 1 };

interface NaturalSize {
    width: number;
    height: number;
}

const clamp = (value: number, min: number, max: number) => Math.min(max, Math.max(min, value));

/**
 * Drive the cover framing from a pointer drag over a fixed frame.
 *
 * The image is laid out `cover`, then scaled by the zoom. The overflow in each
 * axis is what the drag actually pans across, so a drag of `dx` pixels is worth
 * `-dx / overflow * 100` percentage points — which makes the image follow the
 * pointer exactly, and makes the 0–100 clamp land precisely on the edges of the
 * picture. An axis with no overflow cannot pan and stays centred.
 */
export default function useCoverCrop(initial: CoverCrop) {
    const [crop, setCrop] = useState<CoverCrop>(initial);
    const frameRef = useRef<HTMLDivElement | null>(null);
    const naturalRef = useRef<NaturalSize | null>(null);

    const overflow = useCallback((zoom: number) => {
        const frame = frameRef.current;
        const natural = naturalRef.current;

        if (!frame || !natural || natural.width === 0 || natural.height === 0) {
            return { x: 0, y: 0 };
        }

        const { width, height } = frame.getBoundingClientRect();
        const cover = Math.max(width / natural.width, height / natural.height);
        const renderedWidth = natural.width * cover * zoom;
        const renderedHeight = natural.height * cover * zoom;

        return {
            x: Math.max(0, renderedWidth - width),
            y: Math.max(0, renderedHeight - height),
        };
    }, []);

    const onImageLoad = useCallback((event: SyntheticEvent<HTMLImageElement>) => {
        naturalRef.current = {
            width: event.currentTarget.naturalWidth,
            height: event.currentTarget.naturalHeight,
        };
    }, []);

    const onPointerDown = useCallback(
        (event: ReactPointerEvent<HTMLDivElement>) => {
            if (naturalRef.current === null) {
                return;
            }

            const element = event.currentTarget;
            const startX = event.clientX;
            const startY = event.clientY;
            const start = crop;
            const span = overflow(crop.zoom);

            if (span.x === 0 && span.y === 0) {
                return;
            }

            element.setPointerCapture(event.pointerId);

            const move = (moveEvent: PointerEvent) => {
                const dx = moveEvent.clientX - startX;
                const dy = moveEvent.clientY - startY;

                setCrop({
                    ...start,
                    x: span.x === 0 ? 50 : clamp(start.x - (dx / span.x) * 100, 0, 100),
                    y: span.y === 0 ? 50 : clamp(start.y - (dy / span.y) * 100, 0, 100),
                });
            };

            const up = () => {
                element.releasePointerCapture(event.pointerId);
                element.removeEventListener('pointermove', move);
                element.removeEventListener('pointerup', up);
                element.removeEventListener('pointercancel', up);
            };

            element.addEventListener('pointermove', move);
            element.addEventListener('pointerup', up);
            element.addEventListener('pointercancel', up);
        },
        [crop, overflow],
    );

    const setZoom = useCallback((zoom: number) => {
        setCrop((current) => ({ ...current, zoom }));
    }, []);

    const reset = useCallback(() => {
        naturalRef.current = null;
        setCrop(DEFAULT_CROP);
    }, []);

    return { crop, setCrop, setZoom, reset, frameRef, onImageLoad, onPointerDown };
}
```

- [ ] **Step 2: Type-check**

```bash
vendor/bin/sail npm run build
```

Expected: no TypeScript error.

- [ ] **Step 3: Commit**

```bash
git add resources/js/hooks/use-cover-crop.ts
git commit -m "feat(gallery): add the cover framing hook"
```

---

## Task 7: The cropper component

**Files:**
- Create: `resources/js/components/party/cover-cropper.tsx`

**Interfaces:**
- Consumes: `useCoverCrop`, `CoverCrop` from Task 6.
- Produces: `CoverCropper` with props `{ src: string | null; crop: CoverCrop; frameRef; onImageLoad; onPointerDown; onZoomChange: (zoom: number) => void; onReset: () => void; onPick: (file: File) => void }`.

- [ ] **Step 1: Write the component**

`resources/js/components/party/cover-cropper.tsx`:

```tsx
import type { PointerEvent as ReactPointerEvent, RefObject, SyntheticEvent } from 'react';

import type { CoverCrop } from '@/hooks/use-cover-crop';

interface CoverCropperProps {
    src: string | null;
    crop: CoverCrop;
    frameRef: RefObject<HTMLDivElement | null>;
    onImageLoad: (event: SyntheticEvent<HTMLImageElement>) => void;
    onPointerDown: (event: ReactPointerEvent<HTMLDivElement>) => void;
    onZoomChange: (zoom: number) => void;
    onReset: () => void;
    onPick: (file: File) => void;
}

/**
 * The 16:7 frame is indicative, not exact: the hero is 62vh tall and its ratio
 * follows the viewport. The "Zone visible" label says as much.
 */
export default function CoverCropper({
    src,
    crop,
    frameRef,
    onImageLoad,
    onPointerDown,
    onZoomChange,
    onReset,
    onPick,
}: CoverCropperProps) {
    return (
        <div className="flex flex-col gap-3">
            <div className="flex flex-wrap items-baseline justify-between gap-3">
                <span className="text-[11px] tracking-[0.16em] text-ink/55 uppercase">Photo de couverture</span>
                <span className="text-xs text-ink/45">Glissez l'image pour cadrer</span>
            </div>

            <div
                ref={frameRef}
                data-cover-frame
                onPointerDown={onPointerDown}
                className="relative aspect-[16/7] touch-none overflow-hidden rounded-[3px] bg-placeholder select-none"
                style={{ cursor: src === null ? 'default' : 'grab' }}
            >
                {src !== null && (
                    <img
                        src={src}
                        alt=""
                        onLoad={onImageLoad}
                        draggable={false}
                        className="pointer-events-none absolute inset-0 h-full w-full object-cover"
                        style={{
                            objectPosition: `${crop.x}% ${crop.y}%`,
                            transform: `scale(${crop.zoom})`,
                        }}
                    />
                )}

                <div className="pointer-events-none absolute inset-0 shadow-[inset_0_0_0_1px_rgba(253,252,250,0.5)]">
                    <div className="absolute inset-0 mx-[33.33%] border-x border-cream/20" />
                    <div className="absolute inset-0 my-[33.33%] border-y border-cream/20" />
                </div>

                {src === null && (
                    <div className="pointer-events-none absolute inset-0 flex items-center justify-center text-xs tracking-[0.14em] text-ink/40 uppercase">
                        Aucune photo de couverture
                    </div>
                )}

                <div className="pointer-events-none absolute bottom-2.5 left-2.5 rounded-full bg-shade/50 px-2.5 py-1 text-[10px] tracking-[0.1em] text-cream uppercase">
                    Zone visible
                </div>
            </div>

            <div className="flex items-center gap-3.5">
                <span className="text-xs whitespace-nowrap text-ink/55">Zoom</span>
                <input
                    type="range"
                    min={1}
                    max={3}
                    step={0.02}
                    value={crop.zoom}
                    disabled={src === null}
                    onChange={(event) => onZoomChange(Number(event.target.value))}
                    className="flex-1 accent-ink"
                    aria-label="Zoom de la couverture"
                />
                <button
                    type="button"
                    onClick={onReset}
                    disabled={src === null}
                    className="cursor-pointer rounded-full border border-ink/15 px-3 py-1.5 text-xs whitespace-nowrap text-ink transition-colors hover:border-ink disabled:cursor-default disabled:opacity-40"
                >
                    Recadrer
                </button>
            </div>

            <div className="flex flex-wrap gap-2 pt-0.5">
                <label className="cursor-pointer rounded-full border border-ink/20 px-4.5 py-2.5 text-[13px] whitespace-nowrap transition-colors hover:border-ink">
                    Importer une photo
                    <input
                        type="file"
                        accept="image/*"
                        className="hidden"
                        onChange={(event) => {
                            const file = event.target.files?.[0];

                            if (file) {
                                onPick(file);
                            }

                            event.target.value = '';
                        }}
                    />
                </label>
            </div>
        </div>
    );
}
```

Clearing `event.target.value` after a pick lets the host re-select the same file
after a reset, which the browser would otherwise swallow as "no change".

- [ ] **Step 2: Type-check**

```bash
vendor/bin/sail npm run build
```

Expected: no TypeScript error.

- [ ] **Step 3: Commit**

```bash
git add resources/js/components/party/cover-cropper.tsx
git commit -m "feat(gallery): add the cover cropper"
```

---

## Task 8: The editor modal

**Files:**
- Create: `resources/js/components/party/gallery-editor.tsx`
- Modify: `resources/js/pages/party/gallery.tsx`

**Interfaces:**
- Consumes: `CoverCropper` (Task 7), `useCoverCrop` / `DEFAULT_CROP` (Task 6), the `party.metadata.update` route (Task 4), `GalleryToast` (already present).
- Produces: `GalleryEditor` with props `{ open: boolean; onClose: () => void; partyName: string; title: string; subtitle: string | null; bannerUrl: string | null; crop: CoverCrop; onSaved: () => void }`.

- [ ] **Step 1: Write the modal**

`resources/js/components/party/gallery-editor.tsx`:

```tsx
import { useForm } from '@inertiajs/react';
import { useEffect, useRef, useState, type FormEvent } from 'react';

import CoverCropper from '@/components/party/cover-cropper';
import useCoverCrop, { DEFAULT_CROP, type CoverCrop } from '@/hooks/use-cover-crop';

const TITLE_LIMIT = 60;
const SUBTITLE_LIMIT = 90;

interface GalleryEditorProps {
    open: boolean;
    onClose: () => void;
    partyName: string;
    title: string;
    subtitle: string | null;
    bannerUrl: string | null;
    crop: CoverCrop;
    onSaved: () => void;
}

export default function GalleryEditor({
    open,
    onClose,
    partyName,
    title,
    subtitle,
    bannerUrl,
    crop,
    onSaved,
}: GalleryEditorProps) {
    const form = useForm({
        display_name: title,
        description: subtitle ?? '',
        banner: null as File | null,
        remove_banner: false as boolean,
        banner_position_x: crop.x,
        banner_position_y: crop.y,
        banner_zoom: crop.zoom,
    });

    const cropper = useCoverCrop(crop);
    const [preview, setPreview] = useState<string | null>(null);
    const objectUrl = useRef<string | null>(null);

    /** Reload the draft from the stored values every time the modal opens. */
    useEffect(() => {
        if (!open) {
            return;
        }

        form.setDefaults({
            display_name: title,
            description: subtitle ?? '',
            banner: null,
            remove_banner: false,
            banner_position_x: crop.x,
            banner_position_y: crop.y,
            banner_zoom: crop.zoom,
        });
        form.reset();
        form.clearErrors();
        cropper.setCrop(crop);
        setPreview(null);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [open]);

    useEffect(
        () => () => {
            if (objectUrl.current !== null) {
                URL.revokeObjectURL(objectUrl.current);
            }
        },
        [],
    );

    const onPick = (file: File) => {
        if (objectUrl.current !== null) {
            URL.revokeObjectURL(objectUrl.current);
        }

        objectUrl.current = URL.createObjectURL(file);

        form.setData('banner', file);
        form.setData('remove_banner', false);
        setPreview(objectUrl.current);
        cropper.reset();
    };

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();

        form.transform((data) => ({
            ...data,
            _method: 'put',
            banner_position_x: cropper.crop.x,
            banner_position_y: cropper.crop.y,
            banner_zoom: cropper.crop.zoom,
        }));

        // Inertia switches to FormData as soon as a file is present, and
        // multipart cannot carry a PUT, hence the spoofed method above.
        form.post(`/${encodeURIComponent(partyName)}/metadata`, {
            forceFormData: true,
            preserveScroll: true,
            onSuccess: () => {
                onSaved();
                onClose();
            },
        });
    };

    const source = preview ?? (form.data.remove_banner ? null : bannerUrl);
    const subtitleLeft = SUBTITLE_LIMIT - form.data.description.length;

    return (
        <>
            <div
                onClick={onClose}
                className="fixed inset-0 z-50 bg-shade/50 backdrop-blur-[3px] transition-opacity duration-250"
                style={{ opacity: open ? 1 : 0, pointerEvents: open ? 'auto' : 'none' }}
            />

            <form
                data-gallery-editor
                onSubmit={submit}
                role="dialog"
                aria-label="Modifier la galerie"
                aria-hidden={!open}
                className="fixed top-1/2 left-1/2 z-51 flex max-h-[min(88vh,860px)] w-[min(620px,calc(100vw-24px))] flex-col rounded-md bg-surface shadow-[0_30px_80px_rgba(20,18,15,0.35)] transition-all duration-250"
                style={{
                    opacity: open ? 1 : 0,
                    pointerEvents: open ? 'auto' : 'none',
                    transform: `translate(-50%, -50%) scale(${open ? 1 : 0.97})`,
                }}
            >
                <div className="flex items-center justify-between gap-4 border-b border-ink/10 px-5 pt-5 pb-4 lg:px-7">
                    <span className="min-w-0 font-serif text-[22px] leading-tight">Modifier la galerie</span>
                    <button
                        type="button"
                        onClick={onClose}
                        aria-label="Fermer"
                        className="flex h-8.5 w-8.5 flex-none cursor-pointer items-center justify-center rounded-full border border-ink/15 text-ink transition-colors hover:border-ink"
                    >
                        <svg width="13" height="13" viewBox="0 0 14 14" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" aria-hidden="true">
                            <path d="M2.5 2.5l9 9m0-9l-9 9" />
                        </svg>
                    </button>
                </div>

                <div className="flex flex-col gap-6.5 overflow-y-auto px-5 py-5 lg:px-7 lg:py-7">
                    <label className="flex flex-col gap-2">
                        <span className="text-[11px] tracking-[0.16em] text-ink/55 uppercase">Nom de la galerie</span>
                        <input
                            value={form.data.display_name}
                            onChange={(event) => form.setData('display_name', event.target.value)}
                            maxLength={TITLE_LIMIT}
                            placeholder="Camille & Antoine"
                            className="w-full rounded-[3px] border border-ink/15 bg-cream px-3.5 py-3 font-serif text-2xl text-ink outline-none focus:border-ink"
                        />
                        {form.errors.display_name && (
                            <span className="text-xs text-red-600">{form.errors.display_name}</span>
                        )}
                    </label>

                    <label className="flex flex-col gap-2">
                        <span className="text-[11px] tracking-[0.16em] text-ink/55 uppercase">Description</span>
                        <input
                            value={form.data.description}
                            onChange={(event) => form.setData('description', event.target.value)}
                            maxLength={SUBTITLE_LIMIT}
                            placeholder="Date · Lieu"
                            className="w-full rounded-[3px] border border-ink/15 bg-cream px-3.5 py-3 text-sm text-ink outline-none focus:border-ink"
                        />
                        <span className="text-xs text-ink/45">
                            Affichée sous le titre, en petites capitales. {subtitleLeft} caractères restants.
                        </span>
                        {form.errors.description && (
                            <span className="text-xs text-red-600">{form.errors.description}</span>
                        )}
                    </label>

                    <div className="flex flex-col gap-3">
                        <CoverCropper
                            src={source}
                            crop={cropper.crop}
                            frameRef={cropper.frameRef}
                            onImageLoad={cropper.onImageLoad}
                            onPointerDown={cropper.onPointerDown}
                            onZoomChange={cropper.setZoom}
                            onReset={cropper.reset}
                            onPick={onPick}
                        />
                        {form.errors.banner && <span className="text-xs text-red-600">{form.errors.banner}</span>}
                    </div>
                </div>

                <div className="flex items-center justify-end gap-2.5 border-t border-ink/10 bg-surface/70 px-5 py-4 lg:px-7">
                    <button
                        type="button"
                        onClick={onClose}
                        className="cursor-pointer rounded-full px-5 py-3 text-sm text-ink/55 transition-colors hover:text-ink"
                    >
                        Annuler
                    </button>
                    <button
                        type="submit"
                        disabled={form.processing}
                        className="cursor-pointer rounded-full bg-ink px-6 py-3 text-sm text-cream transition-opacity disabled:opacity-60"
                    >
                        Enregistrer
                    </button>
                </div>
            </form>
        </>
    );
}
```

- [ ] **Step 2: Wire it into the page**

In `resources/js/pages/party/gallery.tsx`, import the editor and render it just
before `<GalleryToast …/>`:

```tsx
import GalleryEditor from '@/components/party/gallery-editor';
```

```tsx
            {canEdit && (
                <GalleryEditor
                    open={editorOpen}
                    onClose={() => setEditorOpen(false)}
                    partyName={party.name}
                    title={party.display_name ?? ''}
                    subtitle={party.description}
                    bannerUrl={party.banner_url}
                    crop={{
                        x: party.banner_position_x,
                        y: party.banner_position_y,
                        zoom: party.banner_zoom,
                    }}
                    onSaved={() => flash('Galerie mise à jour')}
                />
            )}
```

`title` passes `display_name` rather than the resolved `title` constant, so an
empty field means "fall back to the party name" rather than pinning the fallback
into the column.

- [ ] **Step 3: Build**

```bash
vendor/bin/sail npm run build
```

Expected: no TypeScript or Vite error.

- [ ] **Step 4: Commit**

```bash
git add resources/js
git commit -m "feat(gallery): let the host edit the gallery from a modal"
```

---

## Task 9: The edit link in the administration

**Files:**
- Modify: `app/Http/Controllers/Admin/PartyController.php`, `routes/web.php`, `resources/js/pages/admin/parties/index.tsx`
- Test: `tests/Feature/AdminPartiesTest.php`

**Interfaces:**
- Consumes: `Party::hostEditUrl()` from Task 1.
- Produces: route `admin.parties.host-token` at `POST admin/parties/{party}/host-token`, and a `host_edit_url: string` field on each entry of the `editableParties` prop.

- [ ] **Step 1: Write the failing tests**

Append to `tests/Feature/AdminPartiesTest.php`, following the file's existing
way of authenticating an admin:

```php
// ── Host edit link ────────────────────────────────────────────────────────────

it('exposes the host edit link to the parties index', function () {
    $user = User::factory()->create();
    $party = Party::factory()->create();

    $this->actingAs($user)
        ->get(route('admin.parties.index'))
        ->assertInertia(fn ($page) => $page
            ->where('editableParties.0.host_edit_url', $party->hostEditUrl())
        );
});

it('regenerates the host token', function () {
    $user = User::factory()->create();
    $party = Party::factory()->create();
    $before = $party->host_token;

    $this->actingAs($user)
        ->post(route('admin.parties.host-token', $party))
        ->assertRedirect(route('admin.parties.index'));

    expect($party->fresh()->host_token)->not->toBe($before)->toHaveLength(48);
});

it('stops the old link from opening a host session once regenerated', function () {
    $user = User::factory()->create();
    $party = Party::factory()->create();
    $stale = $party->host_token;

    $this->actingAs($user)->post(route('admin.parties.host-token', $party));

    $this->get(route('party.gallery', $party).'?token='.$stale)
        ->assertRedirect(route('party.gallery', $party))
        ->assertSessionMissing('party_host.'.$party->id);
});

it('refuses to regenerate a host token for a guest', function () {
    $party = Party::factory()->create();

    $this->post(route('admin.parties.host-token', $party))->assertRedirect(route('login'));
});
```

Check the top of the file for how the other tests build their admin user; reuse
that exactly rather than the `User::factory()->create()` above if it differs.

- [ ] **Step 2: Run the tests to verify they fail**

```bash
vendor/bin/sail artisan test --compact --filter=AdminPartiesTest
```

Expected: FAIL — no `admin.parties.host-token` route, no `host_edit_url`.

- [ ] **Step 3: Expose the link and the action**

In `app/Http/Controllers/Admin/PartyController.php`, add `host_token` to the
column list of `editableParties` and the URL to the mapped array:

```php
            'editableParties' => Party::query()
                ->get(['id', 'name', 'host_token', 'user_id', 'expire_at', 'display_name', 'description', 'banner_path'])
                ->map(fn (Party $party): array => [
                    'id' => $party->id,
                    'name' => $party->name,
                    'user_id' => $party->user_id,
                    'expire_at' => $this->expiryForInput($party),
                    'display_name' => $party->display_name,
                    'description' => $party->description,
                    'banner_url' => $party->bannerUrl(),
                    'host_edit_url' => $party->hostEditUrl(),
                    'route_key' => $party->name,
                ])
                ->values(),
```

and add the action next to `update()`:

```php
    /**
     * Cut the party's current edit link and mint a new one.
     *
     * Host sessions already open survive, since they only carry a boolean.
     * That is acceptable: regeneration exists to kill a link that leaked to a
     * third party, not to evict a host from their own browser.
     */
    public function regenerateHostToken(Party $party): RedirectResponse
    {
        $party->forceFill(['host_token' => Str::random(48)])->save();

        return to_route('admin.parties.index');
    }
```

`Str` is already imported in this file.

- [ ] **Step 4: Add the route**

In `routes/web.php`, inside the `admin` group, **above** the
`Route::resource('parties', …)` line so the resource's `{party}` wildcard does
not swallow it:

```php
    Route::post('parties/{party}/host-token', [AdminPartyController::class, 'regenerateHostToken'])
        ->name('parties.host-token');
```

- [ ] **Step 5: Run the tests to verify they pass**

```bash
vendor/bin/sail artisan test --compact --filter=AdminPartiesTest
```

Expected: PASS.

- [ ] **Step 6: Add the block to the details dialog**

In `resources/js/pages/admin/parties/index.tsx` — **double quotes, no
semicolons**, this file's own style:

Add `host_edit_url: string` to the `editableParties` prop type. Add state next
to `selectedPartyBannerUrl`:

```tsx
    const [selectedPartyHostEditUrl, setSelectedPartyHostEditUrl] = useState<string | null>(null)
    const [hostLinkCopied, setHostLinkCopied] = useState(false)
    const hostTokenForm = useForm({})
```

In `openPartyDetails`, alongside `setSelectedPartyBannerUrl(...)`:

```tsx
        setSelectedPartyHostEditUrl(editableParty.host_edit_url)
        setHostLinkCopied(false)
```

and clear it in the dialog's `onOpenChange` reset block:

```tsx
                        setSelectedPartyHostEditUrl(null)
                        setHostLinkCopied(false)
```

Then, inside the edit `<form>` after the expiry field, add:

```tsx
                        <div className="space-y-2">
                            <Label htmlFor="edit-party-host-link">Host edit link</Label>
                            <div className="flex gap-2">
                                <Input
                                    id="edit-party-host-link"
                                    readOnly
                                    value={selectedPartyHostEditUrl ?? ""}
                                    onFocus={(event) => event.target.select()}
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => {
                                        if (selectedPartyHostEditUrl) {
                                            void navigator.clipboard.writeText(selectedPartyHostEditUrl)
                                            setHostLinkCopied(true)
                                        }
                                    }}
                                >
                                    {hostLinkCopied ? "Copied" : "Copy"}
                                </Button>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Send this to the host so they can edit the gallery title, description
                                and cover without an account.
                            </p>
                            <Button
                                type="button"
                                variant="ghost"
                                className="px-0 text-xs text-destructive"
                                disabled={hostTokenForm.processing}
                                onClick={() => {
                                    if (!selectedPartyRouteKey) {
                                        return
                                    }

                                    if (!window.confirm("Regenerate the link? The one already sent will stop working.")) {
                                        return
                                    }

                                    hostTokenForm.post(
                                        `/admin/parties/${encodeURIComponent(selectedPartyRouteKey)}/host-token`,
                                        { onSuccess: () => setDetailsModalOpen(false) },
                                    )
                                }}
                            >
                                Regenerate the link
                            </Button>
                        </div>
```

- [ ] **Step 7: Build and commit**

```bash
vendor/bin/sail npm run build
vendor/bin/sail bin pint --dirty --format agent
git add app/Http/Controllers/Admin/PartyController.php routes/web.php resources/js/pages/admin/parties/index.tsx tests/Feature/AdminPartiesTest.php
git commit -m "feat(admin): surface and regenerate the host edit link"
```

---

## Task 10: The browser test

**Files:**
- Create: `tests/Web/GalleryEditorTest.php`

**Interfaces:**
- Consumes: everything from Tasks 1–8.
- Produces: nothing further.

- [ ] **Step 1: Write the test**

`tests/Web/GalleryEditorTest.php`:

```php
<?php

use App\Models\Party;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
});

// ── Visibility ────────────────────────────────────────────────────────────────

it('hides the edit button from a plain visitor', function () {
    $party = Party::factory()->create();

    $page = visit(route('party.gallery', $party));

    $page->assertPresent('[data-gallery-hero]')
        ->assertMissing('[data-gallery-edit]')
        ->assertNoJavaScriptErrors();
});

it('shows the edit button to whoever opens the tokenised link', function () {
    $party = Party::factory()->create();

    $page = visit(route('party.gallery', $party).'?token='.$party->host_token);

    $page->assertPresent('[data-gallery-edit]')
        ->assertSee('Modifier la galerie')
        ->assertNoJavaScriptErrors();
});

it('shows no edit button for a wrong token', function () {
    $party = Party::factory()->create();

    $page = visit(route('party.gallery', $party).'?token=wrong');

    $page->assertMissing('[data-gallery-edit]')
        ->assertNoJavaScriptErrors();
});

// ── Editing ───────────────────────────────────────────────────────────────────

it('saves a new title from the editor', function () {
    $party = Party::factory()->create(['name' => 'noces-de-juin']);

    $page = visit(route('party.gallery', $party).'?token='.$party->host_token);

    $page->click('[data-gallery-edit]')
        ->assertSee('Modifier la galerie')
        ->type('[data-gallery-editor] input[placeholder="Camille & Antoine"]', 'Camille & Antoine')
        ->click('[data-gallery-editor] button[type="submit"]')
        ->assertSeeIn('[data-gallery-hero]', 'Camille & Antoine')
        ->assertNoJavaScriptErrors();

    expect($party->fresh()->display_name)->toBe('Camille & Antoine');
});
```

- [ ] **Step 2: Build the assets, then run**

```bash
vendor/bin/sail npm run build
vendor/bin/sail artisan test --compact --filter=GalleryEditorTest
```

Expected: PASS. These tests are known to flake on 5 s timeouts — **re-run once**
before investigating any failure.

- [ ] **Step 3: Commit**

```bash
git add tests/Web/GalleryEditorTest.php
git commit -m "test(gallery): cover the host editor in a browser"
```

---

## Task 11: Format, full suite, tick the plan

**Files:**
- Modify: `docs/superpowers/plans/2026-08-29-editeur-hote-galerie.md`

- [ ] **Step 1: Format every touched PHP file**

```bash
vendor/bin/sail bin pint --format agent
```

- [ ] **Step 2: Run the whole suite**

```bash
vendor/bin/sail artisan test --compact
```

Expected: PASS. Re-run once on a `tests/Web/` timeout before investigating.

- [ ] **Step 3: Tick every checkbox in this plan and note any deviation**

Add a `## Deviations` section at the bottom recording anything implemented
differently from the plan and why.

- [ ] **Step 4: Commit**

```bash
git add -A
git commit -m "docs(gallery): tick the host editor plan and record its deviations"
```

---

## Deviations

_(filled in at Task 11)_
