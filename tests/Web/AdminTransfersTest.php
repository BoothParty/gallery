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
