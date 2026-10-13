# Livre d'or audio — Lot 1 : fondations, modèle et adressage

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Poser le socle du livre d'or audio — les modules activables sur une `Party`, l'adressage par uuid pour les événements sans sous-domaine, et les deux tables audio — sans construire aucune des deux interfaces.

**Architecture:** Une `Party` reste l'entité pivot d'un événement et gagne deux drapeaux de module. Elle porte désormais deux uuid publics **distincts** : `gallery_uuid` pour la future surface invités, `client_uuid` pour l'espace privé du client. Le sous-domaine (`name`) devient facultatif et ne concerne plus que les événements « Galerie en ligne ». Deux tables, `audio_greetings` (une ligne par party) et `audio_messages`, portent le contenu audio.

**Tech Stack:** Laravel 12, Inertia 2 + React 19, Pest 4, MySQL 8.4, disque `s3` (RustFS en local).

**Spec:** `docs/superpowers/specs/2026-09-17-livre-or-audio.md`

**Issues:** épic #51, lot #52, sous-tâches #53 à #57.

## Global Constraints

- **Toutes les commandes passent par Sail.** `vendor/bin/sail artisan …`, `vendor/bin/sail bin pint …`, `vendor/bin/sail composer …`. Jamais `php`/`artisan` nus.
- **Tests ciblés :** `vendor/bin/sail artisan test --compact --filter=…`. Suite complète à la tâche 6 seulement.
- **Après toute modification de PHP :** `vendor/bin/sail bin pint --dirty --format agent`.
- **PHP :** types de retour explicites partout, accolades toujours, promotion de propriétés dans les constructeurs, blocs PHPDoc plutôt que commentaires en ligne, `Model::query()` plutôt que `DB::`.
- **Les casts vont dans la méthode `casts()`**, pas dans une propriété `$casts` — convention du projet.
- **Les commentaires de code du projet sont en anglais**, les textes destinés aux visiteurs en français. Respecter cette séparation.
- **Les deux uuid sont distincts et sans lien dérivable.** `gallery_uuid` circule en QR code, il est public de fait ; `client_uuid` ouvre l'espace privé du couple. Ne jamais dériver l'un de l'autre, ne jamais exposer `client_uuid` sur une surface invités.
- **Noms de party réservés :** `audiobook` et `pictures`. `/{party}` est un préfixe attrape-tout.
- **Statuts de l'annonce :** `draft`, `pending`, `validated`, `rejected` — valeurs exactes, les tests s'appuient dessus.
- **Une party dont le module est inactif renvoie 404, jamais 403.**
- **Aucune interface dans ce lot.** La page `/audiobook/{uuid}` est un gabarit vide que le lot 3 remplira.

---

## File Structure

**Créés**

| Fichier | Responsabilité |
| --- | --- |
| `database/migrations/2026_09_17_100000_add_audio_guestbook_columns_to_parties_table.php` | Modules, uuid, e-mail client, `event_at`, colonnes de relance ; `name` devient nullable |
| `database/migrations/2026_09_17_100001_create_audio_greetings_table.php` | L'annonce du client |
| `database/migrations/2026_09_17_100002_create_audio_messages_table.php` | Les messages des invités |
| `app/Enums/GreetingStatus.php` | Les quatre statuts de l'annonce |
| `app/Models/AudioGreeting.php` | L'annonce, son URL signée, la purge de ses fichiers |
| `app/Models/AudioMessage.php` | Un message d'invité, son URL signée, la purge de son fichier |
| `database/factories/AudioGreetingFactory.php` | États par statut |
| `database/factories/AudioMessageFactory.php` | |
| `app/Http/Middleware/EnsureAudioGuestbook.php` | 404 si le module est inactif |
| `app/Http/Controllers/AudiobookController.php` | Point d'entrée de l'espace client |
| `resources/js/pages/party/audiobook.tsx` | Gabarit vide, rempli au lot 3 |
| `tests/Feature/AudioGuestbookAccessTest.php` | Résolution par uuid, 404, limiteur |
| `tests/Feature/AudioGreetingTest.php` | Modèle, statuts, purge des fichiers |
| `tests/Feature/AudioMessageTest.php` | Modèle, ordre, purge du fichier |

**Modifiés**

| Fichier | Changement |
| --- | --- |
| `app/Models/Party.php` | `fillable`, `casts()`, frappe des uuid, relations audio, URL publiques tolérant l'absence de sous-domaine, noms réservés |
| `database/factories/PartyFactory.php` | Valeurs par défaut et états des modules |
| `app/Http/Requests/StorePartyRequest.php` | `name` facultatif, noms réservés |
| `app/Http/Requests/UpdateAdminPartyRequest.php` | `name` facultatif, noms réservés |
| `app/Services/PartyPosterGenerator.php` | Refuse une party sans sous-domaine |
| `app/Http/Controllers/Admin/PartyController.php` | `host_edit_url` devient nullable |
| `routes/web.php` | `/audiobook/{uuid}`, **déclarée avant** le groupe `{party}` |
| `bootstrap/app.php` | Alias `audiobook` du nouveau middleware |
| `app/Providers/AppServiceProvider.php` | Limiteur `audiobook-entry` |
| `tests/Feature/PartyTest.php` | Cas des nouvelles colonnes et des URL sans sous-domaine |
| `tests/Feature/AdminPartiesTest.php` | Noms réservés refusés |

---

## Task 1: Migration `parties` — modules, uuid et métadonnées client

