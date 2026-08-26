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

it('renders the gallery page with the party name', function () {
    $party = Party::factory()->create(['name' => 'Mariage de Paul']);

    $page = visit(route('party.gallery', $party));

    $page->assertSee('PhotoBooth')
        ->assertSee('Retrouves ici toutes les photos prises avec le photobooth')
        ->assertNoJavaScriptErrors();
});

it('displays the party name in the page title', function () {
    $party = Party::factory()->create(['name' => 'Soirée Anniversaire']);

    $page = visit(route('party.gallery', $party));

    $page->assertTitleContains('Soirée Anniversaire')
        ->assertNoJavaScriptErrors();
});

// ── Empty state ───────────────────────────────────────────────────────────────

it('shows empty state message when party has no pictures', function () {
    $party = Party::factory()->create();

    $page = visit(route('party.gallery', $party));

    $page->assertSee("Il n'y a pas encore de photos ici")
        ->assertNoJavaScriptErrors();
});

// ── Pictures display ──────────────────────────────────────────────────────────

it('displays pictures when the party has some', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(3)->for($party)->create();

    $page = visit(route('party.gallery', $party));

    $page->assertDontSee("Il n'y a pas encore de photos ici")
        ->assertCount('#gallery img', 3)
        ->assertNoJavaScriptErrors();
});

it('lazy loads pictures below the first row', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(10)->for($party)->create();

    $page = visit(route('party.gallery', $party));

    $page->assertCount('#gallery img[loading="eager"]', 6)
        ->assertCount('#gallery img[loading="lazy"]', 4)
        ->assertCount('#gallery img[decoding="async"]', 10)
        ->assertNoJavaScriptErrors();
});

it('does not lazy load anything when a party has only a first row of pictures', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(3)->for($party)->create();

    $page = visit(route('party.gallery', $party));

    $page->assertCount('#gallery img[loading="eager"]', 3)
        ->assertCount('#gallery img[loading="lazy"]', 0)
        ->assertNoJavaScriptErrors();
});

it('shows a placeholder instead of the original when the thumbnail is missing', function () {
    $party = Party::factory()->create();
    Picture::factory()->for($party)->withoutThumbnail()->create();

    $page = visit(route('party.gallery', $party));

    $page->assertCount('#gallery [data-picture-placeholder]', 1)
        ->assertCount('#gallery img', 0)
        ->assertNoJavaScriptErrors();
});

it('reserves the picture height up front to avoid layout shift', function () {
    $party = Party::factory()->create();
    Picture::factory()->for($party)->create();

    $page = visit(route('party.gallery', $party));

    $page->assertAttribute('#gallery img', 'width', '1600')
        ->assertAttribute('#gallery img', 'height', '1200')
        ->assertNoJavaScriptErrors();
});

it('falls back to a square ratio for a picture stored without dimensions', function () {
    $party = Party::factory()->create();
    Picture::factory()->for($party)->withoutDimensions()->create();

    $page = visit(route('party.gallery', $party));

    $page->assertPresent('#gallery img')
        ->assertNoJavaScriptErrors();
});

// ── Expired party ─────────────────────────────────────────────────────────────

it('shows hidden message for an expired party', function () {
    $party = Party::factory()->expired()->create();
    Picture::factory()->count(2)->for($party)->create();

    $page = visit(route('party.gallery', $party));

    $page->assertSee('les photos sont désormais cachées')
        ->assertNoJavaScriptErrors();
});

it('does not show pictures for an expired party', function () {
    $party = Party::factory()->expired()->create();
    Picture::factory()->count(2)->for($party)->create();

    $page = visit(route('party.gallery', $party));

    $page->assertCount('#gallery img', 0)
        ->assertNoJavaScriptErrors();
});

// ── Chargement au scroll ──────────────────────────────────────────────────────

it('no longer displays pagination controls', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(5)->for($party)->create();

    $page = visit(route('party.gallery', $party));

    $page->assertDontSee('Page suivante')
        ->assertDontSee('Page précédente')
        ->assertNoJavaScriptErrors();
});

it('loads only the first batch of pictures on arrival', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(30)->for($party)->create();

    $page = visit(route('party.gallery', $party));

    $page->assertCount('#gallery img', 24)
        ->assertNoJavaScriptErrors();
});

it('loads a second batch of pictures when scrolling to the bottom', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(30)->for($party)->create();

    $page = visit(route('party.gallery', $party));

    $page->assertCount('#gallery img', 24)
        ->script('window.scrollTo(0, document.body.scrollHeight)');

    $page->wait(2)
        ->assertCount('#gallery img', 30)
        ->assertNoJavaScriptErrors();
});

// ── Chrome de page ────────────────────────────────────────────────────────────

it('no longer displays the old navigation bar', function () {
    $party = Party::factory()->create();

    $page = visit(route('party.gallery', $party));

    $page->assertDontSee('Accueil')
        ->assertNoJavaScriptErrors();
});

// ── Footer ────────────────────────────────────────────────────────────────────

it('displays the footer call to action', function () {
    $party = Party::factory()->create();

    $page = visit(route('party.gallery', $party));

    $page->assertSee('Une borne photo pour votre événement')
        ->assertSee('Demander un devis')
        ->assertNoJavaScriptErrors();
});

it('displays the footer legal links', function () {
    $party = Party::factory()->create();

    $page = visit(route('party.gallery', $party));

    $page->assertSee('Mentions légales')
        ->assertSee('Confidentialité')
        ->assertSee('SIRET')
        ->assertNoJavaScriptErrors();
});

it('displays the footer social links', function () {
    $party = Party::factory()->create();

    $page = visit(route('party.gallery', $party));

    $page->assertPresent('footer a[aria-label="Instagram"]')
        ->assertPresent('footer a[aria-label="Facebook"]')
        ->assertPresent('footer a[aria-label="LinkedIn"]')
        ->assertNoJavaScriptErrors();
});

// ── No smoke ──────────────────────────────────────────────────────────────────

it('has no smoke on the gallery page', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(3)->for($party)->create();

    $page = visit(route('party.gallery', $party));

    $page->assertNoSmoke();
});
