<?php

use App\Models\Party;
use App\Models\User;

it('shares the authenticated user with every inertia page', function () {
    $user = User::factory()->create(['name' => 'Alice', 'email' => 'alice@boothparty.fr']);

    $this->actingAs($user)
        ->get('/admin/parties')
        ->assertInertia(fn ($page) => $page
            ->where('auth.user.id', $user->id)
            ->where('auth.user.name', 'Alice')
            ->where('auth.user.email', 'alice@boothparty.fr')
        );
});

it('shares a null user with guests', function () {
    $party = Party::factory()->create();

    $this->get(route('party.gallery', $party))
        ->assertInertia(fn ($page) => $page->where('auth.user', null));
});
