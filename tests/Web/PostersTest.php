<?php

use App\Models\Party;
use App\Services\PosterBatchLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

/**
 * The signed page URL a QR code would carry.
 */
function postersPageUrl(array $partyIds): string
{
    return URL::temporarySignedRoute(
        'posters.index',
        now()->addHours(PosterBatchLink::LIFETIME_IN_HOURS),
        ['parties' => implode(',', $partyIds)],
    );
}

/**
 * Stubs the Web Share API and the poster fetch, so the test covers the wiring
 * without generating real posters. The bytes have their own coverage in
 * tests/Feature/PosterFileTest.php.
 */
function stubPosterShare(object $page, string $shareResult = 'resolve'): void
{
    // visit() hands back the page while the navigation is still in flight, and
    // script() runs at once instead of retrying like the assertions do. Without
    // this barrier the stubs land on the transient about:blank document, which
    // the real page then replaces.
    $page->assertPresent('#share-posters');

    $page->script(<<<JS
        window.fetch = () => Promise.resolve(new Response(new Blob(['x'], { type: 'image/png' })));
        navigator.canShare = () => true;
        navigator.share = (data) => {
            document.title = 'shared:' + data.files.length;

            return '{$shareResult}' === 'resolve'
                ? Promise.resolve()
                : Promise.reject(new DOMException('cancelled', 'AbortError'));
        };

        // The evaluated script must not return a function: Playwright cannot
        // serialise one back to the test process.
        true;
    JS);
}

it('lists the parties of the batch', function () {
    $first = Party::factory()->create(['name' => 'premiere-soiree']);
    $second = Party::factory()->create(['name' => 'seconde-soiree']);

    $page = browserPage(fn () => visit(postersPageUrl([$first->id, $second->id])));

    $page->assertSee('premiere-soiree')
        ->assertSee('seconde-soiree')
        ->assertNoJavaScriptErrors();
});

it('shares every poster of the batch at once', function () {
    $first = Party::factory()->create();
    $second = Party::factory()->create();

    $page = browserPage(fn () => visit(postersPageUrl([$first->id, $second->id])));

    stubPosterShare($page);

    $page->click('#share-posters')
        ->assertTitleContains('shared:2')
        ->assertNoJavaScriptErrors();
});

it('stays quiet when the share sheet is dismissed', function () {
    $party = Party::factory()->create();

    $page = browserPage(fn () => visit(postersPageUrl([$party->id])));

    stubPosterShare($page, 'reject');

    $page->click('#share-posters')
        ->assertDontSee('Le téléchargement des affiches a échoué')
        ->assertNoJavaScriptErrors();
});

/*
 * No stub here on purpose: none of the three browsers driven by this suite
 * implements sharing files on Linux, so the button falls back on its own.
 */
it('falls back to download links without native sharing', function () {
    $party = Party::factory()->create(['name' => 'sans-partage']);

    $page = browserPage(fn () => visit(postersPageUrl([$party->id])));

    $page->click('#share-posters')
        ->assertVisible('[data-poster-link]')
        ->assertMissing('#share-posters')
        ->assertNoJavaScriptErrors();

    expect($page->attribute('[data-poster-link]', 'download'))->toBe('sans-partage-affiche.png');
});
