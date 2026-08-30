# Migration des données legacy — plan d'implémentation

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Doter le projet d'une commande `party:import-legacy` qui importe, en une passe rejouable, les ~62 000 photos et leurs parties depuis la base et le disque de l'ancien projet `BoothParty/BoothParty`.

**Architecture:** Une seule commande Artisan lit la source par une connexion Eloquent `legacy` dédiée (requêtes `DB::connection('legacy')->table(...)`, aucun modèle legacy), pousse les originaux sur S3 sous un chemin déterministe dérivé du md5 d'origine, et délègue les miniatures au job `CreatePictureThumbnail` existant. L'idempotence ne repose sur aucun état persisté : `firstOrNew` sur le couple `source_key`/`source_event_id` pour les parties, sur le `path` S3 pour les photos.

**Tech Stack:** Laravel 12, Pest 4, Eloquent (connexion multiple), Flysystem S3 (Minio), queue Laravel.

**Spec:** `docs/superpowers/specs/2026-08-31-migration-donnees-legacy.md`

## Global Constraints

- Toutes les commandes passent par Sail : `vendor/bin/sail artisan …`, `vendor/bin/sail composer …`.
- Aucune nouvelle dépendance Composer ou NPM.
- PHP : accolades obligatoires même sur corps d'une ligne ; types de retour explicites partout ; promotion de propriétés dans les constructeurs ; PHPDoc plutôt que commentaires inline.
- Pest : `it('...')`, `uses(RefreshDatabase::class)`, séparateurs de section en commentaires français (`// ── Titre ───`), à l'image de `tests/Feature/Console/CreatePartyTest.php`.
- Après toute modification PHP : `vendor/bin/sail bin pint --dirty --format agent`.
- `source_key` vaut `'legacy'` pour tout ce que produit cet import — c'est le bouton d'annulation.
- Le disque S3 s'obtient par `Storage::disk('s3')`, jamais par le disque par défaut.
- Chemin S3 des originaux : `pictures/{party_id}/{md5}.jpg`, où `{md5}` est le **nom de fichier d'origine** (l'ancien projet nommait déjà par le hash du contenu).
- Aucune image n'est décodée pendant l'import : `width`/`height` viennent de `picture_sizes`.
- Les colonnes legacy `location`, `date`, `is_from_time` sont **délibérément abandonnées**.
- Les utilisateurs ne sont pas migrés : `user_id` reste `null`.

## Structure des fichiers

| Fichier | Responsabilité |
| --- | --- |
| `config/database.php` (modif) | Déclarer la connexion `legacy`, en lecture seule de fait |
| `.env.example` (modif) | Documenter les variables `LEGACY_DB_*` |
| `app/Console/Commands/Party/ImportLegacyData.php` (création) | Toute la commande : lecture source, mapping, upload, compteurs, rapport |
| `tests/Feature/Console/ImportLegacyDataTest.php` (création) | Couverture complète, sur une connexion `legacy` SQLite en mémoire |

La commande tient dans un seul fichier : elle est linéaire (parties → photos → rapport), sans logique réutilisable ailleurs, et elle disparaîtra une fois la bascule faite. L'éclater en service + commande créerait des unités que rien d'autre ne consomme.

---

### Task 1 : Connexion `legacy` et squelette de commande

**Files:**
- Modify: `config/database.php` (bloc `connections`, après `mariadb`)
- Modify: `.env.example` (après le bloc `DB_*`)
- Create: `app/Console/Commands/Party/ImportLegacyData.php`
- Test: `tests/Feature/Console/ImportLegacyDataTest.php`

**Interfaces:**
- Consumes: rien.
- Produces: la connexion nommée `legacy` ; la commande `party:import-legacy` avec les options `--files=`, `--party=`, `--dry-run` ; la constante `ImportLegacyData::SOURCE_KEY = 'legacy'`.

- [x] **Step 1 : Écrire le test qui échoue**

Créer `tests/Feature/Console/ImportLegacyDataTest.php` :

