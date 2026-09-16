<?php

use App\Models\Party;
use App\Models\Picture;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('s3');
});

/**
 * Stubs the tracker the way Umami's script would install it, and reports the
 * last event through the page title, the only channel a browser assertion can
 * read without depending on the tracker's own network calls.
 */
function stubUmami(object $page, bool $withWebShare = false): void
{
    /*
     * visit() hands back the page while the navigation is still in flight, so
     * the stub needs this barrier or it lands on the transient about:blank
     * document that the real page then replaces.
     */
    $page->assertPresent('[data-gallery-hero]');

    $webShare = $withWebShare ? <<<'JS'
        window.fetch = () => Promise.resolve(new Response(new Blob(['x'], { type: 'image/jpeg' })));
        navigator.canShare = () => true;
        navigator.share = () => Promise.resolve();
    JS : '';

    $page->script(<<<JS
        window.umami = {
            track: (event, data) => {
                document.title = 'ev:' + event + ':' + (data === undefined ? '' : data.count);
            },
        };

        {$webShare}

        // The evaluated script must not return a function: Playwright cannot
        // serialise one back to the test process.
        true;
    JS);
}

it('tracks a picture opened in the lightbox', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(3)->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    stubUmami($page);

    $page->click('#gallery a:first-child')
        ->assertTitleContains('ev:photo_open:')
        ->assertNoJavaScriptErrors();
});

it('tracks a zip download with the size of the selection', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(3)->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    stubUmami($page);

    $page->click('Sélectionner')
        ->click('Tout sélectionner')
        ->click('[data-download-button]')
        ->assertTitleContains('ev:download:3')
        ->assertNoJavaScriptErrors();
});

it('tracks nothing when the selection is refused for being too large', function () {
    config()->set('party.download.max_pictures', 2);

    $party = Party::factory()->create(['display_name' => 'Galerie sans événement']);
    Picture::factory()->count(3)->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    stubUmami($page);

    /* The untouched title is what says no event was recorded. */
    $page->click('Sélectionner')
        ->click('Tout sélectionner')
        ->click('[data-download-button]')
        ->assertSee('Sélection trop grande')
        ->assertTitleContains('Galerie sans événement')
        ->assertNoJavaScriptErrors();
});

it('tracks a native share with the size of the selection', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(2)->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    stubUmami($page, withWebShare: true);

    $page->click('Sélectionner')
        ->click('Tout sélectionner')
        ->click('[data-download-button]')
        ->assertTitleContains('ev:share:2')
        ->assertNoJavaScriptErrors();
});