Issue #53.

**Files:**
- Create: `database/migrations/2026_09_17_100000_add_audio_guestbook_columns_to_parties_table.php`
- Modify: `app/Models/Party.php`
- Modify: `database/factories/PartyFactory.php`
- Test: `tests/Feature/PartyTest.php`

**Interfaces:**
- Produces: colonnes `gallery_uuid`, `client_uuid`, `has_gallery`, `has_audio_guestbook`, `client_email`, `event_at`, `greeting_reminder_sent_at`, `expiry_reminder_sent_at` ; `name` nullable. États de factory `withAudioGuestbook()`, `audioOnly()`, `withoutSubdomain()`, `eventAt(Carbon $date)`.

- [ ] **Step 1: Write the failing tests**

Ajouter à la fin de `tests/Feature/PartyTest.php` :

```php
// ── Modules & adressage ─────────────────────────────────────────────────────

it('mints two distinct uuids when a party is created', function () {
    $party = Party::factory()->create();

    expect($party->gallery_uuid)->toBeString()->not->toBeEmpty()
        ->and($party->client_uuid)->toBeString()->not->toBeEmpty()
        ->and($party->gallery_uuid)->not->toBe($party->client_uuid);
});

it('keeps the uuids it was given rather than minting new ones', function () {
    $party = Party::factory()->create([
        'gallery_uuid' => '11111111-1111-1111-1111-111111111111',
        'client_uuid' => '22222222-2222-2222-2222-222222222222',
    ]);

    expect($party->fresh())
        ->gallery_uuid->toBe('11111111-1111-1111-1111-111111111111')
        ->client_uuid->toBe('22222222-2222-2222-2222-222222222222');
});

it('enables the gallery module and nothing else by default', function () {
    $party = Party::factory()->create();

    expect($party->has_gallery)->toBeTrue()
        ->and($party->has_audio_guestbook)->toBeFalse();
});

it('casts the module flags as booleans', function () {
    $party = Party::factory()->audioOnly()->create();

    expect($party->fresh())
        ->has_gallery->toBeFalse()
        ->has_audio_guestbook->toBeTrue();
});

it('accepts a party without a subdomain name', function () {
    $party = Party::factory()->withoutSubdomain()->create();

    expect($party->fresh()->name)->toBeNull();
});

it('casts event_at and both reminder stamps as Carbon instances', function () {
    $party = Party::factory()->eventAt(now()->addMonth())->create([
        'greeting_reminder_sent_at' => now(),
        'expiry_reminder_sent_at' => now(),
    ]);

    expect($party->fresh())
        ->event_at->toBeInstanceOf(Carbon::class)
        ->greeting_reminder_sent_at->toBeInstanceOf(Carbon::class)
        ->expiry_reminder_sent_at->toBeInstanceOf(Carbon::class);
});

it('stores the client email', function () {
    $party = Party::factory()->create(['client_email' => 'camille@example.test']);

    expect($party->fresh()->client_email)->toBe('camille@example.test');
});
```

- [ ] **Step 2: Run the tests to verify they fail**

```
vendor/bin/sail artisan test --compact --filter=PartyTest
```

Attendu : ÉCHEC, colonne `gallery_uuid` inconnue.

- [ ] **Step 3: Write the migration**

`database/migrations/2026_09_17_100000_add_audio_guestbook_columns_to_parties_table.php` :

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
     * Modules, public identifiers and client metadata.
     *
     * The subdomain becomes optional: only the events sold as a published
     * gallery get one, the rest being addressed by uuid. Both uuids are
     * nullable in the schema so that existing rows can be backfilled, but the
     * model mints them on creation, so no row ever carries a null.
     */
    public function up(): void
    {
        Schema::table('parties', function (Blueprint $table): void {
            $table->string('name')->nullable()->change();
            $table->uuid('gallery_uuid')->nullable()->unique()->after('name');
            $table->uuid('client_uuid')->nullable()->unique()->after('gallery_uuid');
            $table->boolean('has_gallery')->default(true)->after('client_uuid');
            $table->boolean('has_audio_guestbook')->default(false)->after('has_gallery');
            $table->string('client_email')->nullable()->after('has_audio_guestbook');
            $table->dateTime('event_at')->nullable()->after('expire_at');
            $table->dateTime('greeting_reminder_sent_at')->nullable()->after('event_at');
            $table->dateTime('expiry_reminder_sent_at')->nullable()->after('greeting_reminder_sent_at');
        });

        Party::query()
            ->whereNull('client_uuid')
            ->each(function (Party $party): void {
                $party->forceFill([
                    'gallery_uuid' => (string) Str::uuid(),
                    'client_uuid' => (string) Str::uuid(),
                ])->save();
            });
    }

    /**
     * Rolling back fails if any party has been created without a subdomain in
     * the meantime: the column cannot go back to NOT NULL while it holds
     * nulls. That is the honest behaviour — the rollback would otherwise have
     * to invent a name for a party that never had one.
     */
    public function down(): void
    {
        Schema::table('parties', function (Blueprint $table): void {
            $table->dropUnique(['gallery_uuid']);
            $table->dropUnique(['client_uuid']);
            $table->dropColumn([
                'gallery_uuid',
                'client_uuid',
                'has_gallery',
                'has_audio_guestbook',
                'client_email',
                'event_at',
                'greeting_reminder_sent_at',
                'expiry_reminder_sent_at',
            ]);
            $table->string('name')->nullable(false)->change();
        });
    }
};
```

- [ ] **Step 4: Extend the `Party` model**

Dans `app/Models/Party.php`, ajouter aux `$fillable` :

```php
        'gallery_uuid',
        'client_uuid',
        'has_gallery',
        'has_audio_guestbook',
        'client_email',
        'event_at',
        'greeting_reminder_sent_at',
        'expiry_reminder_sent_at',