```php
<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

/**
 * Monte une connexion « legacy » SQLite en mémoire portant le schéma de
 * l'ancien projet, et un dossier temporaire tenant lieu de son « public/ ».
 */
beforeEach(function () {
    DB::purge('legacy');

    config()->set('database.connections.legacy', [
        'driver' => 'sqlite',
        'database' => ':memory:',
        'prefix' => '',
        'foreign_key_constraints' => false,
    ]);

    $schema = Schema::connection('legacy');

    $schema->create('parties', function ($table) {
        $table->id();
        $table->string('name');
        $table->string('title')->nullable();
        $table->string('description')->nullable();
        $table->string('location')->nullable();
        $table->dateTime('date')->nullable();
        $table->boolean('is_from_time')->default(false);
        $table->dateTime('hide_pictures_date')->nullable();
        $table->timestamps();
    });

    $schema->create('pictures', function ($table) {
        $table->id();
        $table->unsignedBigInteger('party_id');
        $table->timestamps();
    });

    $schema->create('picture_sizes', function ($table) {
        $table->id();
        $table->unsignedBigInteger('picture_id');
        $table->string('size');
        $table->integer('height');
        $table->integer('width');
        $table->string('path');
        $table->timestamps();
    });

    exec('rm -rf '.escapeshellarg(legacyFilesRoot()));
    mkdir(legacyFilesRoot(), 0755, true);
});

afterEach(function () {
    exec('rm -rf '.escapeshellarg(legacyFilesRoot()));
});

/**
 * Dossier temporaire tenant lieu du « public/ » de l'ancien projet. Le jeton
 * de ParaTest le rend distinct par processus, pour que `--parallel` ne fasse
 * pas se marcher dessus deux tests.
 */
function legacyFilesRoot(): string
{
    return sys_get_temp_dir().'/boothparty-legacy-import-'.(getenv('TEST_TOKEN') ?: 'default');
}

// ── Validation de --files ────────────────────────────────────────────────────

it('fails when --files does not point to an existing directory', function () {
    $this->artisan('party:import-legacy', ['--files' => '/nowhere/at/all'])
        ->expectsOutputToContain('must point to an existing directory')
        ->assertFailed();
});

it('succeeds on an empty legacy database', function () {
    $this->artisan('party:import-legacy', ['--files' => legacyFilesRoot()])
        ->assertSuccessful();
});
```

- [x] **Step 2 : Lancer le test, vérifier qu'il échoue**

Run: `vendor/bin/sail artisan test --compact --filter=ImportLegacyData`
Expected: FAIL — `The command "party:import-legacy" does not exist.`

- [x] **Step 3 : Déclarer la connexion `legacy`**

Dans `config/database.php`, insérer après le bloc `'mariadb' => [...]` :

```php
        'legacy' => [
            'driver' => 'mariadb',
            'host' => env('LEGACY_DB_HOST', '127.0.0.1'),
            'port' => env('LEGACY_DB_PORT', '3306'),
            'database' => env('LEGACY_DB_DATABASE', 'boothparty_legacy'),
            'username' => env('LEGACY_DB_USERNAME', 'root'),
            'password' => env('LEGACY_DB_PASSWORD', ''),
            'unix_socket' => env('LEGACY_DB_SOCKET', ''),
            'charset' => 'utf8mb4',
            'collation' => 'utf8mb4_unicode_ci',
            'prefix' => '',
            'prefix_indexes' => true,
            'strict' => true,
            'engine' => null,
        ],
```

Dans `.env.example`, après la ligne `DB_PASSWORD=` :

```
# Base de l'ancien projet, lue une seule fois par `party:import-legacy`
LEGACY_DB_HOST=127.0.0.1
LEGACY_DB_PORT=3306
LEGACY_DB_DATABASE=boothparty_legacy
LEGACY_DB_USERNAME=root
LEGACY_DB_PASSWORD=
```

- [x] **Step 4 : Écrire le squelette de la commande**

Créer `app/Console/Commands/Party/ImportLegacyData.php` :

```php
<?php

namespace App\Console\Commands\Party;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class ImportLegacyData extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'party:import-legacy
                            {--files= : Path to the copied public/ directory of the legacy project}
                            {--party= : Restrict the import to a single legacy party, by name}
                            {--dry-run : Walk the source and report without writing anything}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import parties and pictures from the legacy BoothParty database';

    /**
     * Value written to `parties.source_key` for everything this import creates.
     */
    public const SOURCE_KEY = 'legacy';

    /**
     * Absolute path to the copied `public/` directory of the legacy project.
     */
    private string $filesRoot = '';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $filesRoot = $this->option('files');

        if (! is_string($filesRoot) || ! is_dir($filesRoot)) {
            $this->components->error('The --files option must point to an existing directory.');

            return self::FAILURE;
        }

        $this->filesRoot = rtrim($filesRoot, '/');

        $legacyParties = DB::connection('legacy')->table('parties')
            ->when(
                $this->option('party'),
                fn ($query, string $name) => $query->where('name', $name),
            )
            ->orderBy('id')
            ->get();

        return self::SUCCESS;
    }
}
```

