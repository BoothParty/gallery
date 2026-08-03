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

// ── Pagination ────────────────────────────────────────────────────────────────

it('displays pagination controls', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(5)->for($party)->create();

    $page = visit(route('party.gallery', $party));

    $page->assertSee('Page précédente')
        ->assertSee('Page suivante')
        ->assertSee('1 / 1 pages')
        ->assertNoJavaScriptErrors();
});

it('shows correct page count with many pictures', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(31)->for($party)->create();

    $page = visit(route('party.gallery', $party));

    $page->assertSee('1 / 2 pages')
        ->assertNoJavaScriptErrors();
});

it('navigates to the next page when clicking next button', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(31)->for($party)->create();

    $page = visit(route('party.gallery', $party));

    $page->assertSee('1 / 2 pages')
        ->click('Page suivante')
        ->assertQueryStringHas('page', '2')
        ->assertSee('2 / 2 pages')
        ->assertNoJavaScriptErrors();
});

it('disables previous button on the first page', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(31)->for($party)->create();

    $page = visit(route('party.gallery', $party));

    $page->assertButtonDisabled('Page précédente')
        ->assertButtonEnabled('Page suivante')
        ->assertNoJavaScriptErrors();
});

it('disables next button on the last page', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(31)->for($party)->create();

    $page = visit(route('party.gallery', ['party' => $party, 'page' => 2]));

    $page->assertButtonDisabled('Page suivante')
        ->assertButtonEnabled('Page précédente')
        ->assertNoJavaScriptErrors();
});

// ── Navigation ────────────────────────────────────────────────────────────────

it('displays the navigation bar', function () {
    $party = Party::factory()->create();

    $page = visit(route('party.gallery', $party));

    $page->assertSee('Accueil')
        ->assertSee('Photos')
        ->assertNoJavaScriptErrors();
});

// ── Footer ────────────────────────────────────────────────────────────────────

it('displays the footer', function () {
    $party = Party::factory()->create();

    $page = visit(route('party.gallery', $party));

    $page->assertSee('Louez moi')
        ->assertSee('Mentions légales')
        ->assertNoJavaScriptErrors();
});

// ── No smoke ──────────────────────────────────────────────────────────────────

it('has no smoke on the gallery page', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(3)->for($party)->create();

    $page = visit(route('party.gallery', $party));

    $page->assertNoSmoke();
});
