<?php

use App\Models\Party;
use App\Models\Picture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
});

// ── Page rendering ────────────────────────────────────────────────────────────

it('renders the gallery hero with the event metadata', function () {
    $party = Party::factory()->create([
        'name' => 'mariage-de-paul',
        'display_name' => 'Camille & Antoine',
        'description' => 'Samedi 12 septembre · Domaine des Ormes',
    ]);

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->assertPresent('[data-gallery-hero]')
        ->assertSee('Camille & Antoine')
        ->assertSee('Domaine des Ormes')
        ->assertNoJavaScriptErrors();
});

it('falls back to the party name when no display name is set', function () {
    $party = Party::factory()->create(['name' => 'Mariage de Paul']);

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->assertSeeIn('[data-gallery-hero]', 'Mariage de Paul')
        ->assertNoJavaScriptErrors();
});

it('renders the typographic hero when the party has no banner', function () {
    $party = Party::factory()->create(['display_name' => 'Camille & Antoine']);

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->assertPresent('[data-gallery-hero]')
        ->assertMissing('[data-gallery-banner]')
        ->assertSeeIn('[data-gallery-hero]', 'Camille & Antoine')
        ->assertDontSee("Galerie de l'événement")
        ->assertNoJavaScriptErrors();
});

it('renders the banner image when the party has one', function () {
    $party = Party::factory()->withBanner()->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->assertPresent('[data-gallery-banner]')
        ->assertAttribute('[data-gallery-banner]', 'src', $party->bannerUrl())
        ->assertNoJavaScriptErrors();
});

it('displays the event title in the page title', function () {
    $party = Party::factory()->create([
        'name' => 'soiree-anniversaire',
        'display_name' => 'Soirée Anniversaire',
    ]);

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->assertTitleContains('Soirée Anniversaire')
        ->assertNoJavaScriptErrors();
});

it('displays the total picture count in the sticky bar, not the loaded batch size', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(30)->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->assertSeeIn('[data-gallery-count]', '30')
        ->assertNoJavaScriptErrors();
});

// ── Empty state ───────────────────────────────────────────────────────────────

it('shows empty state message when party has no pictures', function () {
    $party = Party::factory()->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->assertSeeIn('[data-gallery-notice]', 'La galerie se remplit bientôt')
        ->assertSeeIn('[data-gallery-notice]', 'Aucune photo pour le moment')
        ->assertNoJavaScriptErrors();
});

it('hides the sticky bar when the party has no pictures', function () {
    $party = Party::factory()->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->assertMissing('[data-gallery-count]')
        ->assertDontSee('Sélectionner')
        ->assertNoJavaScriptErrors();
});

// ── Pictures display ──────────────────────────────────────────────────────────

it('displays pictures when the party has some', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(3)->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->assertMissing('[data-gallery-notice]')
        ->assertCount('#gallery img', 3)
        ->assertNoJavaScriptErrors();
});

it('lazy loads pictures below the first row', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(10)->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->assertCount('#gallery img[loading="eager"]', 6)
        ->assertCount('#gallery img[loading="lazy"]', 4)
        ->assertCount('#gallery img[decoding="async"]', 10)
        ->assertNoJavaScriptErrors();
});

it('shows a placeholder instead of the original when the thumbnail is missing', function () {
    $party = Party::factory()->create();
    Picture::factory()->for($party)->withoutThumbnail()->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->assertCount('#gallery [data-picture-placeholder]', 1)
        ->assertCount('#gallery img', 0)
        ->assertNoJavaScriptErrors();
});

it('links each tile to the full resolution picture for the lightbox', function () {
    $party = Party::factory()->create();
    Picture::factory()->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->assertAttribute('#gallery a', 'data-pswp-width', '1600')
        ->assertAttribute('#gallery a', 'data-pswp-height', '1200')
        ->assertNoJavaScriptErrors();
});

it('renders a tile for a picture stored without dimensions', function () {
    $party = Party::factory()->create();
    Picture::factory()->for($party)->withoutDimensions()->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->assertPresent('#gallery img')
        ->assertNoJavaScriptErrors();
});

it('shows the end of gallery marker once every picture is loaded', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(3)->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->assertSee('Fin de la galerie')
        ->assertNoJavaScriptErrors();
});

// ── Expired party ─────────────────────────────────────────────────────────────

it('shows hidden message for an expired party', function () {
    $party = Party::factory()->expired()->create();
    Picture::factory()->count(2)->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->assertSeeIn('[data-gallery-notice]', 'Cette galerie a expiré')
        ->assertSeeIn('[data-gallery-notice]', 'Les photos sont restées en ligne')
        ->assertNoJavaScriptErrors();
});