- [x] **Step 5 : Lancer les tests, vérifier qu'ils passent**

Run: `vendor/bin/sail artisan test --compact --filter=ImportLegacyData`
Expected: PASS (2 tests)

- [x] **Step 6 : Formater et committer**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add config/database.php .env.example app/Console/Commands/Party/ImportLegacyData.php tests/Feature/Console/ImportLegacyDataTest.php
git commit -m "feat(migration): add the legacy connection and the import command skeleton"
```

---

### Task 2 : Import des parties

**Files:**
- Modify: `app/Console/Commands/Party/ImportLegacyData.php`
- Test: `tests/Feature/Console/ImportLegacyDataTest.php`

**Interfaces:**
- Consumes: `ImportLegacyData::SOURCE_KEY`, la connexion `legacy`, `$this->filesRoot`.
- Produces: `private function importParty(object $legacyParty): Party` — renvoie la party cible, créée ou déjà présente ; `private array $counters` avec les clés `parties`, `pictures`, `missing`, `errors`.

- [x] **Step 1 : Écrire les tests qui échouent**

Ajouter à `tests/Feature/Console/ImportLegacyDataTest.php` :

```php
// ── Import des parties ───────────────────────────────────────────────────────

/**
 * Insère une party dans la base legacy et renvoie son identifiant.
 */
function legacyParty(array $attributes = []): int
{
    return DB::connection('legacy')->table('parties')->insertGetId(array_merge([
        'name' => 'retro-night',
        'title' => 'Retro Night',
        'description' => 'Une soirée à paillettes',
        'location' => 'Nantes',
        'date' => '2024-06-01 20:00:00',
        'is_from_time' => false,
        'hide_pictures_date' => '2024-07-01 00:00:00',
        'created_at' => '2024-05-01 10:00:00',
        'updated_at' => '2024-05-02 11:00:00',
    ], $attributes));
}

it('maps every legacy party column onto the new schema', function () {
    $legacyId = legacyParty();

    $this->artisan('party:import-legacy', ['--files' => legacyFilesRoot()])
        ->assertSuccessful();

    $party = Party::where('source_key', 'legacy')->sole();

    expect($party->name)->toBe('retro-night')
        ->and($party->display_name)->toBe('Retro Night')
        ->and($party->description)->toBe('Une soirée à paillettes')
        ->and($party->source_event_id)->toBe((string) $legacyId)
        ->and($party->user_id)->toBeNull()
        ->and($party->host_token)->not->toBeNull()
        ->and($party->expire_at->toDateTimeString())->toBe('2024-07-01 00:00:00')
        ->and($party->created_at->toDateTimeString())->toBe('2024-05-01 10:00:00')
        ->and($party->updated_at->toDateTimeString())->toBe('2024-05-02 11:00:00');
});

it('imports a party whose optional columns are null', function () {
    legacyParty([
        'name' => 'bare',
        'title' => null,
        'description' => null,
        'hide_pictures_date' => null,
    ]);

    $this->artisan('party:import-legacy', ['--files' => legacyFilesRoot()])
        ->assertSuccessful();

    $party = Party::where('name', 'bare')->sole();

    expect($party->display_name)->toBeNull()
        ->and($party->description)->toBeNull()
        ->and($party->expire_at)->toBeNull()
        ->and($party->displayTitle())->toBe('bare');
});

it('does not duplicate a party on a second run', function () {
    legacyParty();

    $this->artisan('party:import-legacy', ['--files' => legacyFilesRoot()])->assertSuccessful();
    $this->artisan('party:import-legacy', ['--files' => legacyFilesRoot()])->assertSuccessful();

    expect(Party::count())->toBe(1);
});

it('restricts the import to a single party with --party', function () {
    legacyParty(['name' => 'wanted']);
    legacyParty(['name' => 'ignored']);

    $this->artisan('party:import-legacy', [
        '--files' => legacyFilesRoot(),
        '--party' => 'wanted',
    ])->assertSuccessful();

    expect(Party::pluck('name')->all())->toBe(['wanted']);
});
```

Ajouter en tête du fichier de test l'import `use App\Models\Party;`.

- [x] **Step 2 : Lancer les tests, vérifier qu'ils échouent**

Run: `vendor/bin/sail artisan test --compact --filter=ImportLegacyData`
Expected: FAIL — aucune party n'est créée (`sole()` lève `NoRecordsFoundException`).

- [x] **Step 3 : Implémenter l'import des parties**

Dans `ImportLegacyData.php`, ajouter `use App\Models\Party;` puis la propriété de compteurs sous `$filesRoot` :

```php
    /**
     * Tally of what the run did, rendered by the closing report.
     *
     * @var array{parties: int, pictures: int, missing: int, errors: int}
     */
    private array $counters = [
        'parties' => 0,
        'pictures' => 0,
        'missing' => 0,
        'errors' => 0,
    ];
