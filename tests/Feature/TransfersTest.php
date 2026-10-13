<?php

use App\Enums\PartyKind;
use App\Models\Party;
use App\Models\User;
use Illuminate\Support\Str;

it('creates events by default', function () {
    $party = Party::factory()->create();

    expect($party->refresh()->kind)->toBe(PartyKind::Event)
        ->and($party->isTransfer())->toBeFalse();
});

it('keeps the subdomain url for an event', function () {
    $party = Party::factory()->create(['name' => 'mariage-julie']);

    expect($party->publicUrl())->toBe('https://mariage-julie.'.config('party.domain'));
});

it('builds transfers named by a uuid', function () {
    $transfer = Party::factory()->transfer()->create();

    expect($transfer->isTransfer())->toBeTrue()
        ->and(Str::isUuid($transfer->name))->toBeTrue();
});

it('lists only events on the parties screen', function () {
    $this->actingAs(User::factory()->create());
    $event = Party::factory()->create();
    Party::factory()->transfer()->create();

    $this->get(route('admin.parties.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('rows', 1)
            ->where('rows.0.id', $event->id)
        );
});

it('keeps transfers out of the sidebar party switcher', function () {
    $this->actingAs(User::factory()->create());
    $event = Party::factory()->create();
    Party::factory()->transfer()->create();

    $this->get(route('admin.users.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('searchableParties', fn ($parties) => collect($parties)->pluck('id')->all() === [$event->id])
        );
});

it('serves a transfer gallery under /transfers', function () {
    $transfer = Party::factory()->transfer()->create();

    $this->get('/transfers/'.$transfer->name)
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('party/gallery')
            ->where('party.id', $transfer->id)
        );
});

it('does not serve an event under /transfers', function () {
    Party::factory()->create(['name' => 'mariage-julie']);

    $this->get('/transfers/mariage-julie')->assertNotFound();
});

it('points the public url of a transfer at /transfers', function () {
    $transfer = Party::factory()->transfer()->create();

    expect($transfer->publicUrl())->toBe(url('/transfers/'.$transfer->name));
});
