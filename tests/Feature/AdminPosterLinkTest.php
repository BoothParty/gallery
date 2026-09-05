<?php

use App\Models\Party;
use App\Models\User;
use App\Services\PosterBatchLink;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('mints a signed page link with its qr code', function () {
    $this->actingAs(User::factory()->create());

    $party = Party::factory()->create();

    $response = $this->getJson(route('admin.parties.posters-link', ['parties' => (string) $party->id]));

    $response->assertOk()
        ->assertJsonStructure(['url', 'qr']);

    expect($response->json('qr'))->toStartWith('data:image/png;base64,');

    $this->get($response->json('url'))->assertOk();
});

it('carries every requested party in the link', function () {
    $this->actingAs(User::factory()->create());

    $first = Party::factory()->create();
    $second = Party::factory()->create();

    $url = app(PosterBatchLink::class)->forParties([$first->id, $second->id])['url'];

    $this->get($url)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('party/posters')->has('posters', 2));
});

it('rejects an empty selection', function () {
    $this->actingAs(User::factory()->create());

    $this->getJson(route('admin.parties.posters-link', ['parties' => '']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('parties');
});

it('rejects a selection of unknown parties', function () {
    $this->actingAs(User::factory()->create());

    $this->getJson(route('admin.parties.posters-link', ['parties' => '404,405']))
        ->assertStatus(422)
        ->assertJsonValidationErrors('parties');
});

it('keeps the link behind the admin session', function () {
    $party = Party::factory()->create();

    $this->getJson(route('admin.parties.posters-link', ['parties' => (string) $party->id]))
        ->assertUnauthorized();
});
