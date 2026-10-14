<?php

use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create(['name' => 'Admin']));
});

it('creates a user from the dialog', function () {
    $page = browserPage(fn () => visit(route('admin.users.index')));

    $page->click('Create user')
        ->fill('#create-user-name', 'Jane Doe')
        ->fill('#create-user-email', 'jane@example.com')
        ->click('Save user')
        ->assertSee('jane@example.com')
        ->assertNoJavaScriptErrors();

    expect(User::query()->where('email', 'jane@example.com')->exists())->toBeTrue();
});

it('prefills and updates a user from the dialog', function () {
    $page = browserPage(fn () => visit(route('admin.users.index')));

    $page->assertVisible('table tbody tr:first-child td:nth-child(2)')
        ->click('table tbody tr:first-child td:nth-child(2)')
        ->assertValue('#edit-user-name', 'Admin')
        ->fill('#edit-user-name', 'Renamed')
        ->click('Update user')
        ->assertSee('Renamed')
        ->assertNoJavaScriptErrors();

    expect(auth()->user()->refresh()->name)->toBe('Renamed');
});

it('titles the page with the last breadcrumb segment', function () {
    $page = browserPage(fn () => visit(route('admin.users.index')));

    $page->assertSeeIn('main > div h1', 'Users')
        ->assertSee('Administration')
        ->assertNoJavaScriptErrors();
});

it('renders dialog titles clearly larger than their description', function () {
    $page = browserPage(fn () => visit(route('admin.users.index')));

    $page->click('Create user')
        ->assertVisible('[data-slot="dialog-title"]');

    $fontSize = fn (string $slot): float => (float) $page->script(
        "parseFloat(getComputedStyle(document.querySelector('[data-slot=\"{$slot}\"]')).fontSize)"
    );

    // Instrument Serif has a small x-height: below ~1.4× the sans description it reads smaller.
    expect($fontSize('dialog-title'))->toBeGreaterThanOrEqual($fontSize('dialog-description') * 1.4);
});