```

Ajouter aux `$attributes` par défaut, pour qu'une instance fraîche rapporte les mêmes valeurs que la base :

```php
        'has_gallery' => true,
        'has_audio_guestbook' => false,
```

Compléter `casts()` :

```php
            'event_at' => 'datetime',
            'greeting_reminder_sent_at' => 'datetime',
            'expiry_reminder_sent_at' => 'datetime',
            'has_gallery' => 'boolean',
            'has_audio_guestbook' => 'boolean',
```

Et dans `booting()`, à côté de la frappe de `host_token` :

```php
            $party->gallery_uuid ??= (string) Str::uuid();
            $party->client_uuid ??= (string) Str::uuid();
```

- [ ] **Step 5: Extend `PartyFactory`**

Ajouter à `definition()` :

```php
            'has_gallery' => true,
            'has_audio_guestbook' => false,
            'client_email' => null,
            'event_at' => null,
            'greeting_reminder_sent_at' => null,
            'expiry_reminder_sent_at' => null,
```

Et les états :

```php
    public function withAudioGuestbook(): static
    {
        return $this->state(fn (array $attributes) => [
            'has_audio_guestbook' => true,
        ]);
    }

    public function audioOnly(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => null,
            'has_gallery' => false,
            'has_audio_guestbook' => true,
        ]);
    }

    public function withoutSubdomain(): static
    {
        return $this->state(fn (array $attributes) => [
            'name' => null,
        ]);
    }

    public function eventAt(Carbon $date): static
    {
        return $this->state(fn (array $attributes) => [
            'event_at' => $date,
        ]);
    }
```

- [ ] **Step 6: Run the migration and the tests**

```
vendor/bin/sail artisan migrate
vendor/bin/sail artisan test --compact --filter=PartyTest
```

Attendu : SUCCÈS.

- [ ] **Step 7: Verify the rollback**

```
vendor/bin/sail artisan migrate:rollback --step=1
vendor/bin/sail artisan migrate
```

Attendu : les deux passent sans erreur. Un `down()` qui casse ne se découvre qu'en production.

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add database/migrations app/Models/Party.php database/factories/PartyFactory.php tests/Feature/PartyTest.php
git commit -m "feat(audio): ajoute les modules, les uuid publics et les métadonnées client à parties

Refs #53"
```

---

## Task 2: Tables, modèles et factories `audio_greetings` et `audio_messages`

Issue #54.

**Files:**
- Create: `database/migrations/2026_09_17_100001_create_audio_greetings_table.php`
- Create: `database/migrations/2026_09_17_100002_create_audio_messages_table.php`
- Create: `app/Enums/GreetingStatus.php`
- Create: `app/Models/AudioGreeting.php`
- Create: `app/Models/AudioMessage.php`
- Create: `database/factories/AudioGreetingFactory.php`
- Create: `database/factories/AudioMessageFactory.php`
- Test: `tests/Feature/AudioGreetingTest.php`
- Test: `tests/Feature/AudioMessageTest.php`

**Interfaces:**
- Consumes: `parties` de la tâche 1.
- Produces: `GreetingStatus::{Draft,Pending,Validated,Rejected}` (valeurs `draft`, `pending`, `validated`, `rejected`) ; `AudioGreeting::url(): ?string`, `AudioMessage::url(): string`, `AudioMessage::displayTitle(): string` ; états de factory `AudioGreetingFactory::{pending,validated,rejected}()`.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/AudioGreetingTest.php` :

```php
<?php

use App\Enums\GreetingStatus;
use App\Models\AudioGreeting;
use App\Models\Party;
use Illuminate\Support\Facades\Storage;

it('belongs to a party', function () {
    $party = Party::factory()->withAudioGuestbook()->create();
    $greeting = AudioGreeting::factory()->for($party)->create();

    expect($greeting->party->id)->toBe($party->id);
});

it('starts as a draft', function () {
    $greeting = AudioGreeting::factory()->create();

    expect($greeting->status)->toBe(GreetingStatus::Draft);
});

it('casts the status as an enum', function () {
    $greeting = AudioGreeting::factory()->validated()->create();

    expect($greeting->fresh()->status)->toBe(GreetingStatus::Validated);
});

it('carries the rejection reason when rejected', function () {
    $greeting = AudioGreeting::factory()->rejected('Son saturé')->create();

    expect($greeting->fresh())
        ->status->toBe(GreetingStatus::Rejected)
        ->rejection_reason->toBe('Son saturé');
});

it('has no playable url until the conversion has run', function () {
    $greeting = AudioGreeting::factory()->create(['mp3_path' => null]);

    expect($greeting->url())->toBeNull();
});

it('signs the url of its mp3 once converted', function () {
    $greeting = AudioGreeting::factory()->validated()->create();

    expect($greeting->url())->toBeString()->toContain($greeting->mp3_path);
});

