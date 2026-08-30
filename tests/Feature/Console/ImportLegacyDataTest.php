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
