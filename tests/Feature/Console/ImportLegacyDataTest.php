<?php

use App\Jobs\CreatePictureThumbnail;
use App\Models\Party;
use App\Models\Picture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;

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
