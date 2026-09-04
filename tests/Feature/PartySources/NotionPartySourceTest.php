<?php

use App\Services\PartySources\NotionPartySource;
use Carbon\CarbonImmutable;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\HttpFactory;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Log;
use Notion\Exceptions\ApiException;
use Notion\Notion;

/**
 * The SDK talks to Notion through its own PSR-18 client, so the seam for these
 * tests is the client itself rather than Laravel's HTTP fake.
 */
function notionSourceWithResponses(array $responses, array &$requests = []): NotionPartySource
{
    $handlerStack = HandlerStack::create(new MockHandler($responses));
    $handlerStack->push(Middleware::history($requests));

    $notion = Notion::createWithPsrImplementations(
        new GuzzleClient(['handler' => $handlerStack]),
        new HttpFactory,
        'secret_test',
    );

    return new NotionPartySource($notion);
}

function notionFixture(string $name): Response
{
    return new Response(200, ['Content-Type' => 'application/json'], file_get_contents(
        base_path("tests/Fixtures/notion/{$name}.json")
    ));
}

beforeEach(function () {
    config()->set('services.notion.database_id', '11111111-2222-3333-4444-555555555555');
    config()->set('services.notion.properties.name', 'Name');
    config()->set('services.notion.properties.date', 'Date');
    config()->set('services.notion.properties.status', 'Status');
    config()->set('services.notion.properties.options', 'Options');
    config()->set('services.notion.properties.gallery_name', 'Nom de la galerie');
});

it('maps the notion rows to party source events', function () {
    $source = notionSourceWithResponses([
        notionFixture('database'),
        notionFixture('query-page-2'),
    ]);

    $events = $source->events();

    expect($events)->toHaveCount(1);
    expect($events[0]->id)->toBe('aaaaaaaa-0000-0000-0000-000000000003');
    expect($events[0]->name)->toBe('Soirée Teclib');
    expect($events[0]->startsAt)->toBeNull();
});

it('reads the event date from the configured property', function () {
    Log::spy();

    $source = notionSourceWithResponses([
        notionFixture('database'),
        notionFixture('query-page-1'),
        notionFixture('query-page-2'),
    ]);

    $events = $source->events();

    expect($events[0]->name)->toBe('Mariage Domaine des Tilleuls');
    expect($events[0]->startsAt)->toBeInstanceOf(CarbonImmutable::class);
    expect($events[0]->startsAt->toIso8601String())->toBe('2026-09-12T18:00:00+02:00');
});

it('reads the gallery name from the configured property', function () {
    Log::spy();

    $source = notionSourceWithResponses([
        notionFixture('database'),
        notionFixture('query-page-1'),
        notionFixture('query-page-2'),
    ]);

    $events = $source->events();

    expect($events[0]->galleryName)->toBe('tilleuls-2026');
});

it('leaves the gallery name null when the notion column is empty', function () {
    $source = notionSourceWithResponses([
        notionFixture('database'),
        notionFixture('query-page-2'),
    ]);

    $events = $source->events();

    expect($events[0]->name)->toBe('Soirée Teclib');
    expect($events[0]->galleryName)->toBeNull();
});

it('leaves the gallery name null when the notion column is missing', function () {
    config()->set('services.notion.properties.gallery_name', 'Colonne absente');

    $source = notionSourceWithResponses([
        notionFixture('database'),
        notionFixture('query-page-2'),
    ]);

    $events = $source->events();

    expect($events[0]->galleryName)->toBeNull();
});

it('follows the pagination cursor', function () {
    $requests = [];
    $source = notionSourceWithResponses([
        notionFixture('database'),
        notionFixture('query-page-1'),
        notionFixture('query-page-2'),
    ], $requests);

    $events = $source->events();

    expect(collect($events)->pluck('name')->all())
        ->toBe(['Mariage Domaine des Tilleuls', 'Soirée Teclib']);

    expect($requests)->toHaveCount(3);

    $secondQuery = json_decode((string) $requests[2]['request']->getBody(), true);

    expect($secondQuery['start_cursor'])->toBe('cursor-page-2');
});

it('skips rows without a usable name', function () {
    Log::spy();

    $source = notionSourceWithResponses([
        notionFixture('database'),
        notionFixture('query-page-1'),
        notionFixture('query-page-2'),
    ]);

    $events = $source->events();

    expect(collect($events)->pluck('id')->all())
        ->not->toContain('aaaaaaaa-0000-0000-0000-000000000002');

    Log::shouldHaveReceived('warning')->once();
});

it('only asks notion for this week confirmed events with an online gallery, oldest first', function () {
    $requests = [];
    $source = notionSourceWithResponses([
        notionFixture('database'),
        notionFixture('query-page-2'),
    ], $requests);

    $source->events();

    $query = json_decode((string) $requests[1]['request']->getBody(), true);

    expect($query['filter'])->toBe([
        'and' => [
            ['property' => 'Status', 'status' => ['equals' => 'Confirmé']],
            ['property' => 'Options', 'multi_select' => ['contains' => 'Galerie en ligne']],
            ['property' => 'Date', 'date' => ['this_week' => []]],
        ],
    ]);
    expect($query['sorts'])->toBe([
        ['property' => 'Date', 'direction' => 'ascending'],
    ]);
});

it('lets notion api errors bubble up', function () {
    $source = notionSourceWithResponses([
        new Response(401, ['Content-Type' => 'application/json'], json_encode([
            'object' => 'error',
            'status' => 401,
            'code' => 'unauthorized',
            'message' => 'API token is invalid.',
        ])),
    ]);

    expect(fn () => $source->events())->toThrow(ApiException::class);
});

it('requires a configured database id', function () {
    config()->set('services.notion.database_id', null);

    $source = notionSourceWithResponses([]);

    expect(fn () => $source->events())
        ->toThrow(RuntimeException::class, 'NOTION_DATABASE_ID');
});

it('stays out of the enabled sources while no token is configured', function () {
    expect(config('party.sources'))
        ->toHaveKey('stub')
        ->not->toHaveKey('notion');
});
