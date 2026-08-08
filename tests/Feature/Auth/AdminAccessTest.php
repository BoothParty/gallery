<?php

use App\Models\Party;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('redirects a guest away from the administration', function (string $url) {
    $this->get($url)->assertRedirect(route('login'));
})->with([
    '/admin/parties',
    '/admin/users',
    '/admin/users/create',
]);

it('redirects a guest away from the party dashboard', function () {
    $party = Party::factory()->create();

    $this->get(route('party.dashboard.show', $party))
        ->assertRedirect(route('login'));

    $this->get(route('party.dashboard.pictures', $party))
        ->assertRedirect(route('login'));
});

it('keeps the public party gallery open to guests', function () {
    $party = Party::factory()->create();

    $this->get(route('party.gallery', $party))->assertOk();
});

it('lets an authenticated user reach the administration', function () {
    $this->actingAs(User::factory()->create())
        ->get('/admin/parties')
        ->assertOk();
});
