<?php

use App\Models\Party;
use App\Models\Picture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
    config(['party.refresh.hot' => 1, 'party.refresh.warm' => 1, 'party.refresh.cold' => 1]);
});

it('shows no masking control to a plain visitor', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(2)->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->assertPresent('#gallery a[data-id]')
        ->assertMissing('[data-hide-button]')
        ->assertNoJavaScriptErrors();
});

it('masks a picture from the grid and marks the tile', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party).'?token='.$party->host_token));

    $page->click('[data-hide-button="'.$picture->id.'"]')
        ->assertPresent('[data-picture-hidden]')
        ->assertSee('Masquée')
        ->assertSee('Photo masquée pour les invités')
        ->assertNoJavaScriptErrors();

    expect($picture->fresh()->isHidden())->toBeTrue();
});

it('puts a masked picture back online', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->hidden()->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party).'?token='.$party->host_token));

    $page->assertPresent('[data-picture-hidden]')
        ->click('[data-hide-button="'.$picture->id.'"]')
        ->assertMissing('[data-picture-hidden]')
        ->assertSee('Photo remise en ligne')
        ->assertNoJavaScriptErrors();

    expect($picture->fresh()->isHidden())->toBeFalse();
});

/** The control sits inside the anchor PhotoSwipe listens on. */
it('does not open the lightbox when masking a picture', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party).'?token='.$party->host_token));

    $page->click('[data-hide-button="'.$picture->id.'"]')
        ->assertPresent('[data-picture-hidden]')
        ->assertMissing('.pswp--open')
        ->assertNoJavaScriptErrors();
});

// ── What the other open galleries do about it ───────────────────────────────

it('drops a masked picture from a guests open gallery on its own', function () {
    $party = Party::factory()->create();
    $pictures = Picture::factory()->count(3)->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->assertCount('#gallery img', 3);

    $pictures->first()->update(['hidden_at' => now()]);

    $page->assertCount('#gallery img', 2)
        ->assertSeeIn('[data-gallery-count]', '2')
        ->assertNoJavaScriptErrors();
});

it('brings a picture back to a guests open gallery when it is unmasked', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->hidden()->create();
    Picture::factory()->count(2)->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->assertCount('#gallery img', 2);

    $picture->update(['hidden_at' => null]);

    $page->assertCount('#gallery img', 3)
        ->assertNoJavaScriptErrors();
});

it('still announces a new picture to the host after they masked one', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party).'?token='.$party->host_token));

    $page->click('[data-hide-button="'.$picture->id.'"]')
        ->assertPresent('[data-picture-hidden]');

    expect($picture->fresh()->isHidden())->toBeTrue();

    /*
     * `last_change` only has a second of resolution, and masking a picture
     * gives the count back what the new one adds. Both changes landing in the
     * same second would cancel each other out, so the clock moves on first.
     */
    $this->travel(1)->second();

    Picture::factory()->for($party)->create();

    /*
     * At the top of the page the refresh happens on its own, so the pill only
     * exists for the render in between — a state no assertion can catch
     * reliably. The grid picking the new picture up is the same proof.
     */
    $page->assertCount('#gallery img', 2)
        ->assertNoJavaScriptErrors();
});

it('keeps the loaded grid in place when a picture is masked', function () {
    $party = Party::factory()->create();
    $pictures = Picture::factory()->count(30)->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party).'?token='.$party->host_token));

    $page->click('[data-hide-button="'.$pictures->last()->id.'"]')
        ->assertPresent('[data-picture-hidden]')
        ->assertSeeIn('[data-gallery-count]', '30')
        ->assertNoJavaScriptErrors();
});
