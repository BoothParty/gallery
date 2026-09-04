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

/*
 * The row is asserted before it is clicked because navigate() hands the page
 * back while the navigation is still in flight, and a click sent during it is
 * interrupted.
 */
function openFirstPartyDetails(object $page): object
{
    return $page->assertVisible('table tbody tr:first-child td:nth-child(2)')
        ->click('table tbody tr:first-child td:nth-child(2)');
}

// ── Create dialog ─────────────────────────────────────────────────────────────

it('offers an expiry date field in the create dialog', function () {
    $page = browserPage(fn () => visit(route('admin.parties.index')));

    $page->click('Create party')
        ->assertVisible('#create-party-expire-at')
        ->assertAttribute('#create-party-expire-at', 'type', 'datetime-local')
        ->assertValue('#create-party-expire-at', '')
        ->assertNoJavaScriptErrors();
});

it('round-trips an expiry date entered in the create dialog', function () {
    $page = browserPage(fn () => visit(route('admin.parties.index')));

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
    $page = browserPage(fn () => visit(route('admin.parties.index')));

    $page->click('Create from events')
        ->assertSee('Create parties from upcoming events')
        ->assertVisible('#bulk-create-party-name-0')
        ->assertSee('.'.config('party.domain'))
        ->assertNoJavaScriptErrors();

    expect($page->value('#bulk-create-party-name-0'))->toMatch('/^[a-z0-9-]+$/');
});

it('creates the selected parties from the bulk create dialog', function () {
    $page = browserPage(fn () => visit(route('admin.parties.index')));

    $page->click('Create from events')
        ->assertVisible('#bulk-create-party-name-0')
        ->click('#bulk-create-select-0')
        ->fill('#bulk-create-party-name-0', 'browser-bulk-party')
        ->click('Create 1 party')
        ->assertNoJavaScriptErrors();

    expect(Party::query()->where('name', 'browser-bulk-party')->exists())->toBeTrue();
});

it('reloads the upcoming events from the bulk create dialog', function () {
    $page = browserPage(fn () => visit(route('admin.parties.index')));

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

    $page = browserPage(fn () => visit(route('admin.parties.index')));

    $page->click('Create from events')
        ->assertSee('Already created')
        ->assertNoJavaScriptErrors();

    expect($page->attribute('#bulk-create-party-name-0', 'disabled'))->not->toBeNull();
});

// ── Edit dialog ───────────────────────────────────────────────────────────────

it('prefills the expiry date in the edit dialog', function () {
    Party::factory()->create([
        'name' => 'Timed Party',
        'expire_at' => '2030-06-15 18:00:00',
    ]);

    $page = browserPage(fn () => visit(route('admin.parties.index')));

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

    $page = browserPage(fn () => visit(route('admin.parties.index')));

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

    $page = browserPage(fn () => visit(route('admin.parties.index')));

    openFirstPartyDetails($page)
        ->clear('#edit-party-expire-at')
        ->click('Update party')
        ->assertNoJavaScriptErrors();

    expect($party->refresh()->expire_at)->toBeNull();
});

it('round-trips an expiry date entered in the edit dialog', function () {
    $party = Party::factory()->create(['expire_at' => null]);

    $page = browserPage(fn () => visit(route('admin.parties.index')));

    openFirstPartyDetails($page)
        ->fill('#edit-party-expire-at', '2030-06-15T18:00')
        ->click('Update party')
        ->assertNoJavaScriptErrors();

    expect($party->refresh()->expire_at)->not->toBeNull();

    openFirstPartyDetails($page->navigate(route('admin.parties.index')))
        ->assertValue('#edit-party-expire-at', '2030-06-15T18:00')
        ->assertNoJavaScriptErrors();
});

// ── Poster ────────────────────────────────────────────────────────────────────

it('links to the poster download from the details dialog', function () {
    $party = Party::factory()->create(['name' => 'poster-party']);

    $page = browserPage(fn () => visit(route('admin.parties.index')));

    openFirstPartyDetails($page)
        ->assertVisible('#download-party-poster')
        ->assertAttribute('#download-party-poster', 'href', route('admin.parties.poster', $party, absolute: false))
        ->assertNoJavaScriptErrors();
});

// ── Event metadata ────────────────────────────────────────────────────────────

it('saves the display name and description from the edit dialog', function () {
    $party = Party::factory()->create();

    $page = browserPage(fn () => visit(route('admin.parties.index')));

    openFirstPartyDetails($page)
        ->fill('#edit-party-display-name', 'Camille & Antoine')
        ->fill('#edit-party-description', 'Samedi 12 septembre · Domaine des Ormes')
        ->click('Update party')
        ->assertNoJavaScriptErrors();

    expect($party->refresh())
        ->display_name->toBe('Camille & Antoine')
        ->description->toBe('Samedi 12 septembre · Domaine des Ormes');
});

it('prefills the metadata in the edit dialog', function () {
    Party::factory()->create([
        'display_name' => 'Stored Title',
        'description' => 'Stored description',
    ]);

    $page = browserPage(fn () => visit(route('admin.parties.index')));

    openFirstPartyDetails($page)
        ->assertValue('#edit-party-display-name', 'Stored Title')
        ->assertValue('#edit-party-description', 'Stored description')
        ->assertNoJavaScriptErrors();
});

it('offers a banner file field in the edit dialog', function () {
    Party::factory()->create();

    $page = browserPage(fn () => visit(route('admin.parties.index')));

    openFirstPartyDetails($page)
        ->assertVisible('#edit-party-banner')
        ->assertAttribute('#edit-party-banner', 'type', 'file')
        ->assertNoJavaScriptErrors();
});

it('previews the current banner and offers to remove it', function () {
    $party = Party::factory()->withBanner()->create();

    $page = browserPage(fn () => visit(route('admin.parties.index')));

    openFirstPartyDetails($page)
        ->assertVisible('#edit-party-banner-preview')
        ->assertAttribute('#edit-party-banner-preview', 'src', $party->bannerUrl())
        ->assertVisible('#edit-party-remove-banner')
        ->assertNoJavaScriptErrors();
});

it('hides the banner preview and removal box when the party has no banner', function () {
    Party::factory()->create();

    $page = browserPage(fn () => visit(route('admin.parties.index')));

    openFirstPartyDetails($page)
        ->assertMissing('#edit-party-banner-preview')
        ->assertMissing('#edit-party-remove-banner')
        ->assertNoJavaScriptErrors();
});

it('removes the banner from the edit dialog', function () {
    $party = Party::factory()->withBanner()->create();

    $page = browserPage(fn () => visit(route('admin.parties.index')));

    openFirstPartyDetails($page)
        ->click('#edit-party-remove-banner')
        ->click('Update party')
        ->assertNoJavaScriptErrors();

    expect($party->refresh()->banner_path)->toBeNull();
});

// ── Authentication ────────────────────────────────────────────────────────────

it('signs the user out from the sidebar menu', function () {
    $page = browserPage(fn () => visit(route('admin.parties.index')));

    $page->click(auth()->user()->name)
        ->click('Log out')
        ->assertNoJavaScriptErrors();

    $this->assertGuest();
});
