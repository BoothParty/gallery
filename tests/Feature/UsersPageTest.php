<?php

use App\Models\User;
use Inertia\Testing\AssertableInertia;

test('users page requires authentication', function () {
    $response = $this->get(route('users.index'));

    $response->assertRedirect(route('login'));
});

test('users page requires admin access', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $response = $this->actingAs($user)->get(route('users.index'));

    $response->assertForbidden();
});

test('admin can view users page', function () {
    $admin = User::factory()->create(['is_admin' => true]);

    $response = $this->actingAs($admin)->get(route('users.index'));

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('users')
        ->has('users')
    );
});

test('users page displays all users', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $regularUsers = User::factory()->count(3)->create(['is_admin' => false]);

    $response = $this->actingAs($admin)->get(route('users.index'));

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('users')
        ->has('users', 4) // 3 regular users + 1 admin
        ->where('users.0.id', fn ($id) => in_array($id, [$admin->id, ...$regularUsers->pluck('id')->toArray()]))
    );
});

test('users page shows correct user information', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create([
        'name' => 'Test User',
        'email' => 'test@example.com',
        'is_admin' => false,
        'email_verified_at' => now(),
    ]);

    $response = $this->actingAs($admin)->get(route('users.index'));

    $response->assertOk();
    $response->assertInertia(fn (AssertableInertia $page) => $page
        ->component('users')
        ->has('users', 2)
        ->where('users.1.name', 'Test User')
        ->where('users.1.email', 'test@example.com')
        ->where('users.1.is_admin', false)
        ->has('users.1.email_verified_at')
        ->has('users.1.created_at')
    );
});

test('admin can update user information', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create(['name' => 'Old Name', 'email' => 'old@example.com']);

    $response = $this->actingAs($admin)->put(route('users.update', $user), [
        'name' => 'New Name',
        'email' => 'new@example.com',
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('success', 'User updated successfully.');

    $user->refresh();
    expect($user->name)->toBe('New Name');
    expect($user->email)->toBe('new@example.com');
});

test('admin can toggle user admin status', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create(['is_admin' => false]);

    $response = $this->actingAs($admin)->patch(route('users.toggle-admin', $user));

    $response->assertRedirect();
    $response->assertSessionHas('success');

    $user->refresh();
    expect((bool) $user->is_admin)->toBeTrue();
});

test('admin can delete user', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $user = User::factory()->create(['name' => 'User To Delete']);

    $response = $this->actingAs($admin)->delete(route('users.destroy', $user));

    $response->assertRedirect();
    $response->assertSessionHas('success');

    expect(User::find($user->id))->toBeNull();
});

test('non-admin cannot update users', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $targetUser = User::factory()->create();

    $response = $this->actingAs($user)->put(route('users.update', $targetUser), [
        'name' => 'New Name',
        'email' => 'new@example.com',
    ]);

    $response->assertForbidden();
});

test('non-admin cannot toggle admin status', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $targetUser = User::factory()->create();

    $response = $this->actingAs($user)->patch(route('users.toggle-admin', $targetUser));

    $response->assertForbidden();
});

test('non-admin cannot delete users', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $targetUser = User::factory()->create();

    $response = $this->actingAs($user)->delete(route('users.destroy', $targetUser));

    $response->assertForbidden();
});

