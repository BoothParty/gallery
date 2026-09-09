<?php

use App\Models\Party;
use App\Services\UmamiStats;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    config()->set('services.umami', [
        'website_id' => 'the-website-id',
        'url' => 'https://umami.test',
        'username' => 'reader',
        'password' => 'secret',
        'timeout' => 5,
        'cache_ttl' => 300,
    ]);
});

/**
 * Answers each Umami endpoint the panel reads, the metrics ones being told
 * apart by their `type` rather than by their path.
 */
function fakeUmami(): void
{
    Http::fake(function (Request $request) {
        return match (true) {
            str_contains($request->url(), '/auth/login') => Http::response(['token' => 'a-token']),
            str_contains($request->url(), '/stats') => Http::response([
                'visitors' => ['value' => 42],
                'visits' => ['value' => 50],
                'pageviews' => ['value' => 130],
                'totaltime' => ['value' => 4000],
            ]),
            str_contains($request->url(), '/pageviews') => Http::response([
                'pageviews' => [['x' => '2026-09-09 21:00:00', 'y' => 90]],
                'sessions' => [['x' => '2026-09-09 21:00:00', 'y' => 30], ['x' => '2026-09-09 22:00:00', 'y' => 12]],
            ]),
            str_contains($request->url(), 'type=event') => Http::response([
                ['x' => 'photo_open', 'y' => 88],
                ['x' => 'download', 'y' => 7],
            ]),
            str_contains($request->url(), 'type=referrer') => Http::response([
                ['x' => '', 'y' => 30],
                ['x' => 'whatsapp.com', 'y' => 12],
                ['x' => 'a.test', 'y' => 5],
                ['x' => 'b.test', 'y' => 4],
                ['x' => 'c.test', 'y' => 3],
                ['x' => 'd.test', 'y' => 2],
            ]),
            default => Http::response([['x' => 'mobile', 'y' => 45], ['x' => 'desktop', 'y' => 5]]),
        };
    });
}

it('shapes the audience of one gallery', function () {
    fakeUmami();

    $party = Party::factory()->create(['name' => 'la-soiree']);

    expect(app(UmamiStats::class)->for($party))
        ->toMatchArray([
            'hostname' => 'la-soiree.'.config('party.domain'),
            'dashboard_url' => 'https://umami.test/websites/the-website-id',
            'unit' => 'hour',
            'visitors' => 42,
            'visits' => 50,
            'pageviews' => 130,
            // 4000 seconds over 50 visits.
            'average_seconds' => 80,
            'events' => ['photo_open' => 88, 'download' => 7],
            'devices' => [
                ['label' => 'mobile', 'value' => 45],
                ['label' => 'desktop', 'value' => 5],
            ],
            'series' => [
                ['label' => '2026-09-09 21:00:00', 'value' => 30],
                ['label' => '2026-09-09 22:00:00', 'value' => 12],
            ],
        ]);
});

it('names an empty referrer and keeps only the top rows', function () {
    fakeUmami();

    $referrers = app(UmamiStats::class)->for(Party::factory()->create())['referrers'];

    expect($referrers)->toHaveCount(5)
        ->and($referrers[0])->toBe(['label' => 'Direct', 'value' => 30])
        ->and($referrers[1])->toBe(['label' => 'whatsapp.com', 'value' => 12]);
});

it('asks Umami for this gallery only, over the life of the party', function () {
    fakeUmami();

    config()->set('party.domain', 'example.test');

    $party = Party::factory()->create(['name' => 'la-soiree', 'expire_at' => now()->subDay()]);

    app(UmamiStats::class)->for($party);

    Http::assertSent(function (Request $request) use ($party) {
        if (! str_contains($request->url(), '/stats')) {
            return false;
        }

        return $request['hostname'] === 'la-soiree.example.test'
            && (int) $request['startAt'] === $party->created_at->getTimestampMs()
            && (int) $request['endAt'] === $party->expire_at->getTimestampMs();
    });
});

it('reads the hourly curve for one evening and the daily one beyond', function () {
    fakeUmami();

    app(UmamiStats::class)->for(Party::factory()->create());
    Http::assertSent(fn (Request $request) => ! str_contains($request->url(), '/pageviews') || $request['unit'] === 'hour');

    Http::fake();
    fakeUmami();

    app(UmamiStats::class)->for(Party::factory()->create(['created_at' => now()->subWeek()]));
    Http::assertSent(fn (Request $request) => ! str_contains($request->url(), '/pageviews') || $request['unit'] === 'day');
});

it('serves the same figures from the cache rather than asking again', function () {
    fakeUmami();

    $party = Party::factory()->create();
    $service = app(UmamiStats::class);

    $service->for($party);
    $sent = count(Http::recorded());
    $service->for($party);

    expect(Http::recorded())->toHaveCount($sent);
});

it('reports nothing while the api is unconfigured', function () {
    Http::fake();
    config()->set('services.umami.url', null);

    expect(app(UmamiStats::class)->isConfigured())->toBeFalse()
        ->and(app(UmamiStats::class)->for(Party::factory()->create()))->toBeNull();

    Http::assertNothingSent();
});

it('reports nothing when umami answers an error', function () {
    Http::fake([
        '*/auth/login' => Http::response(['token' => 'a-token']),
        '*' => Http::response(status: 503),
    ]);

    expect(app(UmamiStats::class)->for(Party::factory()->create()))->toBeNull();
});
