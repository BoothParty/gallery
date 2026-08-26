<?php

use App\Models\Party;
use App\Models\Picture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
});

/**
 * Puts real bytes behind each picture so the ZIP stream has something to read.
 *
 * @param  Collection<int, Picture>  $pictures
 */
function storePictureFiles($pictures): void
{
    $pictures->each(function (Picture $picture): void {
        Storage::disk('s3')->put($picture->path, 'fake-jpeg-bytes');
    });
}

// ── Happy path ────────────────────────────────────────────────────────────────

it('streams a zip of the selected pictures', function () {
    $party = Party::factory()->create(['name' => 'mariage-paul']);
    $pictures = Picture::factory()->count(3)->for($party)->create();
    storePictureFiles($pictures);

    $response = $this->post(route('party.download', $party), [
        'ids' => $pictures->pluck('id')->all(),
    ]);

    $response->assertOk()
        ->assertHeader('content-type', 'application/zip');

    expect($response->headers->get('content-disposition'))->toContain('mariage-paul-photos.zip');
});

it('writes one entry per selected picture', function () {
    $party = Party::factory()->create();
    $pictures = Picture::factory()->count(2)->for($party)->create();
    storePictureFiles($pictures);

    $response = $this->post(route('party.download', $party), [
        'ids' => $pictures->pluck('id')->all(),
    ]);

    $archive = $response->streamedContent();

    expect($archive)->toContain('photo-1.jpg')
        ->and($archive)->toContain('photo-2.jpg');
});

it('downloads a single selected picture as a zip too', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create();
    storePictureFiles(collect([$picture]));

    $this->post(route('party.download', $party), ['ids' => [$picture->id]])
        ->assertOk()
        ->assertHeader('content-type', 'application/zip');
});

// ── Scoping ───────────────────────────────────────────────────────────────────

it('rejects a picture belonging to another party', function () {
    $party = Party::factory()->create();
    $otherParty = Party::factory()->create();
    $foreign = Picture::factory()->for($otherParty)->create();

    $this->post(route('party.download', $party), ['ids' => [$foreign->id]])
        ->assertSessionHasErrors('ids.0');
});

it('rejects an unknown picture id', function () {
    $party = Party::factory()->create();

    $this->post(route('party.download', $party), ['ids' => [999999]])
        ->assertSessionHasErrors('ids.0');
});

// ── Guard rails ───────────────────────────────────────────────────────────────

it('rejects an empty selection', function () {
    $party = Party::factory()->create();

    $this->post(route('party.download', $party), ['ids' => []])
        ->assertSessionHasErrors('ids');
});

it('rejects a selection above the configured cap', function () {
    config()->set('party.download.max_pictures', 2);

    $party = Party::factory()->create();
    $pictures = Picture::factory()->count(3)->for($party)->create();
    storePictureFiles($pictures);

    $this->post(route('party.download', $party), ['ids' => $pictures->pluck('id')->all()])
        ->assertSessionHasErrors('ids');
});

it('refuses to download an expired party', function () {
    $party = Party::factory()->expired()->create();
    $pictures = Picture::factory()->count(2)->for($party)->create();
    storePictureFiles($pictures);

    $this->post(route('party.download', $party), ['ids' => $pictures->pluck('id')->all()])
        ->assertForbidden();
});

it('throttles repeated download requests', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create();
    storePictureFiles(collect([$picture]));

    foreach (range(1, 10) as $ignored) {
        $this->post(route('party.download', $party), ['ids' => [$picture->id]])->assertOk();
    }

    $this->post(route('party.download', $party), ['ids' => [$picture->id]])
        ->assertStatus(429);
});
