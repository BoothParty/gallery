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
