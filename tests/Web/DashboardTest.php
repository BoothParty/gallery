<?php

use App\Models\Party;
use App\Models\User;

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

it('shows the party overview with stats, latest pictures and activity', function () {
    $party = Party::factory()->create([
        'name' => 'alpha-party',
        'description' => 'Domaine des Oliviers',
        'expire_at' => '2099-09-10 12:00:00',
    ]);

    $page = browserPage(fn () => visit(route('party.dashboard.show', $party)));

    $page->assertSeeIn('main > div > h1', 'alpha-party')
        ->assertSee("Domaine des Oliviers · galerie en ligne jusqu'au 10 septembre 2099")
        ->assertSee('+0 dans la dernière heure')
        ->assertSee('Aucune photo pour le moment.')
        ->assertSee('Visiteurs')
        ->assertSee('Téléchargements')
        ->assertDontSee('Messages audio')
        ->assertSee('Dernières photos')
        ->assertSee('Activité')
        ->click('Tout voir')
        ->assertPathIs('/alpha-party/dashboard/pictures')
        ->assertNoJavaScriptErrors();
});

it('links to the public gallery of the current party', function () {
    $party = Party::factory()->create(['name' => 'alpha-party']);

    $page = browserPage(fn () => visit(route('party.dashboard.show', $party)));

    $page->assertAttribute('a[target="_blank"]', 'href', route('party.gallery', $party))
        ->assertNoJavaScriptErrors();
});
