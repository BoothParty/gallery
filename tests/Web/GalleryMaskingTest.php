<?php

use App\Models\Party;
use App\Models\Picture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
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

it('keeps the loaded grid in place when a picture is masked', function () {
    $party = Party::factory()->create();
    $pictures = Picture::factory()->count(30)->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party).'?token='.$party->host_token));

    $page->click('[data-hide-button="'.$pictures->last()->id.'"]')
        ->assertPresent('[data-picture-hidden]')
        ->assertSeeIn('[data-gallery-count]', '30')
        ->assertNoJavaScriptErrors();
});
