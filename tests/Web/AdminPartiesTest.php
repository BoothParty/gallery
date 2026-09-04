<?php

use App\Models\Party;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

/*
 * The expiry input is a datetime-local field, so its value is the browser's
 * local time while the database stores an absolute instant. These tests assert
 * round-trips rather than literal instants, so they hold in any timezone.
 */

function openFirstPartyDetails(object $page): object
{
    return $page->click('table tbody tr:first-child td:nth-child(2)');
}

// ── Create dialog ─────────────────────────────────────────────────────────────

it('offers an expiry date field in the create dialog', function () {
    $page = visit(route('admin.parties.index'));

    $page->click('Create party')
        ->assertVisible('#create-party-expire-at')
        ->assertAttribute('#create-party-expire-at', 'type', 'datetime-local')
        ->assertValue('#create-party-expire-at', '')
        ->assertNoJavaScriptErrors();
});

it('round-trips an expiry date entered in the create dialog', function () {
    $page = visit(route('admin.parties.index'));

    $page->click('Create party')
        ->fill('#create-party-name', 'Browser Party')
        ->fill('#create-party-expire-at', '2030-06-15T18:00')
        ->click('Save party')
        ->assertNoJavaScriptErrors();

    expect(Party::query()->where('name', 'Browser Party')->value('expire_at'))->not->toBeNull();

    openFirstPartyDetails($page->navigate(route('admin.parties.index')))
        ->assertValue('#edit-party-expire-at', '2030-06-15T18:00')
        ->assertNoJavaScriptErrors();
});

// ── Bulk create dialog ────────────────────────────────────────────────────────

it('prefills party names from the upcoming events in the bulk create dialog', function () {
    $page = visit(route('admin.parties.index'));

    $page->click('Create from events')
        ->assertSee('Create parties from upcoming events')
        ->assertVisible('#bulk-create-party-name-0')
        ->assertSee('.'.config('party.domain'))
        ->assertNoJavaScriptErrors();

    expect($page->value('#bulk-create-party-name-0'))->toMatch('/^[a-z0-9-]+$/');
});

it('prefills the party name from the gallery name of the source event', function () {
    $page = visit(route('admin.parties.index'));

    $page->click('Create from events')
        ->assertVisible('#bulk-create-party-name-0')
        ->assertNoJavaScriptErrors();

    expect($page->value('#bulk-create-party-name-0'))->toBe('domaine-des-tilleuls-2026');
});

it('creates the selected parties from the bulk create dialog', function () {
    $page = visit(route('admin.parties.index'));

    $page->click('Create from events')
        ->assertVisible('#bulk-create-party-name-0')
        ->click('#bulk-create-select-0')
        ->fill('#bulk-create-party-name-0', 'browser-bulk-party')
        ->click('Create 1 party')
        ->assertNoJavaScriptErrors();

    expect(Party::query()->where('name', 'browser-bulk-party')->exists())->toBeTrue();
});

it('reloads the upcoming events from the bulk create dialog', function () {
    $page = visit(route('admin.parties.index'));

    $page->click('Create from events')
        ->assertVisible('#bulk-create-party-name-0')
        ->click('#bulk-create-refresh')
        ->assertVisible('#bulk-create-party-name-0')
        ->assertNoJavaScriptErrors();
});

it('marks the events already turned into a party', function () {
    Party::factory()
        ->fromSourceEvent('stub:stub-domaine-des-tilleuls')
        ->create(['name' => 'mariage-domaine-des-tilleuls']);

    $page = visit(route('admin.parties.index'));

    $page->click('Create from events')
        ->assertSee('Already created')
        ->assertNoJavaScriptErrors();

    expect($page->attribute('#bulk-create-party-name-0', 'disabled'))->not->toBeNull();
    expect($page->value('#bulk-create-party-name-0'))->toBe('mariage-domaine-des-tilleuls');
});

// ── Edit dialog ───────────────────────────────────────────────────────────────

it('prefills the expiry date in the edit dialog', function () {
    Party::factory()->create([
        'name' => 'Timed Party',
        'expire_at' => '2030-06-15 18:00:00',
    ]);

    $page = visit(route('admin.parties.index'));

    openFirstPartyDetails($page)
        ->assertVisible('#edit-party-expire-at')
        ->assertAttribute('#edit-party-expire-at', 'type', 'datetime-local')
        ->assertNoJavaScriptErrors();

    expect($page->value('#edit-party-expire-at'))->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/');
});

it('leaves the expiry date untouched when the dialog is saved unchanged', function () {
    $party = Party::factory()->create([
        'name' => 'Stable Party',
        'expire_at' => '2030-06-15 18:00:00',
    ]);

    $page = visit(route('admin.parties.index'));

    openFirstPartyDetails($page)
        ->click('Update party')
        ->assertNoJavaScriptErrors();

    expect($party->refresh()->expire_at?->toDateTimeString())->toBe('2030-06-15 18:00:00');
});

it('clears the expiry date from the edit dialog', function () {
    $party = Party::factory()->create([
        'name' => 'Clearable Party',
        'expire_at' => '2030-06-15 18:00:00',
    ]);

    $page = visit(route('admin.parties.index'));

    openFirstPartyDetails($page)
        ->clear('#edit-party-expire-at')
        ->click('Update party')
        ->assertNoJavaScriptErrors();

    expect($party->refresh()->expire_at)->toBeNull();
});

// ── Poster ────────────────────────────────────────────────────────────────────

it('links to the poster download from the details dialog', function () {
    $party = Party::factory()->create(['name' => 'poster-party']);

    $page = visit(route('admin.parties.index'));

    openFirstPartyDetails($page)
        ->assertVisible('#download-party-poster')
        ->assertAttribute('#download-party-poster', 'href', route('admin.parties.poster', $party, absolute: false))
        ->assertNoJavaScriptErrors();
});

// ── Edit page ─────────────────────────────────────────────────────────────────

it('offers an expiry date field on the edit page', function () {
    $party = Party::factory()->create(['expire_at' => null]);

    $page = visit(route('admin.parties.edit', $party));

    $page->assertVisible('#expire_at')
        ->assertAttribute('#expire_at', 'type', 'datetime-local')
        ->assertValue('#expire_at', '')
        ->assertNoJavaScriptErrors();
});

it('saves an expiry date from the edit page', function () {
    $party = Party::factory()->create(['expire_at' => null]);

    $page = visit(route('admin.parties.edit', $party));

    $page->fill('#expire_at', '2030-06-15T18:00')
        ->click('Update party')
        ->assertNoJavaScriptErrors();

    expect($party->refresh()->expire_at)->not->toBeNull();

    visit(route('admin.parties.edit', $party))
        ->assertValue('#expire_at', '2030-06-15T18:00');
});

// ── Authentication ────────────────────────────────────────────────────────────

it('signs the user out from the sidebar menu', function () {
    $page = visit(route('admin.parties.index'));

    $page->click(auth()->user()->name)
        ->click('Log out')
        ->assertNoJavaScriptErrors();

    $this->assertGuest();
});
