<?php

use App\Models\Party;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

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
                    ->where('source_key', 'notion')
                    ->where('source_label', 'Notion')
                )
            )
            ->etc()
        );
});

it('creates several parties at once from the selected events', function () {
    $this->post('/admin/parties/bulk', [
        'parties' => [
            ['name' => 'domaine-des-tilleuls', 'source_event_id' => 'notion:stub-domaine-des-tilleuls'],
            ['name' => 'chateau-de-la-motte', 'source_event_id' => 'notion:stub-chateau-de-la-motte'],
        ],
    ])->assertRedirect('/admin/parties');

    expect(Party::query()->pluck('name')->all())
        ->toBe(['domaine-des-tilleuls', 'chateau-de-la-motte']);
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
