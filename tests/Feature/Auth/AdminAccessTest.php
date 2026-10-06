<?php

use App\Models\Party;
use App\Models\Picture;
use App\Models\User;

it('redirects a guest away from the administration', function (string $url) {
    $this->get($url)->assertRedirect(route('login'));
})->with([
    '/admin/parties',
    '/admin/users',
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

it('blocks a guest from uploading pictures and creates nothing', function () {
    $party = Party::factory()->create();

    $this->post(route('party.dashboard.pictures.store', $party), [
        'photos' => [],
    ])->assertRedirect(route('login'));

    expect(Picture::query()->count())->toBe(0);
});

it('blocks a guest from bulk deleting pictures and deletes nothing', function () {
    $party = Party::factory()->create();
    Picture::factory()->for($party)->count(3)->create();

    $this->delete(route('party.dashboard.pictures.bulk-delete', $party))
        ->assertRedirect(route('login'));

    expect(Picture::query()->count())->toBe(3);
});

it('blocks a guest from bulk creating parties', function () {
    $this->post(route('admin.parties.bulk-store'), [
        'parties' => [],
    ])->assertRedirect(route('login'));
});

it('blocks a guest from deleting a user and deletes nothing', function () {
    $user = User::factory()->create();

    $this->delete(route('admin.users.destroy', $user))
        ->assertRedirect(route('login'));

    expect(User::query()->count())->toBe(1);
});

it('blocks a guest from generating a party poster', function () {
    $party = Party::factory()->create();

    $this->get(route('admin.parties.poster', $party))
        ->assertRedirect(route('login'));
});
