<?php

use App\Models\Party;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('lists transfers with their link', function () {
    Party::factory()->transfer()->create(['display_name' => 'Mariage Julie']);

    $page = browserPage(fn () => visit(route('admin.transfers.index')));

    $page->assertSee('Mariage Julie')
        ->assertSee('Copy link')
        ->assertNoJavaScriptErrors();
});

it('offers a folder picker in the new transfer dialog', function () {
    $page = browserPage(fn () => visit(route('admin.transfers.index')));

    $page->click('New transfer')
        ->assertVisible('#transfer-name')
        ->assertAttribute('#transfer-expire-at', 'type', 'datetime-local')
        ->assertPresent('#transfer-folder[webkitdirectory]')
        ->assertSee('Drop the photobooth folder here')
        ->assertNoJavaScriptErrors();
});

it('reaches the transfers screen from the sidebar', function () {
    $page = browserPage(fn () => visit(route('admin.parties.index')));

    $page->click('Transfers')
        ->assertSeeIn('main > div > h1', 'Transfers')
        ->assertNoJavaScriptErrors();
});
