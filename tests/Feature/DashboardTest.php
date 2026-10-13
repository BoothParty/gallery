<?php

use App\Enums\PartyActivityType;
use App\Models\Party;
use App\Models\PartyActivity;
use App\Models\Picture;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

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

it('exposes the party description and expiry date', function () {
    $party = Party::factory()->create([
        'description' => 'Domaine des Oliviers',
        'expire_at' => '2026-12-31 23:00:00',
    ]);

    $this->get(route('party.dashboard.show', $party))
        ->assertInertia(fn ($page) => $page
            ->where('party.description', 'Domaine des Oliviers')
            ->where('party.expire_at', $party->expire_at->toIso8601String())
        );
});

it('counts all pictures and those added within the last hour', function () {
    $this->freezeTime();
    $party = Party::factory()->create();
    Picture::factory()->for($party)->count(3)->create(['created_at' => now()->subHours(2)]);
    Picture::factory()->for($party)->count(2)->create(['created_at' => now()->subMinutes(30)]);
    Picture::factory()->for($party)->create(['created_at' => now()->subMinutes(10), 'hidden_at' => now()]);
    Picture::factory()->create();

    $this->get(route('party.dashboard.show', $party))
        ->assertInertia(fn ($page) => $page
            ->where('pictureStats', ['total' => 6, 'lastHour' => 3])
        );
});

it('lists the 24 latest pictures, newest first', function () {
    $party = Party::factory()->create();
    Picture::factory()->for($party)->count(25)->sequence(
        fn ($sequence) => ['created_at' => now()->subMinutes(25 - $sequence->index)],
    )->create();
    $newest = $party->pictures()->latest()->first();

    $this->get(route('party.dashboard.show', $party))
        ->assertInertia(fn ($page) => $page
            ->has('latestPictures', 24)
            ->where('latestPictures.0.id', $newest->id)
        );
});

it('lists the 5 latest feed activities, newest first and without downloads', function () {
    $this->freezeTime();
    $party = Party::factory()->create();
    PartyActivity::factory()->for($party)->count(6)->sequence(
        fn ($sequence) => ['updated_at' => now()->subHours(6 - $sequence->index)],
    )->create(['type' => PartyActivityType::PicturesHidden, 'properties' => ['count' => 3]]);
    PartyActivity::factory()->for($party)->create(['type' => PartyActivityType::PicturesDownloaded]);
    PartyActivity::factory()->create();

    $this->get(route('party.dashboard.show', $party))
        ->assertInertia(fn ($page) => $page
            ->has('activity', 5)
            ->where('activity.0', [
                'description' => '3 photos masquées',
                'at' => now()->subHour()->toIso8601String(),
            ])
        );
});

it('counts the downloads and the pictures they carried', function () {
    $party = Party::factory()->create();
    $downloaded = ['type' => PartyActivityType::PicturesDownloaded];
    PartyActivity::factory()->for($party)->create([...$downloaded, 'properties' => ['count' => 1]]);
    PartyActivity::factory()->for($party)->create([...$downloaded, 'properties' => ['count' => 12]]);
    PartyActivity::factory()->for($party)->create(['type' => PartyActivityType::PicturesImported, 'properties' => ['count' => 30]]);
    PartyActivity::factory()->create([...$downloaded, 'properties' => ['count' => 5]]);

    $this->get(route('party.dashboard.show', $party))
        ->assertInertia(fn ($page) => $page
            ->where('downloadStats', ['total' => 2, 'pictures' => 13])
        );
});