```

Remplacer la fin de `handle()` (à partir de `$legacyParties = …`) par :

```php
        $legacyParties = DB::connection('legacy')->table('parties')
            ->when(
                $this->option('party'),
                fn ($query, string $name) => $query->where('name', $name),
            )
            ->orderBy('id')
            ->get();

        foreach ($legacyParties as $legacyParty) {
            $this->importParty($legacyParty);
        }

        return self::SUCCESS;
```

Et ajouter la méthode :

```php
    /**
     * Create the target party for a legacy one, or return the party a previous
     * run already created for it. The source couple is the whole idempotency
     * key: no run state is persisted anywhere.
     */
    private function importParty(object $legacyParty): Party
    {
        $party = Party::firstOrNew([
            'source_key' => self::SOURCE_KEY,
            'source_event_id' => (string) $legacyParty->id,
        ]);

        if ($party->exists) {
            return $party;
        }

        $party->fill([
            'name' => $legacyParty->name,
            'display_name' => $legacyParty->title,
            'description' => $legacyParty->description,
            'expire_at' => $legacyParty->hide_pictures_date,
            'user_id' => null,
        ]);

        /**
         * Timestamps are guarded, and letting Eloquent stamp them would date
         * every imported gallery from the night of the migration.
         */
        $party->forceFill([
            'created_at' => $legacyParty->created_at,
            'updated_at' => $legacyParty->updated_at,
        ])->save();

        $this->counters['parties']++;

        return $party;
    }
```

- [x] **Step 4 : Lancer les tests, vérifier qu'ils passent**

Run: `vendor/bin/sail artisan test --compact --filter=ImportLegacyData`
Expected: PASS (6 tests)

- [x] **Step 5 : Formater et committer**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add app/Console/Commands/Party/ImportLegacyData.php tests/Feature/Console/ImportLegacyDataTest.php
git commit -m "feat(migration): import the legacy parties"
```

---

### Task 3 : Import des photos et de leurs fichiers

**Files:**
- Modify: `app/Console/Commands/Party/ImportLegacyData.php`
- Test: `tests/Feature/Console/ImportLegacyDataTest.php`

**Interfaces:**
- Consumes: `importParty()`, `$this->filesRoot`, `$this->counters`.
- Produces: `private function importPictures(object $legacyParty, Party $party): void` et `private function importPicture(object $row, Party $party): void`.

- [x] **Step 1 : Écrire les tests qui échouent**

Ajouter au fichier de test :

