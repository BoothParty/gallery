<?php

use App\Models\Party;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('s3');
});

// ── Visibility ──────────────────────────────────────────────────────────────

it('hides the edit button from a plain visitor', function () {
    $party = Party::factory()->create();

    $page = browserPage(fn () => visit(partyUrl($party)));

    $page->assertPresent('[data-gallery-hero]')
        ->assertMissing('[data-gallery-edit]')
        ->assertNoJavaScriptErrors();
});

it('shows the edit button to whoever opens the tokenised link', function () {
    $party = Party::factory()->create();

    $page = browserPage(fn () => visit(partyUrl($party, '/?token='.$party->host_token)));

    $page->assertPresent('[data-gallery-edit]')
        ->assertSee('Modifier la galerie')
        ->assertNoJavaScriptErrors();
});

it('shows no edit button for a wrong token', function () {
    $party = Party::factory()->create();

    $page = browserPage(fn () => visit(partyUrl($party, '/?token=wrong')));

    $page->assertMissing('[data-gallery-edit]')
        ->assertNoJavaScriptErrors();
});

// ── Editing ─────────────────────────────────────────────────────────────────

it('saves a new title from the editor', function () {
    $party = Party::factory()->create(['name' => 'noces-de-juin']);

    $page = browserPage(fn () => visit(partyUrl($party, '/?token='.$party->host_token)));

    $page->click('[data-gallery-edit]')
        ->type('[data-gallery-editor] input[placeholder="Camille & Antoine"]', 'Camille & Antoine')
        ->click('[data-gallery-editor] button[type="submit"]')
        ->assertSeeIn('[data-gallery-hero]', 'Camille & Antoine')
        ->assertNoJavaScriptErrors();

    expect($party->fresh()->display_name)->toBe('Camille & Antoine');
});

it('offers no removal when the party has no cover', function () {
    $party = Party::factory()->create();

    $page = browserPage(fn () => visit(partyUrl($party, '/?token='.$party->host_token)));

    $page->click('[data-gallery-edit]')
        ->assertSee('Importer une photo')
        ->assertMissing('[data-cover-remove]')
        ->assertNoJavaScriptErrors();
});

it('removes the cover and falls back to the typographic hero', function () {
    $party = Party::factory()->withBanner()->create();

    $page = browserPage(fn () => visit(partyUrl($party, '/?token='.$party->host_token)));

    $page->assertPresent('[data-gallery-banner]')
        ->click('[data-gallery-edit]')
        ->click('[data-cover-remove]')
        ->assertMissing('[data-cover-frame] img')
        ->assertSee('Aucune photo de couverture')
        ->click('[data-gallery-editor] button[type="submit"]')
        ->assertMissing('[data-gallery-banner]')
        ->assertNoJavaScriptErrors();

    expect($party->fresh()->banner_path)->toBeNull();
});

/*
 * The Pest browser driver does not forward multipart bodies to Laravel
 * (see Pest\Browser\Drivers\LaravelHttpServer: "@TODO files..."), so the
 * upload request itself is asserted in tests/Feature/PartyMetadataTest.php.
 * This one covers the picker up to the submit.
 */
it('previews an imported cover inside the crop frame', function () {
    $party = Party::factory()->create(['name' => 'noces-de-juin']);

    $page = browserPage(fn () => visit(partyUrl($party, '/?token='.$party->host_token)));

    $page->click('[data-gallery-edit]')
        ->assertMissing('[data-cover-frame] img')
        ->attach('[data-gallery-editor] input[type="file"]', base_path('tests/Fixtures/cover.jpg'))
        ->assertPresent('[data-cover-frame] img')
        ->assertDontSee('Aucune photo de couverture')
        ->assertPresent('[data-cover-remove]')
        ->assertNoJavaScriptErrors();
});