it('deletes both files when the greeting is deleted', function () {
    Storage::fake('s3');

    $greeting = AudioGreeting::factory()->validated()->create();
    Storage::disk('s3')->put($greeting->original_path, 'original');
    Storage::disk('s3')->put($greeting->mp3_path, 'converted');

    $greeting->delete();

    Storage::disk('s3')->assertMissing($greeting->original_path);
    Storage::disk('s3')->assertMissing($greeting->mp3_path);
});

it('is reachable from its party as a single record', function () {
    $party = Party::factory()->withAudioGuestbook()->create();
    AudioGreeting::factory()->for($party)->create();

    expect($party->fresh()->greeting)->toBeInstanceOf(AudioGreeting::class);
});
```

`tests/Feature/AudioMessageTest.php` :

```php
<?php

use App\Models\AudioMessage;
use App\Models\Party;
use Illuminate\Support\Facades\Storage;

it('belongs to a party', function () {
    $party = Party::factory()->withAudioGuestbook()->create();
    $message = AudioMessage::factory()->for($party)->create();

    expect($message->party->id)->toBe($party->id);
});

it('falls back to its position when the client has not named it', function () {
    $message = AudioMessage::factory()->create(['title' => null, 'position' => 4]);

    expect($message->displayTitle())->toBe('Message 04');
});

it('uses the title the client gave it', function () {
    $message = AudioMessage::factory()->create(['title' => 'Mamie Jeanne']);

    expect($message->displayTitle())->toBe('Mamie Jeanne');
});

it('signs the url of its file', function () {
    $message = AudioMessage::factory()->create();

    expect($message->url())->toBeString()->toContain($message->path);
});

it('deletes its file when the message is deleted', function () {
    Storage::fake('s3');

    $message = AudioMessage::factory()->create();
    Storage::disk('s3')->put($message->path, 'audio');

    $message->delete();

    Storage::disk('s3')->assertMissing($message->path);
});

