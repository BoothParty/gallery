<?php

use App\Models\Party;
use App\Models\Picture;
use App\Models\PrintJob;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('s3');
    config(['party.refresh.hot' => 1, 'party.refresh.warm' => 1, 'party.refresh.cold' => 1]);
});

it('shows no reprint button to a guest', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(2)->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party)));

    $page->click('Sélectionner')
        ->assertPresent('[data-selection-count]')
        ->assertMissing('[data-reprint-button]')
        ->assertNoJavaScriptErrors();
});

it('reprints the selection and marks the tiles', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(2)->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party).'?token='.$party->host_token));

    $page->click('Sélectionner')
        ->click('#gallery a:nth-child(1)')
        ->click('#gallery a:nth-child(2)')
        ->assertSeeIn('[data-selection-count]', '2')
        ->click('[data-reprint-button]')
        ->assertSee('2 photos envoyées à l\'impression')
        ->assertCount('[data-print-pending]', 2)
        ->assertDontSee('Tout sélectionner')
        ->assertNoJavaScriptErrors();

    expect(PrintJob::pending()->count())->toBe(2);
});

it('refuses a selection over the cap without sending it', function () {
    config(['party.download.max_pictures' => 1]);
    $party = Party::factory()->create();
    Picture::factory()->count(2)->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party).'?token='.$party->host_token));

    $page->click('Sélectionner')
        ->click('#gallery a:nth-child(1)')
        ->click('#gallery a:nth-child(2)')
        ->assertSeeIn('[data-selection-count]', '2')
        ->click('[data-reprint-button]')
        ->assertSee('Sélection trop grande : 1 photos maximum par réimpression')
        ->assertNoJavaScriptErrors();

    expect(PrintJob::count())->toBe(0);
});

it('toasts and keeps the selection when the party expires after page load', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(2)->for($party)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party).'?token='.$party->host_token));

    $page->click('Sélectionner')
        ->click('#gallery a:nth-child(1)')
        ->assertSeeIn('[data-selection-count]', '1');

    $party->update(['expire_at' => now()->subDay()]);

    $page->click('[data-reprint-button]')
        ->assertSee("La réimpression n'a pas pu être lancée")
        ->assertSeeIn('[data-selection-count]', '1')
        ->assertNoJavaScriptErrors();

    expect(PrintJob::count())->toBe(0);
});

// ── Cancelling ──────────────────────────────────────────────────────────────

it('cancels a pending print from its badge', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create();
    PrintJob::factory()->for($picture)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party).'?token='.$party->host_token));

    $page->click('[data-cancel-print="'.$picture->id.'"]')
        ->assertMissing('[data-print-pending]')
        ->assertSee('Impression annulée')
        ->assertMissing('.pswp--open')
        ->assertNoJavaScriptErrors();

    expect(PrintJob::count())->toBe(0);
});

it('offers no cancel control in select mode', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create();
    PrintJob::factory()->for($picture)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party).'?token='.$party->host_token));

    $page->click('Sélectionner')
        ->assertPresent('[data-print-pending]')
        ->assertMissing('[data-cancel-print]')
        ->assertNoJavaScriptErrors();
});

it('puts the badge back when the cancellation fails', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create();
    PrintJob::factory()->for($picture)->create();

    $page = browserPage(fn () => visit(route('party.gallery', $party).'?token='.$party->host_token));

    $page->assertPresent('[data-cancel-print="'.$picture->id.'"]');

    /*
     * The picture is gone by the time the host taps, so the route answers 404.
     * Quietly, so the gallery's polled state stays cached and the tile is not
     * refreshed away before the click.
     */
    $picture->deleteQuietly();

    $page->click('[data-cancel-print="'.$picture->id.'"]')
        ->assertSee("L'impression n'a pas pu être annulée")
        ->assertPresent('[data-print-pending]')
        ->assertNoJavaScriptErrors();
});
