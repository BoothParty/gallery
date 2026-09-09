<?php

use App\Models\Party;
use App\Models\User;

beforeEach(function () {
    config()->set('services.umami.script_url', 'https://umami.test/script.js');
    config()->set('services.umami.website_id', 'the-website-id');
});

it('loads the tracker on a guest visit', function () {
    $party = Party::factory()->create();

    $this->get(route('party.gallery', $party))
        ->assertOk()
        ->assertSee('https://umami.test/script.js', escape: false)
        ->assertSee('data-website-id="the-website-id"', escape: false);
});

it('keeps the tracker off the host own gallery', function () {
    $party = Party::factory()->create();

    $this->withSession(['party_host.'.$party->id => true])
        ->get(route('party.gallery', $party))
        ->assertOk()
        ->assertDontSee('umami.test');
});

it('keeps the tracker off the dashboard', function () {
    $party = Party::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('party.dashboard.show', $party))
        ->assertOk()
        ->assertDontSee('umami.test');
});

it('loads nothing while umami is unconfigured', function () {
    config()->set('services.umami.website_id', null);

    $party = Party::factory()->create();

    $this->get(route('party.gallery', $party))
        ->assertOk()
        ->assertDontSee('umami.test');
});

it('marks the poster url so a scan is told apart from a shared link', function () {
    config()->set('party.domain', 'example.test');
    config()->set('app.url', 'https://example.test');

    expect(Party::factory()->create(['name' => 'scanned'])->posterUrl())
        ->toBe('https://scanned.example.test/?s=poster');
});
