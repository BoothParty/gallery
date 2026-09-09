<?php

use App\Models\Party;
use App\Models\User;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('renders the party dashboard page with sidebar data', function () {
    $currentParty = Party::factory()->create(['name' => 'alpha']);

    $this->get(route('party.dashboard.show', $currentParty))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('currentPartyId', $currentParty->id)
            ->has('parties', 1)
            ->where('parties.0.id', $currentParty->id)
            ->where('parties.0.name', 'alpha')
            ->where('parties.0.expired', false)
            ->has('searchableParties', 1)
            ->where('searchableParties.0.id', $currentParty->id)
            ->where('searchableParties.0.name', 'alpha')
            ->where('searchableParties.0.expired', false)
        );
});

it('limits dashboard parties to 10', function () {
    $currentParty = Party::factory()->create();
    Party::factory()->count(12)->create();

    $this->get(route('party.dashboard.show', $currentParty))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('parties', 10)
        );
});

it('excludes expired parties from dashboard parties', function () {
    $currentParty = Party::factory()->create();
    $expiredParty = Party::factory()->expired()->create();
    Party::factory()->create();

    $this->get(route('party.dashboard.show', $currentParty))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->has('parties', 2)
            ->where('parties', fn ($parties) => collect($parties)
                ->pluck('id')
                ->doesntContain($expiredParty->id)
            )
            ->where('searchableParties', fn ($parties) => collect($parties)
                ->contains(fn (array $party) => $party['id'] === $expiredParty->id && $party['expired'] === true)
            )
        );
});

it('defers the party audience and says whether umami can answer', function () {
    Http::fake([
        '*/auth/login' => Http::response(['token' => 'a-token']),
        '*' => Http::response([]),
    ]);

    config()->set('services.umami', [
        'website_id' => 'the-website-id',
        'url' => 'https://umami.test',
        'username' => 'reader',
        'password' => 'secret',
        'timeout' => 5,
        'cache_ttl' => 300,
    ]);

    $party = Party::factory()->create(['display_name' => 'Camille & Antoine']);

    $this->get(route('party.dashboard.show', $party))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('partyName', 'Camille & Antoine')
            ->where('analyticsEnabled', true)
            ->missing('analytics')
            ->loadDeferredProps(fn ($reload) => $reload->has('analytics'))
            ->etc()
        );
});

it('reports the audience as unavailable while umami is unconfigured', function () {
    config()->set('services.umami.url', null);

    $this->get(route('party.dashboard.show', Party::factory()->create()))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('analyticsEnabled', false)->etc());
});
