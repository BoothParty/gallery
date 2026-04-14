<?php

use App\Models\Party;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders administration users index with datatable data', function () {
    $user = User::factory()->create(['name' => 'Alice']);
    Party::factory()->count(2)->create(['user_id' => $user->id]);

    $this->get('/admin/users')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/users/index')
            ->has('tableData.columns')
            ->has('tableData.rows', 1)
            ->where('tableData.rows.0.name', 'Alice')
            ->where('tableData.rows.0.parties_count', 2)
        );
});

it('stores a new user from administration create page', function () {
    Party::factory()->create();

    $this->post('/admin/users', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
        'password' => 'password',
        'password_confirmation' => 'password',
    ])->assertRedirect('/admin/users');

    expect(User::query()->where('email', 'jane@example.com')->exists())->toBeTrue();
});

it('updates an existing user from administration edit page', function () {
    Party::factory()->create();
    $user = User::factory()->create([
        'name' => 'Old Name',
        'email' => 'old@example.com',
    ]);

    $this->put("/admin/users/{$user->id}", [
        'name' => 'New Name',
        'email' => 'new@example.com',
        'password' => '',
        'password_confirmation' => '',
    ])->assertRedirect('/admin/users');

    $user->refresh();

    expect($user->name)->toBe('New Name');
    expect($user->email)->toBe('new@example.com');
});

it('deletes a user from administration', function () {
    Party::factory()->create();
    $user = User::factory()->create();

    $this->delete("/admin/users/{$user->id}")
        ->assertRedirect('/admin/users');

    expect(User::query()->whereKey($user->id)->exists())->toBeFalse();
});

it('renders administration create user page', function () {
    $this->get('/admin/users/create')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/users/create')
            ->has('parties')
            ->has('searchableParties')
        );
});

it('renders administration edit user page', function () {
    $user = User::factory()->create([
        'name' => 'Edit Me',
        'email' => 'edit@example.com',
    ]);

    $this->get("/admin/users/{$user->id}/edit")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/users/edit')
            ->where('user.id', $user->id)
            ->where('user.name', 'Edit Me')
            ->where('user.email', 'edit@example.com')
        );
});

it('keeps existing password when updating a user with empty password', function () {
    $user = User::factory()->create([
        'password' => 'initial-password',
    ]);

    $originalHash = $user->password;

    $this->put("/admin/users/{$user->id}", [
        'name' => 'Same Password',
        'email' => 'same-password@example.com',
        'password' => '',
        'password_confirmation' => '',
    ])->assertRedirect('/admin/users');

    $user->refresh();

    expect($user->password)->toBe($originalHash);
});