```php
// ── Import des photos ────────────────────────────────────────────────────────

/**
 * Insère une photo legacy et écrit ses trois fichiers sur le disque factice.
 * Renvoie le chemin relatif de l'original.
 */
function legacyPicture(int $partyId, string $partyName = 'retro-night'): string
{
    $filesRoot = legacyFilesRoot();

    $pictureId = DB::connection('legacy')->table('pictures')->insertGetId([
        'party_id' => $partyId,
        'created_at' => '2024-06-01 21:30:00',
        'updated_at' => '2024-06-01 21:30:00',
    ]);

    $directory = "pictures/{$partyName}/{$pictureId}";
    mkdir("{$filesRoot}/{$directory}", 0755, true);

    $sizes = [
        ['size' => 'original', 'width' => 3000, 'height' => 2000, 'hash' => md5("original-{$pictureId}")],
        ['size' => 'thumbnail', 'width' => 250, 'height' => 167, 'hash' => md5("thumbnail-{$pictureId}")],
        ['size' => 'cover', 'width' => 500, 'height' => 500, 'hash' => md5("cover-{$pictureId}")],
    ];

    foreach ($sizes as $size) {
        $path = "{$directory}/{$size['hash']}.jpg";
        file_put_contents("{$filesRoot}/{$path}", 'fake-jpeg-bytes');

        DB::connection('legacy')->table('picture_sizes')->insert([
            'picture_id' => $pictureId,
            'size' => $size['size'],
            'width' => $size['width'],
            'height' => $size['height'],
            'path' => $path,
            'created_at' => '2024-06-01 21:30:00',
            'updated_at' => '2024-06-01 21:30:00',
        ]);
    }

    return "{$directory}/{$sizes[0]['hash']}.jpg";
}

it('uploads the original and maps its dimensions', function () {
    Storage::fake('s3');
    Queue::fake();

    $legacyId = legacyParty();
    $originalPath = legacyPicture($legacyId);
    $md5 = pathinfo($originalPath, PATHINFO_FILENAME);

    $this->artisan('party:import-legacy', ['--files' => legacyFilesRoot()])
        ->assertSuccessful();

    $party = Party::sole();
    $picture = Picture::sole();

    expect($picture->party_id)->toBe($party->id)
        ->and($picture->path)->toBe("pictures/{$party->id}/{$md5}.jpg")
        ->and($picture->width)->toEqual(3000)
        ->and($picture->height)->toEqual(2000)
        ->and($picture->created_at->toDateTimeString())->toBe('2024-06-01 21:30:00');

    Storage::disk('s3')->assertExists($picture->path);
});

it('ignores the legacy thumbnail and cover sizes', function () {
    Storage::fake('s3');
    Queue::fake();

    legacyPicture(legacyParty());

    $this->artisan('party:import-legacy', ['--files' => legacyFilesRoot()])
        ->assertSuccessful();

    expect(Picture::count())->toBe(1)
        ->and(Storage::disk('s3')->allFiles())->toHaveCount(1);
});

it('dispatches a thumbnail job for each imported picture', function () {
    Storage::fake('s3');
    Queue::fake();

    $legacyId = legacyParty();
    legacyPicture($legacyId);
    legacyPicture($legacyId);

    $this->artisan('party:import-legacy', ['--files' => legacyFilesRoot()])
        ->assertSuccessful();

    Queue::assertPushed(CreatePictureThumbnail::class, 2);
});

it('does not duplicate pictures nor redispatch jobs on a second run', function () {
    Storage::fake('s3');
    Queue::fake();

    legacyPicture(legacyParty());

    $this->artisan('party:import-legacy', ['--files' => legacyFilesRoot()])->assertSuccessful();
    Queue::fake();
    $this->artisan('party:import-legacy', ['--files' => legacyFilesRoot()])->assertSuccessful();

    expect(Picture::count())->toBe(1);
    Queue::assertNothingPushed();
});

it('imports a party that has no picture at all', function () {
    Storage::fake('s3');
    Queue::fake();

    legacyParty();

    $this->artisan('party:import-legacy', ['--files' => legacyFilesRoot()])
        ->assertSuccessful();

    expect(Party::count())->toBe(1)
        ->and(Picture::count())->toBe(0);
});
```

Ajouter en tête du fichier les imports `use App\Jobs\CreatePictureThumbnail;`, `use App\Models\Picture;`, `use Illuminate\Support\Facades\Queue;` et `use Illuminate\Support\Facades\Storage;`.

- [x] **Step 2 : Lancer les tests, vérifier qu'ils échouent**

Run: `vendor/bin/sail artisan test --compact --filter=ImportLegacyData`
Expected: FAIL — aucune `Picture` n'est créée.

- [x] **Step 3 : Implémenter l'import des photos**

Ajouter à `ImportLegacyData.php` les imports `use App\Jobs\CreatePictureThumbnail;`, `use App\Models\Picture;`, `use Illuminate\Http\File;`, `use Illuminate\Support\Facades\Storage;`.

Dans `handle()`, remplacer le corps de la boucle par :

```php
        foreach ($legacyParties as $legacyParty) {
            $this->importPictures($legacyParty, $this->importParty($legacyParty));
        }
```

Puis ajouter les deux méthodes :