it('is ordered by position when read from its party', function () {
    $party = Party::factory()->withAudioGuestbook()->create();
    AudioMessage::factory()->for($party)->create(['position' => 3]);
    AudioMessage::factory()->for($party)->create(['position' => 1]);
    AudioMessage::factory()->for($party)->create(['position' => 2]);

    expect($party->audioMessages->pluck('position')->all())->toBe([1, 2, 3]);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

```
vendor/bin/sail artisan test --compact --filter="AudioGreetingTest|AudioMessageTest"
```

Attendu : ÉCHEC, classe `App\Models\AudioGreeting` introuvable.

- [ ] **Step 3: Write the enum**

`app/Enums/GreetingStatus.php` :

```php
<?php

namespace App\Enums;

/**
 * Lifecycle of a client's greeting, from the take they record to the message
 * the studio puts on the handset.
 */
enum GreetingStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Validated = 'validated';
    case Rejected = 'rejected';

    /**
     * Whether the studio still has to act on this greeting.
     */
    public function awaitsStudio(): bool
    {
        return $this === self::Pending;
    }
}
```

- [ ] **Step 4: Write the migrations**

`database/migrations/2026_09_17_100001_create_audio_greetings_table.php` :

```php
<?php

use App\Models\Party;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The message a client records for their guests to hear when they pick up
     * the handset. One per party, hence the unique foreign key: a table rather
     * than eight columns on `parties`, seven of which would be null for every
     * party sold without the audio guestbook.
     */
    public function up(): void
    {
        Schema::create('audio_greetings', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Party::class)->unique()->constrained()->cascadeOnDelete();
            $table->string('original_path');
            $table->string('mp3_path')->nullable();
            $table->unsignedInteger('duration_seconds')->nullable();
            $table->string('client_note', 120)->nullable();
            $table->string('status', 16)->default('draft');
            $table->text('rejection_reason')->nullable();
            $table->dateTime('submitted_at')->nullable();
            $table->dateTime('validated_at')->nullable();
            $table->timestamps();

            $table->index('status');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audio_greetings');
    }
};
```

`database/migrations/2026_09_17_100002_create_audio_messages_table.php` :

```php
<?php

use App\Models\Party;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The messages the guests left on the handset, uploaded by the studio
     * after the event. Already MP3 when they come off the device, so they are
     * stored untouched; `position` is the order they were recorded in.
     */
    public function up(): void
    {
        Schema::create('audio_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignIdFor(Party::class)->constrained()->cascadeOnDelete();
            $table->string('path');
            $table->string('title')->nullable();
            $table->unsignedInteger('duration_seconds')->default(0);
            $table->dateTime('recorded_at')->nullable();
            $table->unsignedInteger('position');
            $table->timestamps();

            $table->index(['party_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audio_messages');
    }
};
```

- [ ] **Step 5: Write the models**

`app/Models/AudioGreeting.php` :

```php
<?php

namespace App\Models;

use App\Enums\GreetingStatus;
use App\Services\SignedStorageUrl;
use Database\Factories\AudioGreetingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AudioGreeting extends Model
{
    /** @use HasFactory<AudioGreetingFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'party_id',
        'original_path',
        'mp3_path',
        'duration_seconds',
        'client_note',
        'status',
        'rejection_reason',
        'submitted_at',
        'validated_at',
    ];

    /** @var array<string, string> */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GreetingStatus::class,
            'submitted_at' => 'datetime',
            'validated_at' => 'datetime',
            'duration_seconds' => 'integer',
        ];
    }

    protected static function booting(): void
    {
        static::deleted(function (AudioGreeting $greeting): void {
            Storage::disk('s3')->delete(array_filter([
                $greeting->original_path,
                $greeting->mp3_path,
            ]));
        });
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * Signed URL of the converted greeting, or null while the conversion job
     * has not run yet. Callers must not fall back to the original: the browser
     * that recorded it produced WebM or MP4, which the handset cannot read and
     * Safari will not play back reliably.
     */
    public function url(): ?string
    {
        if (! $this->mp3_path) {
            return null;
        }

        return SignedStorageUrl::for($this->mp3_path);
    }
}
```

`app/Models/AudioMessage.php` :

```php
<?php

namespace App\Models;

use App\Services\SignedStorageUrl;
use Database\Factories\AudioMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AudioMessage extends Model
{
    /** @use HasFactory<AudioMessageFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'party_id',
        'path',
        'title',
        'duration_seconds',
        'recorded_at',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'duration_seconds' => 'integer',
            'position' => 'integer',
        ];
    }

    protected static function booting(): void
    {
        static::deleted(function (AudioMessage $message): void {
            Storage::disk('s3')->delete($message->path);
        });
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * What the client sees in their list. Naming a message is theirs to do and
     * always optional, so an unnamed one falls back to its order of passage.
     */
    public function displayTitle(): string
    {
        return $this->title ?: 'Message '.str_pad((string) $this->position, 2, '0', STR_PAD_LEFT);
    }

    public function url(): string
    {
        return SignedStorageUrl::for($this->path);
    }
}
```

- [ ] **Step 6: Write the factories**

`database/factories/AudioGreetingFactory.php` :

```php
<?php

namespace Database\Factories;

use App\Enums\GreetingStatus;
use App\Models\AudioGreeting;
use App\Models\Party;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AudioGreeting>
 */
class AudioGreetingFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'party_id' => Party::factory()->withAudioGuestbook(),
            'original_path' => 'parties/1/audio/'.fake()->uuid().'.webm',
            'mp3_path' => null,
            'duration_seconds' => null,
            'client_note' => null,
            'status' => GreetingStatus::Draft,
            'rejection_reason' => null,
            'submitted_at' => null,
            'validated_at' => null,
        ];
    }

    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => GreetingStatus::Pending,
            'mp3_path' => 'parties/1/audio/'.fake()->uuid().'.mp3',
            'duration_seconds' => fake()->numberBetween(20, 90),
            'submitted_at' => now(),
        ]);
    }

    public function validated(): static
    {
        return $this->pending()->state(fn (array $attributes) => [
            'status' => GreetingStatus::Validated,
            'validated_at' => now(),
        ]);
    }

    public function rejected(string $reason = 'Le son est saturé.'): static
    {
        return $this->pending()->state(fn (array $attributes) => [
            'status' => GreetingStatus::Rejected,
            'rejection_reason' => $reason,
        ]);
    }
}
```

`database/factories/AudioMessageFactory.php` :

```php
<?php

namespace Database\Factories;

use App\Models\AudioMessage;
use App\Models\Party;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<AudioMessage>
 */
class AudioMessageFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'party_id' => Party::factory()->withAudioGuestbook(),
            'path' => 'parties/1/audio/'.fake()->uuid().'.mp3',
            'title' => null,
            'duration_seconds' => fake()->numberBetween(10, 180),
            'recorded_at' => now(),
            'position' => fake()->numberBetween(1, 40),
        ];
    }
}
```

- [ ] **Step 7: Add the relations to `Party`**

Dans `app/Models/Party.php`, à côté de `pictures()` :

```php
    public function greeting(): HasOne
    {
        return $this->hasOne(AudioGreeting::class);
    }

    /**
     * The guests' messages, in the order they were recorded on the handset.
     */
    public function audioMessages(): HasMany
    {
        return $this->hasMany(AudioMessage::class)->orderBy('position');
    }
```

Importer `Illuminate\Database\Eloquent\Relations\HasOne`.

- [ ] **Step 8: Run the migrations and the tests**

```
vendor/bin/sail artisan migrate
vendor/bin/sail artisan test --compact --filter="AudioGreetingTest|AudioMessageTest"
```

Attendu : SUCCÈS.

- [ ] **Step 9: Format and commit**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add database/migrations app/Enums app/Models database/factories tests/Feature/AudioGreetingTest.php tests/Feature/AudioMessageTest.php
git commit -m "feat(audio): ajoute les tables, modèles et factories de l'annonce et des messages

Refs #54"
```

---

## Task 3: `Party` — URL publiques sans sous-domaine et noms réservés

Issue #55.

**Files:**
- Modify: `app/Models/Party.php`
- Modify: `app/Services/PartyPosterGenerator.php`
- Modify: `app/Http/Controllers/Admin/PartyController.php:51`
- Modify: `app/Http/Requests/StorePartyRequest.php`
- Modify: `app/Http/Requests/UpdateAdminPartyRequest.php`
- Test: `tests/Feature/PartyTest.php`
- Test: `tests/Feature/AdminPartiesTest.php`

**Interfaces:**
- Consumes: colonnes de la tâche 1.
- Produces: `Party::RESERVED_NAMES` (`['audiobook', 'pictures']`), `Party::hasSubdomain(): bool`, et `publicHost()`, `publicUrl()`, `posterUrl()`, `hostEditUrl()` renvoyant désormais `?string`.

- [ ] **Step 1: Write the failing tests**

Ajouter à `tests/Feature/PartyTest.php` :

```php
it('has no public host without a subdomain', function () {
    $party = Party::factory()->audioOnly()->create();

    expect($party->hasSubdomain())->toBeFalse()
        ->and($party->publicHost())->toBeNull()
        ->and($party->publicUrl())->toBeNull()
        ->and($party->posterUrl())->toBeNull()
        ->and($party->hostEditUrl())->toBeNull();
});

it('still builds its public host when it has a subdomain', function () {
    $party = Party::factory()->create(['name' => 'camille-antoine']);

    expect($party->hasSubdomain())->toBeTrue()
        ->and($party->publicHost())->toBe('camille-antoine.'.config('party.domain'));
});

it('falls back to a generic title when it has neither display name nor subdomain', function () {
    $party = Party::factory()->audioOnly()->create(['display_name' => null]);

    expect($party->displayTitle())->toBe('Votre événement');
});
```

Ajouter à `tests/Feature/AdminPartiesTest.php` :

```php
it('refuses a party named after a reserved route prefix', function () {
    $this->actingAs(User::factory()->create())
        ->post('/admin/parties', ['name' => 'audiobook'])
        ->assertSessionHasErrors('name');

    $this->actingAs(User::factory()->create())
        ->post('/admin/parties', ['name' => 'pictures'])
        ->assertSessionHasErrors('name');
});

it('accepts a party created without a name', function () {
    $this->actingAs(User::factory()->create())
        ->post('/admin/parties', ['name' => null])
        ->assertSessionHasNoErrors();

    expect(Party::query()->whereNull('name')->count())->toBe(1);
});
```

- [ ] **Step 2: Run the tests to verify they fail**

```
vendor/bin/sail artisan test --compact --filter="PartyTest|AdminPartiesTest"
```

Attendu : ÉCHEC, `hasSubdomain()` n'existe pas et `name` est encore obligatoire.

- [ ] **Step 3: Update `Party`**

Ajouter la constante, en tête de classe :

```php
    /**
     * Names a party may not take: `/{party}` is a catch-all prefix, so a party
     * called `audiobook` or `pictures` would shadow the literal routes of the
     * uuid-addressed surfaces.
     *
     * @var list<string>
     */
    public const RESERVED_NAMES = ['audiobook', 'pictures'];
```

Remplacer les quatre méthodes d'URL, qui ne valent plus que pour une party publiée sur un sous-domaine :

```php
    /**
     * Whether this party is published on a subdomain of its own. Only the
     * events sold as a published gallery are; the rest are addressed by uuid.
     */
    public function hasSubdomain(): bool
    {
        return filled($this->name);
    }

    public function publicHost(): ?string
    {
        if (! $this->hasSubdomain()) {
            return null;
        }

        return $this->name.'.'.config('party.domain');
    }

    public function publicUrl(): ?string
    {
        if (! $this->hasSubdomain()) {
            return null;
        }

        return 'https://'.$this->publicHost();
    }

    public function posterUrl(): ?string
    {
        if (! $this->hasSubdomain()) {
            return null;
        }

        return $this->publicUrl().'/?s=poster';
    }

    public function hostEditUrl(): ?string
    {
        if (! $this->hasSubdomain()) {
            return null;
        }

        return $this->publicUrl().'/?token='.$this->host_token;
    }
```

Et rendre `displayTitle()` tolérant à l'absence des deux :

```php
    public function displayTitle(): string
    {
        return $this->display_name ?: $this->name ?: 'Votre événement';
    }
```

- [ ] **Step 4: Guard the poster generator**

Dans `app/Services/PartyPosterGenerator.php`, au début de `generate()` :

```php
        if (! $party->hasSubdomain()) {
            throw new InvalidArgumentException(
                'A party without a subdomain has no gallery address to print on a poster.'
            );
        }
```

Importer `InvalidArgumentException`. Le contrôleur d'affiche continue de servir les parties qui en ont une ; l'admin masquera le bouton pour les autres à la tâche 3 du lot 10.

- [ ] **Step 5: Update the form requests**

Dans `StorePartyRequest::rules()` :

```php
            'name' => [
                'nullable',
                'string',
                'max:255',
                Rule::notIn(Party::RESERVED_NAMES),
                Rule::unique('parties', 'name'),
            ],
```

Et le message :

```php
            'name.not_in' => 'This name is reserved by the application.',
```

Importer `App\Models\Party`. Appliquer exactement les mêmes deux changements à `UpdateAdminPartyRequest::rules()` et `messages()`, en conservant son `->ignore($party->id)` sur la règle d'unicité.

- [ ] **Step 6: Let the admin controller carry a null link**

Dans `app/Http/Controllers/Admin/PartyController.php`, la clé `host_edit_url` reçoit désormais `null` pour une party sans sous-domaine. Aucun changement de code n'est nécessaire — vérifier seulement que le type du tableau retourné n'annonce pas `string`.

- [ ] **Step 7: Run the tests**

```
vendor/bin/sail artisan test --compact --filter="PartyTest|AdminPartiesTest|AdminPartyPosterTest"
```

Attendu : SUCCÈS. `AdminPartyPosterTest` couvre déjà une party nommée, il doit rester vert.

- [ ] **Step 8: Format and commit**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add app tests/Feature/PartyTest.php tests/Feature/AdminPartiesTest.php
git commit -m "feat(audio): rend le sous-domaine facultatif et réserve les préfixes de route

Refs #55"
```

---

## Task 4: Route `/audiobook/{uuid}` et 404 sur module inactif

Issue #56.

**Files:**
- Create: `app/Http/Middleware/EnsureAudioGuestbook.php`
- Create: `app/Http/Controllers/AudiobookController.php`
- Create: `resources/js/pages/party/audiobook.tsx`
- Modify: `routes/web.php`
- Modify: `bootstrap/app.php`
- Modify: `app/Models/Party.php`
- Test: `tests/Feature/AudioGuestbookAccessTest.php`

**Interfaces:**
- Consumes: `client_uuid`, `gallery_uuid`, `has_audio_guestbook` de la tâche 1 ; `Party::greeting()` de la tâche 2.
- Produces: route nommée `audiobook.show`, alias de middleware `audiobook`, `Party::audiobookUrl(): string`.

- [ ] **Step 1: Write the failing tests**

`tests/Feature/AudioGuestbookAccessTest.php` :

```php
<?php

use App\Models\Party;

it('opens the client space with the client uuid', function () {
    $party = Party::factory()->withAudioGuestbook()->create();

    $this->get('/audiobook/'.$party->client_uuid)->assertOk();
});

it('does not open the client space with the gallery uuid', function () {
    $party = Party::factory()->withAudioGuestbook()->create();

    $this->get('/audiobook/'.$party->gallery_uuid)->assertNotFound();
});

it('does not open the client space of a party without the module', function () {
    $party = Party::factory()->create(['has_audio_guestbook' => false]);

    $this->get('/audiobook/'.$party->client_uuid)->assertNotFound();
});

it('does not open the client space with an unknown uuid', function () {
    $this->get('/audiobook/00000000-0000-0000-0000-000000000000')->assertNotFound();
});

it('does not let a party named audiobook shadow the literal route', function () {
    $party = Party::factory()->withAudioGuestbook()->create(['name' => 'anything']);

    $this->get('/audiobook/'.$party->client_uuid)->assertOk();
});

it('builds the client link from the client uuid', function () {
    $party = Party::factory()->withAudioGuestbook()->create();

    expect($party->audiobookUrl())
        ->toBe(url('/audiobook/'.$party->client_uuid));
});
```

- [ ] **Step 2: Run the tests to verify they fail**

```
vendor/bin/sail artisan test --compact --filter=AudioGuestbookAccessTest
```

Attendu : ÉCHEC, la route n'existe pas (404 partout, y compris sur le premier cas).

- [ ] **Step 3: Write the middleware**

`app/Http/Middleware/EnsureAudioGuestbook.php` :

```php
<?php

namespace App\Http\Middleware;

use App\Models\Party;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAudioGuestbook
{
    /**
     * Guard the client's audio space.
     *
     * A party sold without the module answers 404 rather than 403: the very
     * existence of the space is not confirmed to whoever guessed at the uuid.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $party = $request->route('party');

        abort_unless($party instanceof Party && $party->has_audio_guestbook, 404);

        return $next($request);
    }
}
```

- [ ] **Step 4: Register the alias**

Dans `bootstrap/app.php`, compléter le tableau d'alias :

```php
        $middleware->alias([
            'party.host' => EnsurePartyHost::class,
            'audiobook' => EnsureAudioGuestbook::class,
        ]);
```

Importer `App\Http\Middleware\EnsureAudioGuestbook`.

- [ ] **Step 5: Write the controller**

`app/Http/Controllers/AudiobookController.php` :

```php
<?php

namespace App\Http\Controllers;

use App\Models\Party;
use Inertia\Inertia;
use Inertia\Response;

class AudiobookController extends Controller
{
    /**
     * The client's private audio space.
     *
     * Only the event's identity is sent for now; the greeting and the guests'
     * messages are added by the tasks that build them.
     */
    public function show(Party $party): Response
    {
        return Inertia::render('party/audiobook', [
            'party' => [
                'title' => $party->displayTitle(),
                'event_at' => $party->event_at?->toIso8601String(),
            ],
        ]);
    }
}
```

- [ ] **Step 6: Write the page placeholder**

`resources/js/pages/party/audiobook.tsx` — gabarit minimal, rempli au lot 3. **Aucun import de `@/components/ui/*`** : cette page est publique et ses styles viennent de `app.css`.

```tsx
type AudiobookProps = {
    party: {
        title: string;
        event_at: string | null;
    };
};

export default function Audiobook({ party }: AudiobookProps) {
    return (
        <main className="min-h-screen bg-surface text-ink">
            <h1 className="font-serif text-4xl">{party.title}</h1>
        </main>
    );
}
```

- [ ] **Step 7: Declare the route**

Dans `routes/web.php`, **avant** le groupe `Route::prefix('{party}')` — sans quoi le préfixe attrape-tout capterait `/audiobook/…` :

```php
/**
 * The client's audio space, addressed by the party's private uuid rather than
 * by a subdomain: only the events sold as a published gallery get one of
 * those. Declared ahead of the `{party}` group, which is a catch-all prefix.
 */
Route::get('audiobook/{party:client_uuid}', [AudiobookController::class, 'show'])
    ->middleware('audiobook')
    ->name('audiobook.show');
```

Importer `App\Http\Controllers\AudiobookController`.

- [ ] **Step 8: Add `audiobookUrl()` to `Party`**

```php
    /**
     * Link the client opens to record their greeting and collect their guests'
     * messages. The only place that knows the shape of that link.
     */
    public function audiobookUrl(): string
    {
        return route('audiobook.show', $this->client_uuid);
    }
```

- [ ] **Step 9: Run the tests**

```
vendor/bin/sail artisan test --compact --filter=AudioGuestbookAccessTest
```

Attendu : SUCCÈS. Si le premier cas renvoie encore 404, la route est déclarée après le groupe `{party}`.

- [ ] **Step 10: Format and commit**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add app bootstrap/app.php routes/web.php resources/js/pages/party/audiobook.tsx tests/Feature/AudioGuestbookAccessTest.php
git commit -m "feat(audio): ouvre /audiobook/{uuid} et renvoie 404 sans le module

Refs #56"
```

---

## Task 5: Limiteur de débit keyé sur l'IP au point d'entrée

Issue #57.

**Files:**
- Modify: `app/Providers/AppServiceProvider.php`
- Modify: `routes/web.php`
- Test: `tests/Feature/AudioGuestbookAccessTest.php`

**Interfaces:**
- Consumes: la route `audiobook.show` de la tâche 4.
- Produces: limiteur nommé `audiobook-entry`, 30 requêtes par minute et par adresse.

- [ ] **Step 1: Write the failing test**

Ajouter à `tests/Feature/AudioGuestbookAccessTest.php` :

```php
it('throttles the entry point per address, not per uuid', function () {
    $party = Party::factory()->withAudioGuestbook()->create();

    // Une énumération essaie un uuid différent à chaque coup : le compteur ne
    // doit donc pas être remis à zéro par le changement d'uuid.
    foreach (range(1, 30) as $attempt) {
        $this->get('/audiobook/'.fake()->uuid())->assertNotFound();
    }

    $this->get('/audiobook/'.$party->client_uuid)->assertStatus(429);
});
```

- [ ] **Step 2: Run the test to verify it fails**

```
vendor/bin/sail artisan test --compact --filter="throttles the entry point"
```

Attendu : ÉCHEC — la trente-et-unième requête répond 200 au lieu de 429.

- [ ] **Step 3: Declare the limiter**

Dans `app/Providers\AppServiceProvider::boot()`, à la suite de ses voisins :

```php
        /**
         * Unlike its neighbours, this one is keyed on the address alone. The
         * uuid is what an enumeration varies, so keying on it as well would
         * hand the attacker a fresh counter with every guess — the opposite of
         * what the limiter is for. A client opening their own space a handful
         * of times a day never comes near the ceiling.
         */
        RateLimiter::for('audiobook-entry', function (Request $request): Limit {
            return Limit::perMinute(30)->by($request->ip());
        });
```

- [ ] **Step 4: Apply it to the route**

Dans `routes/web.php` :

```php
Route::get('audiobook/{party:client_uuid}', [AudiobookController::class, 'show'])
    ->middleware(['audiobook', 'throttle:audiobook-entry'])
    ->name('audiobook.show');
```

- [ ] **Step 5: Run the tests**

```
vendor/bin/sail artisan test --compact --filter=AudioGuestbookAccessTest
```

Attendu : SUCCÈS sur l'ensemble du fichier. Les autres cas du fichier restent sous le plafond de 30.

- [ ] **Step 6: Format and commit**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add app/Providers/AppServiceProvider.php routes/web.php tests/Feature/AudioGuestbookAccessTest.php
git commit -m "feat(audio): plafonne l'entrée de /audiobook par adresse

Refs #57"
```

---

## Task 6: Suite complète et vérification de bout en bout

**Files:** aucun, sauf correction d'une régression.

- [ ] **Step 1: Run the whole suite**

```
vendor/bin/sail artisan test --compact
```

Attendu : SUCCÈS. Points de rupture probables, vérifiés à l'écriture du plan :
- `AdminPartiesTest:180` teste l'unicité du nom, pas son caractère obligatoire — il reste vert. Aucun test existant n'exige `name` requis.
- `AdminPartyPosterTest:66` appelle `publicHost()` sur une party nommée — reste vert. Il échouerait si la garde de la tâche 3 se déclenchait à tort.
- `CreatePartyTest` et `ImportLegacyDataTest` — la commande de création pose toujours un nom, rien à changer.
- `tests/Web/` — relancer une fois avant d'investiguer, ces tests navigateur flakent sur des délais.

Le rollback de l'étape 3 échouera si une party sans nom a été créée entre-temps : c'est attendu, `migrate:fresh` d'abord.

- [ ] **Step 2: Verify the front-end still builds**

```
vendor/bin/sail npm run build
```

Attendu : SUCCÈS, la nouvelle page `party/audiobook` incluse.

- [ ] **Step 3: Verify the migrations replay from scratch**

```
vendor/bin/sail artisan migrate:fresh
vendor/bin/sail artisan migrate:rollback --step=3
vendor/bin/sail artisan migrate
```

Attendu : les trois passent. Une base neuve doit se construire sans la moindre donnée préexistante.

- [ ] **Step 4: Final formatting**

```
vendor/bin/sail bin pint --format agent
```

- [ ] **Step 5: Commit any fix and push the branch**

```bash
git add -A
git commit -m "test(audio): rattrape les régressions du lot 1"
```

---

## Ce que ce lot ne fait pas

- Aucune surface `/pictures/{uuid}` : seule la colonne et la réservation du préfixe sont posées.
- La page `/audiobook/{uuid}` ne contient qu'un titre. L'enregistreur, les états et la liste des messages sont les lots 3 et 7.
- Aucun e-mail, aucune conversion, aucune tâche planifiée.
- L'admin ne sait pas encore activer un module ni afficher la colonne `Modules` : lots 5 et 10.
