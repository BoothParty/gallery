<?php

use App\Models\Party;
use App\Models\Picture;
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

it('sums up each transfer under its name', function () {
    $transfer = Party::factory()->transfer()->create(['display_name' => 'Soirée Kerdal']);
    Picture::factory()->for($transfer)->count(2)->create(['size' => 254_000_000]);

    $page = browserPage(fn () => visit(route('admin.transfers.index')));

    $page->assertSeeIn('table tbody tr:first-child', 'Soirée Kerdal')
        ->assertSeeIn('table tbody tr:first-child', '2 photos · 508 MB')
        ->assertSeeIn('table tbody tr:first-child', 'No download yet')
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

it('starts the new transfer dialog with the folder', function () {
    $page = browserPage(fn () => visit(route('admin.transfers.index')));

    $page->click('New transfer')
        ->assertPresent('#transfer-folder[webkitdirectory]')
        ->assertSee('Drop the photobooth folder here')
        ->assertVisible('#transfer-name')
        ->assertSee('Add a folder of pictures first.')
        ->assertButtonDisabled('Create transfer')
        ->assertSee('The link stays available with no time limit.')
        ->click('30 days')
        ->assertSee('The link will expire on')
        ->assertNoJavaScriptErrors();
});

it('names the transfer after the picked folder', function () {
    $page = browserPage(fn () => visit(route('admin.transfers.index')));

    $page->click('New transfer')
        ->assertVisible('#transfer-folder');

    pickTransferFolder($page, 'Photobooth_Pinel_2026-10-14/IMG_0001.jpg');

    $page->assertValue('#transfer-name', 'Pinel')
        ->assertSee('Photobooth_Pinel_2026-10-14')
        ->assertButtonEnabled('Create transfer')
        ->assertNoJavaScriptErrors();
});

it('creates the transfer with the chosen expiry', function () {
    $page = browserPage(fn () => visit(route('admin.transfers.index')));

    $page->click('New transfer')
        ->assertVisible('#transfer-folder');

    pickTransferFolder($page, 'Photobooth_Pinel_2026-10-14/IMG_0001.jpg');

    $page->click('30 days')
        ->click('Create transfer')
        ->assertSee('Transfer created');

    $transfer = Party::query()->where('kind', 'transfer')->sole();

    expect($transfer->display_name)->toBe('Pinel')
        ->and($transfer->expire_at->between(now()->addDays(29), now()->addDays(31)))->toBeTrue();
});

it('opens the new transfer from the folder picked on the page', function () {
    $page = browserPage(fn () => visit(route('admin.transfers.index')));

    $page->assertSee('Drop the photobooth folder here');

    pickTransferFolder($page, 'Photobooth_Pinel_2026-10-14/IMG_0001.jpg', '#transfer-page-folder');

    $page->assertSee('New transfer')
        ->assertValue('#transfer-name', 'Pinel')
        ->assertSee('Photobooth_Pinel_2026-10-14')
        ->assertButtonEnabled('Create transfer')
        ->assertNoJavaScriptErrors();
});

it('veils the window while files are dragged over it, then opens the new transfer', function () {
    $page = browserPage(fn () => visit(route('admin.transfers.index')));

    $page->assertSee('Drop the photobooth folder here')
        ->script(<<<'JS'
            () => {
                const files = new DataTransfer()
                files.items.add(new File(['jpeg'], 'IMG_0001.jpg', { type: 'image/jpeg' }))
                window.droppedFiles = files
                document.body.dispatchEvent(new DragEvent('dragenter', { bubbles: true, cancelable: true, dataTransfer: files }))
            }
        JS);

    $page->assertPresent('[data-dragging=true]')
        ->script(<<<'JS'
            () => document.body.dispatchEvent(new DragEvent('drop', { bubbles: true, cancelable: true, dataTransfer: window.droppedFiles }))
        JS);

    $page->assertPresent('[data-dragging=false]')
        ->assertSee('/ — 1 photo')
        ->assertNoJavaScriptErrors();
});

it('reaches the transfers screen from the sidebar', function () {
    $page = browserPage(fn () => visit(route('admin.parties.index')));

    $page->click('Transfers')
        ->assertSeeIn('main > div h1', 'Transfers')
        ->assertNoJavaScriptErrors();
});

/*
 * Pest's browser server does not forward multipart bodies, so every picture
 * fails to upload here: the panel is checked on that path.
 */
it('reports the pictures that failed to upload', function () {
    $page = browserPage(fn () => visit(route('admin.transfers.index')));

    $page->click('New transfer')
        ->assertVisible('#transfer-folder');

    pickTransferFolder($page, 'Photobooth_Pinel_2026-10-14/IMG_0001.jpg');

    $page->click('Create transfer')
        ->assertPresent('[data-upload-status=done]')
        ->assertSee('0 / 1 photos sent')
        ->assertSee('0 already online · 1 failed')
        ->assertSee('Failed: IMG_0001.jpg')
        ->assertSee('Retry failed')
        ->assertSee('Some pictures did not go through')
        ->assertNoJavaScriptErrors();
});

it('copies the link of the new transfer and closes', function () {
    $page = browserPage(fn () => visit(route('admin.transfers.index')));

    $page->click('New transfer')
        ->assertVisible('#transfer-folder')
        ->script("Object.defineProperty(navigator, 'clipboard', { value: { writeText: (text) => { window.copiedText = text; return Promise.resolve() } } })");

    pickTransferFolder($page, 'Photobooth_Pinel_2026-10-14/IMG_0001.jpg');

    $page->click('Create transfer')
        ->assertSee('Transfer created')
        ->assertValue('#transfer-link', Party::query()->sole()->publicUrl())
        ->click('Copy link and close')
        ->assertMissing('[role=dialog]');

    expect($page->script('window.copiedText'))->toBe(Party::query()->sole()->publicUrl());
});

/*
 * The picture requests are held so the upload stays in flight while the
 * dialog is closed and opened again.
 */
it('keeps uploading once the dialog is closed and shows it in the row', function () {
    $page = browserPage(fn () => visit(route('admin.transfers.index')));

    $page->click('New transfer')
        ->assertVisible('#transfer-folder')
        ->script(<<<'JS'
            () => {
                const fetch = window.fetch
                window.fetch = (url, options) => String(url).endsWith('/dashboard/pictures') ? new Promise(() => {}) : fetch(url, options)
            }
        JS);

    pickTransferFolder($page, 'Photobooth_Pinel_2026-10-14/IMG_0001.jpg');

    $page->click('Create transfer')
        ->assertSee('even once this window is closed')
        ->click('Close')
        ->assertMissing('[role=dialog]')
        ->assertSeeIn('table tbody tr:first-child', 'Pinel')
        ->assertSeeIn('table tbody tr:first-child', 'Sending 0 / 1 photos')
        ->assertButtonDisabled('Drop the photobooth folder here')
        ->click('Show upload')
        ->assertSee('Transfer created')
        ->assertSee('Sending 0 / 1 photos')
        ->assertNoJavaScriptErrors();
});

/*
 * Both clicks are dispatched in the same task, as a double click lands: the
 * second one must find the create button gone rather than create a second
 * transfer. Pest's browser server does not forward multipart bodies, so the
 * pictures themselves fail to upload; only the transfers created are counted.
 */
it('creates a single transfer when create is clicked twice', function () {
    $page = browserPage(fn () => visit(route('admin.transfers.index')));

    $page->click('New transfer')
        ->fill('#transfer-name', 'Mariage Julie');

    pickTransferFolder($page, 'IMG_0001.jpg');

    $page->assertButtonEnabled('Create transfer');

    $page->script(<<<'JS'
        () => {
            const create = [...document.querySelectorAll('button')].find((button) => button.textContent === 'Create transfer')
            create.click()
            create.click()
        }
    JS);

    $page->assertSee('Transfer created');

    expect(Party::query()->where('kind', 'transfer')->count())->toBe(1);
});

/**
 * Playwright only feeds a webkitdirectory input from a real directory, which
 * the browser plugin cannot pass: the picked file is set by hand, its relative
 * path included, as a picked folder would give it.
 */
function pickTransferFolder(object $page, string $relativePath, string $input = '#transfer-folder'): void
{
    $page->script(<<<JS
        () => {
            const input = document.querySelector('{$input}')
            const file = new File(['jpeg'], '{$relativePath}'.split('/').pop(), { type: 'image/jpeg' })
            Object.defineProperty(file, 'webkitRelativePath', { value: '{$relativePath}'.includes('/') ? '{$relativePath}' : '' })
            const files = new DataTransfer()
            files.items.add(file)
            input.files = files.files
            input.dispatchEvent(new Event('change', { bubbles: true }))
        }
    JS);
}
