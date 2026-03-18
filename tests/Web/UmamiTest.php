<?php

use App\Models\Party;
use App\Models\Picture;
use App\Services\UmamiClient;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    if (! config('umami.base_url') || ! config('umami.website_id')) {
        test()->skip('Umami integration tests require UMAMI_BASE_URL and UMAMI_WEBSITE_ID to be set.');
    }

    Storage::fake('s3');
});

// ── Helper ────────────────────────────────────────────────────────────────────

/**
 * Poll the Umami API until the given event name appears or the timeout is reached.
 *
 * @return array<int, string>
 */
function pollForEvent(string $eventName, Carbon $startAt, int $timeoutSeconds = 10): array
{
    $client = app(UmamiClient::class)->login();
    $deadline = now()->addSeconds($timeoutSeconds);

    do {
        $names = $client->getEventNames(config('umami.website_id'), $startAt);
        if (in_array($eventName, $names, true)) {
            return $names;
        }
        sleep(1);
    } while (now()->isBefore($deadline));

    return $names ?? [];
}

// ── Pagination events ─────────────────────────────────────────────────────────

it('records the gallery_page_next event in Umami when navigating to the next page', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(31)->for($party)->create();

    $startAt = Carbon::now()->subSecond();

    visit("/parties/{$party->id}")
        ->assertSee('1 / 2 pages')
        ->click('Page suivante')
        ->assertSee('2 / 2 pages')
        ->assertNoJavaScriptErrors();

    $eventNames = pollForEvent('gallery_page_next', $startAt);

    expect($eventNames)->toContain('gallery_page_next');
});

it('records the gallery_page_prev event in Umami when navigating to the previous page', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(31)->for($party)->create();

    $startAt = Carbon::now()->subSecond();

    visit("/parties/{$party->id}?page=2")
        ->assertSee('2 / 2 pages')
        ->click('Page précédente')
        ->assertSee('1 / 2 pages')
        ->assertNoJavaScriptErrors();

    $eventNames = pollForEvent('gallery_page_prev', $startAt);

    expect($eventNames)->toContain('gallery_page_prev');
});

// ── Lightbox event ────────────────────────────────────────────────────────────

it('records the gallery_image_open event in Umami when opening the lightbox', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(3)->for($party)->create();

    $startAt = Carbon::now()->subSecond();

    visit("/parties/{$party->id}")
        ->assertCount('#gallery a', 3)
        ->click('#gallery a:first-of-type')
        ->assertNoJavaScriptErrors();

    $eventNames = pollForEvent('gallery_image_open', $startAt);

    expect($eventNames)->toContain('gallery_image_open');
});
