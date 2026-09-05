<?php

use App\Models\Party;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/**
 * Seed the provider cache with an event no source would ever return, so a
 * refreshed listing is told apart from a cached one.
 */
function cacheStaleSourceEvents(): void
{
    Cache::put('party-sources.upcoming-events', [[
        'id' => 'stale:1',
        'name' => 'Stale event',
        'starts_at' => null,
        'source_key' => 'stale',
        'source_label' => 'Stale',
    ]], now()->addMinutes(10));
}

it('exposes the party domain and defers the upcoming source events', function () {
    $this->get('/admin/parties')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/parties/index')
            ->where('partyDomain', config('party.domain'))
            ->missing('upcomingSourceEvents')
            ->loadDeferredProps(fn ($reload) => $reload
                ->has('upcomingSourceEvents')
                ->has('upcomingSourceEvents.0', fn ($event) => $event
                    ->has('id')
                    ->has('name')
                    ->has('starts_at')
                    ->has('gallery_name')
                    ->has('party_name')
                    ->has('already_imported')
                    ->where('source_key', 'stub')
                    ->where('source_label', 'Local stub')
                )
            )
            ->etc()
        );
});

it('carries the real party name of the events already imported', function () {
    Party::factory()
        ->fromSourceEvent('stub:stub-domaine-des-tilleuls')
        ->create(['name' => 'mariage-domaine-des-tilleuls']);

    $this->get('/admin/parties')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps(fn ($reload) => $reload
                ->where('upcomingSourceEvents.0.id', 'stub:stub-domaine-des-tilleuls')
                ->where('upcomingSourceEvents.0.party_name', 'mariage-domaine-des-tilleuls')
                ->where('upcomingSourceEvents.1.party_name', null)
            )
            ->etc()
        );
});

it('serves the cached source events by default', function () {
    cacheStaleSourceEvents();

    $this->get('/admin/parties')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps(fn ($reload) => $reload
                ->where('upcomingSourceEvents.0.id', 'stale:1')
            )
            ->etc()
        );
});

it('bypasses the cache when the refresh flag is set', function () {
    cacheStaleSourceEvents();

    $this->get('/admin/parties?refresh=1')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->loadDeferredProps(fn ($reload) => $reload
                ->where('upcomingSourceEvents.0.source_key', 'stub')
            )
            ->etc()
        );
});

it('creates several parties at once from the selected events', function () {
    $this->post('/admin/parties/bulk', [
        'parties' => [
            ['name' => 'domaine-des-tilleuls', 'source_event_id' => 'stub:stub-domaine-des-tilleuls'],
            ['name' => 'chateau-de-la-motte', 'source_event_id' => 'stub:stub-chateau-de-la-motte'],
        ],
    ])->assertRedirect('/admin/parties');

    expect(Party::query()->orderBy('id')->pluck('name')->all())
        ->toBe(['domaine-des-tilleuls', 'chateau-de-la-motte']);
});

it('keeps track of the source event each party was created from', function () {
    $this->post('/admin/parties/bulk', [
        'parties' => [
            ['name' => 'domaine-des-tilleuls', 'source_event_id' => 'stub:stub-domaine-des-tilleuls'],
        ],
    ])->assertRedirect('/admin/parties');

    $party = Party::query()->where('name', 'domaine-des-tilleuls')->firstOrFail();

    expect($party->source_key)->toBe('stub');
    expect($party->source_event_id)->toBe('stub:stub-domaine-des-tilleuls');
});

it('leaves the source columns empty when no event is provided', function () {
    $this->post('/admin/parties/bulk', [
        'parties' => [['name' => 'manual-party']],
    ])->assertRedirect('/admin/parties');

    $party = Party::query()->where('name', 'manual-party')->firstOrFail();

    expect($party->source_key)->toBeNull();
    expect($party->source_event_id)->toBeNull();
});

it('requires at least one selected event', function () {
    $this->post('/admin/parties/bulk', ['parties' => []])
        ->assertSessionHasErrors('parties');

    expect(Party::query()->count())->toBe(0);
});

it('rejects a party name submitted twice', function () {
    $this->post('/admin/parties/bulk', [
        'parties' => [
            ['name' => 'same-name'],
            ['name' => 'same-name'],
        ],
    ])->assertSessionHasErrors('parties.1.name');

    expect(Party::query()->count())->toBe(0);
});

it('rejects a party name already taken and creates nothing', function () {
    Party::factory()->create(['name' => 'already-taken']);

    $this->post('/admin/parties/bulk', [
        'parties' => [
            ['name' => 'brand-new'],
            ['name' => 'already-taken'],
        ],
    ])->assertSessionHasErrors('parties.1.name');

    expect(Party::query()->where('name', 'brand-new')->exists())->toBeFalse();
});

it('requires a name for every selected event', function () {
    $this->post('/admin/parties/bulk', [
        'parties' => [
            ['name' => ''],
        ],
    ])->assertSessionHasErrors('parties.0.name');

    expect(Party::query()->count())->toBe(0);
});

it('hands the created party ids back to the parties list', function () {
    $response = $this->post('/admin/parties/bulk', [
        'parties' => [
            ['name' => 'premiere-soiree', 'source_event_id' => 'stub:1'],
            ['name' => 'seconde-soiree', 'source_event_id' => 'stub:2'],
        ],
    ]);

    $expected = Party::query()
        ->whereIn('name', ['premiere-soiree', 'seconde-soiree'])
        ->orderBy('id')
        ->pluck('id')
        ->all();

    $response->assertSessionHas('created_party_ids', $expected);

    $this->followingRedirects()
        ->post('/admin/parties/bulk', [
            'parties' => [['name' => 'troisieme-soiree', 'source_event_id' => 'stub:3']],
        ])
        ->assertInertia(fn ($page) => $page
            ->component('admin/parties/index')
            ->has('createdPartyIds', 1)
            ->etc()
        );
});

it('leaves the created party ids empty on a plain visit', function () {
    $this->get('/admin/parties')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('createdPartyIds', [])->etc());
});
