<?php

use App\Models\Party;
use App\Services\PartySources\PartySource;
use App\Services\PartySources\PartySourceEvent;
use App\Services\PartySources\PartySourceManager;
use App\Services\PartySources\StubPartySource;
use Carbon\CarbonImmutable;

it('resolves the sources enabled in the configuration', function () {
    $sources = app(PartySourceManager::class)->sources();

    expect($sources)->toHaveCount(1);
    expect($sources[0])->toBeInstanceOf(StubPartySource::class);
});

it('rejects a source that does not implement the contract', function () {
    config()->set('party.sources', ['broken' => stdClass::class]);

    expect(fn () => app(PartySourceManager::class)->sources())
        ->toThrow(InvalidArgumentException::class, 'The [broken] party source must implement');
});

it('drops past events and orders the upcoming ones by date', function () {
    config()->set('party.sources', ['fake' => FakePartySource::class]);

    $events = app(PartySourceManager::class)->upcomingEvents();

    expect(collect($events)->pluck('name')->all())->toBe(['Soon', 'Later', 'Undated']);
    expect($events[0]['id'])->toBe('fake:soon');
    expect($events[0]['source_label'])->toBe('Fake');
    expect($events[2]['starts_at'])->toBeNull();
});

it('degrades to the reachable sources when one is down', function () {
    config()->set('party.sources', ['broken' => BrokenPartySource::class, 'fake' => FakePartySource::class]);

    $events = app(PartySourceManager::class)->upcomingEvents();

    expect(collect($events)->pluck('name')->all())->toBe(['Soon', 'Later', 'Undated']);
});

it('exposes the stub events to the administration', function () {
    $events = app(PartySourceManager::class)->upcomingEvents();

    expect($events)->not->toBeEmpty();

    foreach ($events as $event) {
        expect($event['source_key'])->toBe('stub');
        expect($event['name'])->toBeString()->not->toBeEmpty();
    }
});

it('queries the sources once and serves the cache afterwards', function () {
    config()->set('party.sources', ['counting' => CountingPartySource::class]);
    CountingPartySource::$calls = 0;

    $manager = app(PartySourceManager::class);

    $manager->upcomingEvents();
    $manager->upcomingEvents();

    expect(CountingPartySource::$calls)->toBe(1);
});

it('queries the sources again when asked for fresh events', function () {
    config()->set('party.sources', ['counting' => CountingPartySource::class]);
    CountingPartySource::$calls = 0;

    $manager = app(PartySourceManager::class);

    $manager->upcomingEvents();
    $manager->upcomingEvents(fresh: true);

    expect(CountingPartySource::$calls)->toBe(2);
});

it('flags the events already imported as a party', function () {
    config()->set('party.sources', ['fake' => FakePartySource::class]);
    Party::factory()->fromSourceEvent('fake:soon', 'fake')->create();

    $events = collect(app(PartySourceManager::class)->upcomingEvents())->keyBy('id');

    expect($events['fake:soon']['already_imported'])->toBeTrue();
    expect($events['fake:later']['already_imported'])->toBeFalse();
});

it('flags a freshly imported event even though the events are cached', function () {
    config()->set('party.sources', ['fake' => FakePartySource::class]);
    $manager = app(PartySourceManager::class);

    $manager->upcomingEvents();
    Party::factory()->fromSourceEvent('fake:soon', 'fake')->create();

    $events = collect($manager->upcomingEvents())->keyBy('id');

    expect($events['fake:soon']['already_imported'])->toBeTrue();
});

class FakePartySource implements PartySource
{
    public function key(): string
    {
        return 'fake';
    }

    public function label(): string
    {
        return 'Fake';
    }

    public function events(): array
    {
        return [
            new PartySourceEvent(id: 'undated', name: 'Undated'),
            new PartySourceEvent(id: 'later', name: 'Later', startsAt: CarbonImmutable::now()->addMonth()),
            new PartySourceEvent(id: 'past', name: 'Past', startsAt: CarbonImmutable::now()->subDay()),
            new PartySourceEvent(id: 'soon', name: 'Soon', startsAt: CarbonImmutable::now()->addDay()),
        ];
    }
}

class BrokenPartySource implements PartySource
{
    public function key(): string
    {
        return 'broken';
    }

    public function label(): string
    {
        return 'Broken';
    }

    public function events(): array
    {
        throw new RuntimeException('the source is down');
    }
}

class CountingPartySource implements PartySource
{
    public static int $calls = 0;

    public function key(): string
    {
        return 'counting';
    }

    public function label(): string
    {
        return 'Counting';
    }

    public function events(): array
    {
        self::$calls++;

        return [
            new PartySourceEvent(id: 'only', name: 'Only', startsAt: CarbonImmutable::now()->addWeek()),
        ];
    }
}