```php
    /**
     * Walk the legacy pictures of one party. The join filters on the
     * `original` size, so the legacy thumbnail and cover rows never surface,
     * and `chunkById` keeps memory flat on a gallery of any size.
     */
    private function importPictures(object $legacyParty, Party $party): void
    {
        DB::connection('legacy')->table('pictures')
            ->join('picture_sizes', 'picture_sizes.picture_id', '=', 'pictures.id')
            ->where('pictures.party_id', $legacyParty->id)
            ->where('picture_sizes.size', 'original')
            ->select([
                'pictures.id',
                'pictures.created_at',
                'pictures.updated_at',
                'picture_sizes.path',
                'picture_sizes.width',
                'picture_sizes.height',
            ])
            ->chunkById(500, function ($rows) use ($party): void {
                foreach ($rows as $row) {
                    $this->importPicture($row, $party);
                }
            }, 'pictures.id', 'id');
    }

    /**
     * Push one legacy original onto S3 and record it.
     *
     * The object keeps its legacy file name, which the old project already
     * derived from the content hash. That makes the S3 path deterministic, so
     * a re-run overwrites the very same object instead of creating a twin.
     */
    private function importPicture(object $row, Party $party): void
    {
        $source = $this->filesRoot.'/'.$row->path;

        $path = Storage::disk('s3')->putFileAs(
            "pictures/{$party->id}",
            new File($source),
            pathinfo($row->path, PATHINFO_BASENAME),
        );

        $picture = Picture::firstOrNew(['path' => $path]);

        if ($picture->exists) {
            return;
        }

        $picture->fill([
            'party_id' => $party->id,
            'width' => $row->width,
            'height' => $row->height,
        ])->forceFill([
            'created_at' => $row->created_at,
            'updated_at' => $row->updated_at,
        ])->save();

        $this->counters['pictures']++;

        CreatePictureThumbnail::dispatch($picture);
    }
```

- [x] **Step 4 : Lancer les tests, vérifier qu'ils passent**

Run: `vendor/bin/sail artisan test --compact --filter=ImportLegacyData`
Expected: PASS (11 tests)

- [x] **Step 5 : Formater et committer**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add app/Console/Commands/Party/ImportLegacyData.php tests/Feature/Console/ImportLegacyDataTest.php
git commit -m "feat(migration): import the legacy pictures onto S3"
```

---

### Task 4 : Robustesse et rapport chiffré

**Files:**
- Modify: `app/Console/Commands/Party/ImportLegacyData.php`
- Test: `tests/Feature/Console/ImportLegacyDataTest.php`

**Interfaces:**
- Consumes: `importPicture()`, `$this->counters`.
- Produces: `private function report(): void` ; la barre de progression alimentée par `private function totalPictures(Collection $legacyParties): int`.

Sur 62 000 fichiers d'origine hétérogène, un JPEG tronqué ou un chemin exotique est une certitude : une photo qui échoue ne doit jamais faire tomber les 61 999 autres.

- [x] **Step 1 : Écrire les tests qui échouent**

Ajouter au fichier de test :

```php
// ── Robustesse et rapport ────────────────────────────────────────────────────

it('counts a missing file without failing the run', function () {
    Storage::fake('s3');
    Queue::fake();

    $legacyId = legacyParty();
    $originalPath = legacyPicture($legacyId);
    legacyPicture($legacyId);

    unlink(legacyFilesRoot()."/{$originalPath}");

    $this->artisan('party:import-legacy', ['--files' => legacyFilesRoot()])
        ->expectsOutputToContain('Fichiers manquants')
        ->assertSuccessful();

    expect(Picture::count())->toBe(1);
});

it('reports what the run did', function () {
    Storage::fake('s3');
    Queue::fake();

    legacyPicture(legacyParty());

    $this->artisan('party:import-legacy', ['--files' => legacyFilesRoot()])
        ->expectsOutputToContain('Parties importées')
        ->expectsOutputToContain('Photos importées')
        ->expectsOutputToContain('Erreurs')
        ->assertSuccessful();
});
```

- [x] **Step 2 : Lancer les tests, vérifier qu'ils échouent**

Run: `vendor/bin/sail artisan test --compact --filter=ImportLegacyData`
Expected: FAIL — le premier test lève une exception sur le fichier absent, le second ne trouve pas le rapport.

- [x] **Step 3 : Implémenter la robustesse, la progression et le rapport**

Ajouter les imports `use Illuminate\Support\Collection;`, `use Illuminate\Support\Facades\Log;`, `use Symfony\Component\Console\Helper\ProgressBar;`, `use Throwable;`.

Ajouter la propriété :

```php
    /**
     * Progress over the pictures, which is what actually takes hours.
     */
    private ?ProgressBar $progress = null;
```

Dans `handle()`, entourer la boucle :

```php
        $this->progress = $this->output->createProgressBar($this->totalPictures($legacyParties));
        $this->progress->start();

        foreach ($legacyParties as $legacyParty) {
            $this->importPictures($legacyParty, $this->importParty($legacyParty));
        }

        $this->progress->finish();
        $this->newLine(2);
        $this->report();

        return self::SUCCESS;
