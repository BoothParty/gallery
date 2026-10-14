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

/*
 * Headless Chromium refuses clipboard writes, so the clipboard is stubbed once
 * the real page is up: a script run earlier would land on about:blank.
 */
it('confirms a copied link for a moment', function () {
    $transfer = Party::factory()->transfer()->create();

    $page = browserPage(fn () => visit(route('admin.transfers.index')));

    $page->assertSee('Copy link')
        ->script("Object.defineProperty(navigator, 'clipboard', { value: { writeText: (text) => { window.copiedText = text; return Promise.resolve() } } })");

    $page->click('Copy link')
        ->assertSee('Copied');

    expect($page->script('window.copiedText'))->toBe($transfer->publicUrl());

    $page->wait(2)
        ->assertSee('Copy link')
        ->assertDontSee('Copied')
        ->assertNoJavaScriptErrors();
});

it('shows the state and the expiry date of each transfer', function () {
    Party::factory()->transfer()->create(['display_name' => 'Soirée Kerdal', 'expire_at' => now()->addDays(3)]);
    Party::factory()->transfer()->expired()->create(['display_name' => 'Mariage Lemoine']);

    $page = browserPage(fn () => visit(route('admin.transfers.index')));

    $page->assertSeeIn('table tbody tr:has([data-expiry=soon])', 'Soirée Kerdal')
        ->assertSeeIn('table tbody tr:has([data-expiry=soon])', 'Online')
        ->assertSeeIn('table tbody tr:has([data-expiry=expired])', 'Mariage Lemoine')
        ->assertSeeIn('table tbody tr:has([data-expiry=expired])', 'Expired')
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
        ->assertSeeIn('main > div h1', 'Transfers')
        ->assertNoJavaScriptErrors();
});

/*
 * Both clicks are dispatched in the same task, as a double click lands: the
 * second one must find the send button gone rather than create a second
 * transfer. Pest's browser server does not forward multipart bodies, so the
 * pictures themselves fail to upload; only the transfers created are counted.
 */
it('creates a single transfer when send is clicked twice', function () {
    $page = browserPage(fn () => visit(route('admin.transfers.index')));

    $page->click('New transfer')
        ->fill('#transfer-name', 'Mariage Julie');

    // Playwright only feeds a webkitdirectory input from a real directory,
    // which the browser plugin cannot pass: the picked file is set by hand.
    $page->script(<<<'JS'
        () => {
            const input = document.querySelector('#transfer-folder')
            const files = new DataTransfer()
            files.items.add(new File(['jpeg'], 'IMG_0001.jpg', { type: 'image/jpeg' }))
            input.files = files.files
            input.dispatchEvent(new Event('change', { bubbles: true }))
        }
    JS);

    $page->assertSee('Send 1 photos');

    $page->script(<<<'JS'
        () => {
            const send = [...document.querySelectorAll('button')].find((button) => button.textContent.startsWith('Send'))
            send.click()
            send.click()
        }
    JS);

    $page->assertSee('Link to send');

    expect(Party::query()->where('kind', 'transfer')->count())->toBe(1);
});
