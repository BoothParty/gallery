<?php

use App\Models\Party;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders the party dashboard page with sidebar data', function () {
    $currentParty = Party::factory()->create(['name' => 'Alpha']);

    $this->get(route('party.dashboard.show', $currentParty))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('dashboard')
            ->where('currentPartyId', $currentParty->id)
            ->has('parties', 1)
            ->where('parties.0.id', $currentParty->id)
            ->where('parties.0.name', 'Alpha')
            ->where('parties.0.expired', false)
            ->has('searchableParties', 1)
            ->where('searchableParties.0.id', $currentParty->id)
            ->where('searchableParties.0.name', 'Alpha')
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