```

Dans `importPictures()`, avancer la barre après chaque photo :

```php
                foreach ($rows as $row) {
                    $this->importPicture($row, $party);
                    $this->progress?->advance();
                }
```

Remplacer le début de `importPicture()` — le corps entier passe sous garde :

```php
    private function importPicture(object $row, Party $party): void
    {
        try {
            $source = $this->filesRoot.'/'.$row->path;

            if (! is_file($source)) {
                $this->counters['missing']++;
                Log::warning('Legacy import: file is gone', [
                    'legacy_picture_id' => $row->id,
                    'path' => $row->path,
                ]);

                return;
            }

            $path = Storage::disk('s3')->putFileAs(
                "pictures/{$party->id}",
                new File($source),
                pathinfo($row->path, PATHINFO_BASENAME),
            );

            $picture = Picture::firstOrNew(['path' => $path]);

            if ($picture->exists) {
                return;
            }

            $picture->fill([
                'party_id' => $party->id,
                'width' => $row->width,
                'height' => $row->height,
            ])->forceFill([
                'created_at' => $row->created_at,
                'updated_at' => $row->updated_at,
            ])->save();

            $this->counters['pictures']++;

            CreatePictureThumbnail::dispatch($picture);
        } catch (Throwable $exception) {
            $this->counters['errors']++;
            Log::error('Legacy import: picture failed', [
                'legacy_picture_id' => $row->id,
                'path' => $row->path,
                'message' => $exception->getMessage(),
            ]);
        }
    }
```

Ajouter les deux méthodes :

```php
    /**
     * Total number of legacy originals in scope, used to size the progress bar.
     *
     * @param  Collection<int, object>  $legacyParties
     */
    private function totalPictures(Collection $legacyParties): int
    {
        return DB::connection('legacy')->table('pictures')
            ->join('picture_sizes', 'picture_sizes.picture_id', '=', 'pictures.id')
            ->whereIn('pictures.party_id', $legacyParties->pluck('id'))
            ->where('picture_sizes.size', 'original')
            ->count();
    }

    /**
     * Render the tally. Missing files are the legacy rows whose file is gone
     * from disk; orphan files never surface, the import being driven by the
     * database rather than by a filesystem walk.
     */
    private function report(): void
    {
        $this->table(['Résultat', 'Nombre'], [
            ['Parties importées', $this->counters['parties']],
            ['Photos importées', $this->counters['pictures']],
            ['Fichiers manquants', $this->counters['missing']],
            ['Erreurs', $this->counters['errors']],
        ]);
    }
```

- [x] **Step 4 : Lancer les tests, vérifier qu'ils passent**

Run: `vendor/bin/sail artisan test --compact --filter=ImportLegacyData`
Expected: PASS (13 tests)

- [x] **Step 5 : Formater et committer**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add app/Console/Commands/Party/ImportLegacyData.php tests/Feature/Console/ImportLegacyDataTest.php
git commit -m "feat(migration): survive broken files and report the tally"
```

---

### Task 5 : Mode simulation `--dry-run`

**Files:**
- Modify: `app/Console/Commands/Party/ImportLegacyData.php`
- Test: `tests/Feature/Console/ImportLegacyDataTest.php`

**Interfaces:**
- Consumes: `importParty()`, `importPicture()`, `$this->counters`.
- Produces: `private bool $dryRun`.

C'est la première commande que l'on lancera en production : elle donne le décompte réel et le nombre d'orphelins avant d'engager des heures de transfert.

- [x] **Step 1 : Écrire les tests qui échouent**

Ajouter au fichier de test :

```php
// ── Mode simulation ──────────────────────────────────────────────────────────

it('writes nothing at all in dry-run mode', function () {
    Storage::fake('s3');
    Queue::fake();

    legacyPicture(legacyParty());

    $this->artisan('party:import-legacy', [
        '--files' => legacyFilesRoot(),
        '--dry-run' => true,
    ])->assertSuccessful();

    expect(Party::count())->toBe(0)
        ->and(Picture::count())->toBe(0)
        ->and(Storage::disk('s3')->allFiles())->toBe([]);

    Queue::assertNothingPushed();
});

it('still counts pictures and missing files in dry-run mode', function () {
    Storage::fake('s3');
    Queue::fake();

    $legacyId = legacyParty();
    legacyPicture($legacyId);
    $orphanPath = legacyPicture($legacyId);

    unlink(legacyFilesRoot()."/{$orphanPath}");

    $this->artisan('party:import-legacy', [
        '--files' => legacyFilesRoot(),
        '--dry-run' => true,
    ])
        ->expectsOutputToContain('Parties importées')
        ->expectsOutputToContain('Fichiers manquants')
        ->assertSuccessful();
});
```

