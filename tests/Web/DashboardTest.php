<?php

use App\Models\Party;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('can search parties in the switcher including expired ones', function () {
    $currentParty = Party::factory()->create(['name' => 'Alpha Party']);
    Party::factory()->create(['name' => 'Beta Party']);
    $expiredParty = Party::factory()->expired()->create(['name' => 'Zeta Expired']);

    $page = browserPage(fn () => visit(route('party.dashboard.show', $currentParty)));

    $page->click($currentParty->name)
        ->assertSee('Parties')
        ->assertDontSee($expiredParty->name)
        ->fill('input[aria-label="Search parties"]', 'Zeta')
        ->assertSee($expiredParty->name)
        ->assertSee('Expired')
        ->assertDontSee('Beta Party')
        ->assertNoJavaScriptErrors();
});
