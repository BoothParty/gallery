<?php

use App\Models\Party;
use App\Models\Picture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
    Queue::fake();
});

/**
 * Copy a fake upload to a real, properly named file the browser can attach.
 */
function browserAttachablePath(UploadedFile $file): string
{
    $path = sys_get_temp_dir().'/pest-browser-uploads/'.$file->getClientOriginalName();

    File::ensureDirectoryExists(dirname($path));
    File::copy($file->getRealPath(), $path);

    return $path;
}

/*
 * The Pest browser driver does not forward multipart bodies to Laravel
 * (see Pest\Browser\Drivers\LaravelHttpServer: "@TODO files..."), so the
 * upload request itself is asserted in tests/Feature/AdminGalleryTest.php.
 * These tests cover the dialog behaviour up to the submit.
 */

// ── Upload dialog ─────────────────────────────────────────────────────────────

it('opens the upload dialog from the toolbar', function () {
    $party = Party::factory()->create();

    $page = visit(route('party.dashboard.pictures', $party));

    $page->assertDontSee('Select one or more images to add to this party.')
        ->click('Upload pictures')
        ->assertSee('Select one or more images to add to this party.')
        ->assertVisible('#upload-photos')
        ->assertNoJavaScriptErrors();
});

it('only accepts multiple images in the file field', function () {
    $party = Party::factory()->create();

    $page = visit(route('party.dashboard.pictures', $party));

    $page->click('Upload pictures')
        ->assertAttribute('#upload-photos', 'accept', 'image/*')
        ->assertAttribute('#upload-photos', 'type', 'file')
        ->assertPresent('#upload-photos[multiple]')
        ->assertNoJavaScriptErrors();
});

it('keeps the upload button disabled until an image is attached', function () {
    $party = Party::factory()->create();

    $page = visit(route('party.dashboard.pictures', $party));

    $page->click('Upload pictures')
        ->assertButtonDisabled('Upload')
        ->attach('#upload-photos', browserAttachablePath(
            UploadedFile::fake()->image('enabled.jpg', 800, 600)
        ))
        ->assertButtonEnabled('Upload')
        ->assertNoJavaScriptErrors();
});

it('closes the upload dialog and clears the attached file when cancelling', function () {
    $party = Party::factory()->create();

    $page = visit(route('party.dashboard.pictures', $party));

    $page->click('Upload pictures')
        ->attach('#upload-photos', browserAttachablePath(
            UploadedFile::fake()->image('cancelled.jpg', 800, 600)
        ))
        ->click('Cancel')
        ->assertDontSee('Select one or more images to add to this party.')
        ->click('Upload pictures')
        ->assertValue('#upload-photos', '')
        ->assertButtonDisabled('Upload')
        ->assertNoJavaScriptErrors();

    expect(Picture::query()->where('party_id', $party->id)->exists())->toBeFalse();
    Queue::assertNothingPushed();
});

it('closes the upload dialog and clears the attached file when pressing escape', function () {
    $party = Party::factory()->create();

    $page = visit(route('party.dashboard.pictures', $party));

    $page->click('Upload pictures')
        ->attach('#upload-photos', browserAttachablePath(
            UploadedFile::fake()->image('escaped.jpg', 800, 600)
        ))
        ->keys('#upload-photos', 'Escape')
        ->assertDontSee('Select one or more images to add to this party.')
        ->click('Upload pictures')
        ->assertValue('#upload-photos', '')
        ->assertButtonDisabled('Upload')
        ->assertNoJavaScriptErrors();

    expect(Picture::query()->where('party_id', $party->id)->exists())->toBeFalse();
    Queue::assertNothingPushed();
});

// ── Picture grid ──────────────────────────────────────────────────────────────

it('lazy loads pictures below the first row', function () {
    $party = Party::factory()->create();
    Picture::factory()->forParty($party)->count(10)->create();

    $page = visit(route('party.dashboard.pictures', $party));

    $page->assertCount('img[loading="eager"]', 6)
        ->assertCount('img[loading="lazy"]', 4)
        ->assertCount('img[decoding="async"]', 10)
        ->assertNoJavaScriptErrors();
});

it('shows a placeholder instead of the original when the thumbnail is missing', function () {
    $party = Party::factory()->create();
    Picture::factory()->forParty($party)->withoutThumbnail()->create();

    $page = visit(route('party.dashboard.pictures', $party));

    $page->assertCount('[data-picture-placeholder]', 1)
        ->assertNoJavaScriptErrors();
});

// ── Page ──────────────────────────────────────────────────────────────────────

it('has no smoke on the administration gallery page', function () {
    $party = Party::factory()->create();
    Picture::factory()->forParty($party)->count(3)->create();

    $page = visit(route('party.dashboard.pictures', $party));

    $page->assertSee('3 pictures')
        ->assertNoSmoke();
});
