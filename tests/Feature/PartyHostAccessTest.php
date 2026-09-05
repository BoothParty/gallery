<?php

use App\Models\Party;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
});

// ── Opening a host session ──────────────────────────────────────────────────

it('opens a host session and strips the token from the url', function () {
    $party = Party::factory()->create();

    $this->get(route('party.gallery', $party).'?token='.$party->host_token)
        ->assertRedirect(route('party.gallery', $party))
        ->assertSessionHas('party_host.'.$party->id, true);
});

it('redirects the same way for a wrong token, granting nothing', function () {
    $party = Party::factory()->create();

    $this->get(route('party.gallery', $party).'?token=not-the-right-token')
        ->assertRedirect(route('party.gallery', $party))
        ->assertSessionMissing('party_host.'.$party->id);
});

it('leaves a plain visit untouched', function () {
    $party = Party::factory()->create();

    $this->get(route('party.gallery', $party))
        ->assertOk()
        ->assertSessionMissing('party_host.'.$party->id);
});

// ── The canEdit prop ────────────────────────────────────────────────────────

it('reports canEdit false for a plain visitor', function () {
    $party = Party::factory()->create();

    $this->get(route('party.gallery', $party))
        ->assertInertia(fn ($page) => $page->where('canEdit', false));
});

it('reports canEdit true once the host session is open', function () {
    $party = Party::factory()->create();

    $this->withSession(['party_host' => [$party->id => true]])
        ->get(route('party.gallery', $party))
        ->assertInertia(fn ($page) => $page->where('canEdit', true));
});

it('keeps the host editing an expired gallery', function () {
    $party = Party::factory()->expired()->create();

    $this->withSession(['party_host' => [$party->id => true]])
        ->get(route('party.gallery', $party))
        ->assertInertia(fn ($page) => $page
            ->where('hidePictures', true)
            ->where('canEdit', true)
        );
});

it('does not let the session of one party edit another', function () {
    $mine = Party::factory()->create();
    $other = Party::factory()->create();

    $this->withSession(['party_host' => [$mine->id => true]])
        ->get(route('party.gallery', $other))
        ->assertInertia(fn ($page) => $page->where('canEdit', false));
});