it('keeps the banner hero for an expired party', function () {
    $party = Party::factory()->expired()->withBanner()->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->assertPresent('[data-gallery-banner]')
        ->assertNoJavaScriptErrors();
});

it('hides the sticky bar for an expired party', function () {
    $party = Party::factory()->expired()->create();
    Picture::factory()->count(2)->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->assertMissing('[data-gallery-count]')
        ->assertNoJavaScriptErrors();
});

it('does not show pictures for an expired party', function () {
    $party = Party::factory()->expired()->create();
    Picture::factory()->count(2)->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->assertCount('#gallery img', 0)
        ->assertNoJavaScriptErrors();
});

// ── Chargement au scroll ──────────────────────────────────────────────────────

it('no longer displays pagination controls', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(5)->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->assertDontSee('Page suivante')
        ->assertDontSee('Page précédente')
        ->assertNoJavaScriptErrors();
});

it('loads only the first batch of pictures on arrival', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(30)->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->assertCount('#gallery img', 24)
        ->assertNoJavaScriptErrors();
});

it('loads a second batch of pictures when scrolling to the bottom', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(30)->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->assertCount('#gallery img', 24)
        ->script('window.scrollTo(0, document.body.scrollHeight)');

    $page->wait(2)
        ->assertCount('#gallery img', 30)
        ->assertNoJavaScriptErrors();
});

it('shows the loading skeletons while the next batch is fetched', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(30)->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->assertCount('#gallery img', 24)
        ->assertNotPresent('[data-gallery-skeletons]')
        ->script('window.scrollTo(0, document.body.scrollHeight)');

    $page->assertPresent('[data-gallery-skeletons]');

    $page->wait(2)
        ->assertCount('#gallery img', 30)
        ->assertNotPresent('[data-gallery-skeletons]')
        ->assertNoJavaScriptErrors();
});

// ── Chrome de page ────────────────────────────────────────────────────────────

it('no longer displays the old navigation bar', function () {
    $party = Party::factory()->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->assertDontSee('Accueil')
        ->assertNoJavaScriptErrors();
});

// ── Footer ────────────────────────────────────────────────────────────────────

it('displays the footer call to action', function () {
    $party = Party::factory()->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->assertSee('Louer un photobooth')
        ->assertNoJavaScriptErrors();
});

it('displays the footer legal links', function () {
    $party = Party::factory()->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->assertSee('Mentions légales')
        ->assertSee('Confidentialité')
        ->assertNoJavaScriptErrors();
});

it('displays the footer social links', function () {
    $party = Party::factory()->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->assertPresent('footer a[aria-label="Instagram"]')
        ->assertPresent('footer a[aria-label="Facebook"]')
        ->assertPresent('footer a[aria-label="LinkedIn"]')
        ->assertNoJavaScriptErrors();
});

// ── Mode sélection ────────────────────────────────────────────────────────────

it('reveals the selection controls when entering select mode', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(3)->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->click('Sélectionner')
        ->assertSee('Tout sélectionner')
        ->assertSee('Annuler')
        ->assertPresent('[data-selection-count]')
        ->assertNoJavaScriptErrors();
});

it('counts a picture picked in select mode', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(3)->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->click('Sélectionner')
        ->click('#gallery a:first-child')
        ->assertSeeIn('[data-selection-count]', '1')
        ->assertSee('photo sélectionnée')
        ->assertNoJavaScriptErrors();
});

it('selects every loaded picture at once', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(3)->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->click('Sélectionner')
        ->click('Tout sélectionner')
        ->assertSeeIn('[data-selection-count]', '3')
        ->assertSee('photos sélectionnées')
        ->assertSee('Tout désélectionner')
        ->assertNoJavaScriptErrors();
});

it('clears the selection when cancelling', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(3)->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->click('Sélectionner')
        ->click('Tout sélectionner')
        ->click('Annuler')
        ->assertSee('Sélectionner')
        ->assertDontSee('Tout désélectionner')
        ->assertNoJavaScriptErrors();
});

it('does not open the lightbox while in select mode', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(3)->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->click('Sélectionner')
        ->click('#gallery a:first-child')
        ->assertNotPresent('.pswp')
        ->assertNoJavaScriptErrors();
});

it('posts the selection to the download route', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(3)->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->click('Sélectionner')
        ->click('Tout sélectionner')
        ->assertAttribute('[data-download-form]', 'method', 'post')
        ->assertAttributeContains('[data-download-form]', 'action', '/download')
        ->assertCount('[data-download-form] input[name="ids[]"]', 3)
        ->assertNoJavaScriptErrors();
});

