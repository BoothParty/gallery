<?php

use App\Models\Party;
use App\Models\Picture;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('s3');
    config(['party.refresh.hot' => 1, 'party.refresh.warm' => 1, 'party.refresh.cold' => 1]);
});

it('shows a picture that arrives after the page loaded', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(2)->for($party)->create();

    $page = browserPage(fn () => visit(partyUrl($party)));

    $page->assertCount('#gallery img', 2);

    Picture::factory()->for($party)->create();

    $page->assertCount('#gallery img', 3)
        ->assertMissing('[data-gallery-pill]')
        ->assertNoJavaScriptErrors();
});

it('fills an empty gallery on its own', function () {
    $party = Party::factory()->create();

    $page = browserPage(fn () => visit(partyUrl($party)));

    $page->assertPresent('[data-gallery-notice]');

    Picture::factory()->for($party)->create();

    $page->assertMissing('[data-gallery-notice]')
        ->assertCount('#gallery img', 1)
        ->assertNoJavaScriptErrors();
});

it('offers a pill instead of moving the grid while selecting', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(2)->for($party)->create();

    $page = browserPage(fn () => visit(partyUrl($party)));

    $page->click('[data-gallery-select]');

    Picture::factory()->for($party)->create();

    $page->assertSeeIn('[data-gallery-pill]', '1 nouvelle photo')
        ->assertCount('#gallery img', 2)
        ->assertNoJavaScriptErrors();
});

it('refreshes and hides the pill when it is tapped', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(2)->for($party)->create();

    $page = browserPage(fn () => visit(partyUrl($party)));

    $page->click('[data-gallery-select]');

    Picture::factory()->for($party)->create();

    $page->assertPresent('[data-gallery-pill]')
        ->click('[data-gallery-pill]')
        ->assertMissing('[data-gallery-pill]')
        ->assertCount('#gallery img', 3)
        ->assertNoJavaScriptErrors();
});

it('never polls an expired gallery', function () {
    $party = Party::factory()->expired()->create();

    $page = browserPage(fn () => visit(partyUrl($party)));

    $page->assertSeeIn('[data-gallery-notice]', 'Cette galerie a expiré');

    Picture::factory()->for($party)->create();

    $page->assertSeeIn('[data-gallery-notice]', 'Cette galerie a expiré')
        ->assertMissing('[data-gallery-pill]')
        ->assertCount('#gallery img', 0)
        ->assertNoJavaScriptErrors();
});

it('tells an empty gallery to stay open rather than to reload', function () {
    $party = Party::factory()->create();

    $page = browserPage(fn () => visit(partyUrl($party)));

    $page->assertSeeIn('[data-gallery-notice]', 'gardez cette page ouverte')
        ->assertDontSee('rafraîchissez la page')
        ->assertNoJavaScriptErrors();
});
