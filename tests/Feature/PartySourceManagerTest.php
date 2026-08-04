<?php

use App\Services\PartySources\NotionPartySource;
use App\Services\PartySources\PartySource;
use App\Services\PartySources\PartySourceEvent;
use App\Services\PartySources\PartySourceManager;
use Carbon\CarbonImmutable;

it('resolves the sources enabled in the configuration', function () {
    $sources = app(PartySourceManager::class)->sources();

    expect($sources)->toHaveCount(1);
    expect($sources[0])->toBeInstanceOf(NotionPartySource::class);
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

it('exposes the notion stub events to the administration', function () {
    $events = app(PartySourceManager::class)->upcomingEvents();

    expect($events)->not->toBeEmpty();

    foreach ($events as $event) {
        expect($event['source_key'])->toBe('notion');
        expect($event['name'])->toBeString()->not->toBeEmpty();
    }
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