- [x] **Step 2 : Lancer les tests, vérifier qu'ils échouent**

Run: `vendor/bin/sail artisan test --compact --filter=ImportLegacyData`
Expected: FAIL — `--dry-run` écrit malgré tout : `Party::count()` vaut 1.

- [x] **Step 3 : Implémenter le mode simulation**

Ajouter la propriété :

```php
    /**
     * Whether the run only walks the source and reports, writing nothing.
     */
    private bool $dryRun = false;
```

Dans `handle()`, juste après l'affectation de `$this->filesRoot` :

```php
        $this->dryRun = (bool) $this->option('dry-run');

        if ($this->dryRun) {
            $this->components->warn('Dry run: nothing will be written to the database nor to S3.');
        }
```

Dans `importParty()`, remplacer le bloc qui suit le `if ($party->exists)` par :

```php
        $party->fill([
            'name' => $legacyParty->name,
            'display_name' => $legacyParty->title,
            'description' => $legacyParty->description,
            'expire_at' => $legacyParty->hide_pictures_date,
            'user_id' => null,
        ]);

        $this->counters['parties']++;

        if ($this->dryRun) {
            return $party;
        }

        /**
         * Timestamps are guarded, and letting Eloquent stamp them would date
         * every imported gallery from the night of the migration.
         */
        $party->forceFill([
            'created_at' => $legacyParty->created_at,
            'updated_at' => $legacyParty->updated_at,
        ])->save();

        return $party;
```

Dans `importPicture()`, insérer juste après le garde `if (! is_file($source))` :

```php
            if ($this->dryRun) {
                $this->counters['pictures']++;

                return;
            }
```

En mode simulation, la party n'est pas persistée et n'a donc pas d'identifiant : c'est précisément pourquoi la garde se place avant le calcul du chemin S3. La simulation compte et détecte les fichiers absents, elle ne prédit pas les chemins.

- [x] **Step 4 : Lancer les tests, vérifier qu'ils passent**

Run: `vendor/bin/sail artisan test --compact --filter=ImportLegacyData`
Expected: PASS (15 tests)

- [x] **Step 5 : Lancer la suite complète pour vérifier l'absence de régression**

Run: `vendor/bin/sail artisan test --compact`
Expected: PASS — les tests `tests/Web/` peuvent flaker sur un timeout de 5 s sans régression réelle ; les relancer avant d'enquêter.

- [x] **Step 6 : Formater et committer**

```bash
vendor/bin/sail bin pint --dirty --format agent
git add app/Console/Commands/Party/ImportLegacyData.php tests/Feature/Console/ImportLegacyDataTest.php
git commit -m "feat(migration): add the dry-run mode to the legacy import"
```

---

## Déroulé opérationnel (après implémentation)

Ce n'est pas une tâche de développement, mais la raison d'être du plan. Volume mesuré le 2026-08-31 : **47 Go**, **185 481 fichiers**, ~62 000 photos.

1. `rsync -a` du `public/` de la production vers la machine d'import ; dump de la base legacy.
2. Restaurer le dump, renseigner `LEGACY_DB_*` dans `.env`.
3. `vendor/bin/sail artisan party:import-legacy --files=/chemin/public --dry-run` — décompte et orphelins.
4. `… --party=<une-galerie>` puis contrôle visuel de la galerie publique.
5. Import complet sous `tmux`, workers de queue lancés en parallèle pour les miniatures.
6. Contrôle final : nombre de parties, nombre de photos, et `Picture::whereNull('thumbnail_path')->count()` une fois la queue vidée.

---

## Écarts constatés à l'exécution

Le plan a été suivi sans modification : les cinq tâches ont été implémentées telles qu'écrites, et les 15 tests passent.

Deux points relevés en cours de route :

- **`tests/Web/GalleryEditorTest.php` échoue**, de façon reproductible et non par flake. Vérification faite au point de branchement (`7275516`) : l'échec y est déjà présent. C'est une régression préexistante de `feature/gallery-host-editor`, sans rapport avec l'import, et elle reste à traiter sur cette branche-là.
- La connexion `legacy` a été insérée dans `config/database.php` juste avant le bloc `pgsql`, c'est-à-dire immédiatement après `mariadb` comme prévu.

Le reste de la suite passe : 385 tests, 2 178 assertions.