it('refuses to submit a selection above the cap', function () {
    config()->set('party.download.max_pictures', 2);

    $party = Party::factory()->create();
    Picture::factory()->count(3)->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->click('Sélectionner')
        ->click('Tout sélectionner')
        ->click('Télécharger le ZIP')
        ->assertSeeIn('[data-gallery-toast]', 'maximum')
        ->assertNoJavaScriptErrors();
});

// ── Sélection et défilement ────────────────────────────────────────────────────

it('keeps the current selection when a new batch arrives while selecting', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(30)->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->click('Sélectionner')
        ->click('#gallery a:first-child')
        ->assertSeeIn('[data-selection-count]', '1')
        ->script('window.scrollTo(0, document.body.scrollHeight)');

    $page->wait(2)
        ->assertCount('#gallery img', 30)
        ->assertSeeIn('[data-selection-count]', '1')
        ->assertSee('Tout sélectionner')
        ->assertNoJavaScriptErrors();
});

it('selects all 30 pictures, not just the first batch, after a new batch arrives', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(30)->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->assertCount('#gallery img', 24)
        ->script('window.scrollTo(0, document.body.scrollHeight)');

    $page->wait(2)
        ->assertCount('#gallery img', 30)
        ->click('Sélectionner')
        ->click('Tout sélectionner')
        ->assertSeeIn('[data-selection-count]', '30')
        ->assertNoJavaScriptErrors();
});

it('still opens the lightbox correctly after a new batch arrives', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(30)->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->assertCount('#gallery img', 24)
        ->script('window.scrollTo(0, document.body.scrollHeight)');

    $page->wait(2)
        ->assertCount('#gallery img', 30)
        ->click('#gallery a:first-child')
        ->assertPresent('.pswp')
        ->assertNoJavaScriptErrors();
});

// ── No smoke ──────────────────────────────────────────────────────────────────

it('has no smoke on the gallery page', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(3)->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->assertNoSmoke();
});

// ── Partage natif ─────────────────────────────────────────────────────────────

/**
 * Stubs the Web Share API and the picture fetch, so the browser test covers the
 * wiring without depending on real bytes sitting in the bucket. The route that
 * serves those bytes has its own coverage in tests/Feature/PictureFileTest.php.
 */
function stubWebShare(object $page, string $shareResult = 'resolve', int $fetchDelay = 0): void
{
    // visit() hands back the page while the navigation is still in flight, and
    // script() runs at once instead of retrying like the assertions do. Without
    // this barrier the stubs land on the transient about:blank document, which
    // the real page then replaces: silently, and only in Firefox.
    $page->assertPresent('[data-gallery-hero]');

    $page->script(<<<JS
        window.fetch = () => new Promise((resolve) => setTimeout(
            () => resolve(new Response(new Blob(['x'], { type: 'image/jpeg' }))),
            {$fetchDelay},
        ));
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

it('shares the selection natively when the browser supports it', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(3)->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    stubWebShare($page);

    $page->click('Sélectionner')
        ->click('Tout sélectionner')
        ->click('[data-download-button]')
        ->assertTitleContains('shared:3')
        ->assertNoJavaScriptErrors();
});

it('keeps the download label while sharing', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(2)->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    stubWebShare($page);

    $page->click('Sélectionner')
        ->click('Tout sélectionner')
        ->assertSeeIn('[data-download-button]', 'Télécharger')
        ->assertNoJavaScriptErrors();
});

it('shows the download progress while the files are fetched', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(3)->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    stubWebShare($page, 'resolve', 1500);

    $page->click('Sélectionner')
        ->click('Tout sélectionner')
        ->click('[data-download-button]')
        ->assertPresent('[data-download-progress]')
        ->assertSeeIn('[data-download-button]', 'Téléchargement…')
        ->assertNoJavaScriptErrors();
});

it('keeps the selection usable when the share sheet is dismissed', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(2)->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    stubWebShare($page, 'reject');

    $page->click('Sélectionner')
        ->click('Tout sélectionner')
        ->click('[data-download-button]')
        ->assertSeeIn('[data-selection-count]', '2')
        ->assertSeeIn('[data-download-button]', 'Télécharger')
        ->assertNoJavaScriptErrors();
});

it('falls back to the zip form when the browser cannot share files', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(2)->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->click('Sélectionner')
        ->click('Tout sélectionner')
        ->assertSeeIn('[data-download-button]', 'Télécharger le ZIP')
        ->assertNoJavaScriptErrors();
});

// ── Lightbox ──────────────────────────────────────────────────────────────────

it('opens the lightbox on a picture', function () {
    $party = Party::factory()->create();
    Picture::factory()->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->assertMissing('.pswp--open')
        ->click('#gallery a')
        ->assertPresent('.pswp--open')
        ->assertNoJavaScriptErrors();
});
