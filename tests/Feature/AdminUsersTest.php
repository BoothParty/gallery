<?php

use App\Models\Party;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('renders administration users index with one row per user', function () {
    $user = User::factory()->create(['name' => 'Alice']);
    Party::factory()->count(2)->create(['user_id' => $user->id]);

    $this->get('/admin/users')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/users/index')
            ->has('rows', 2)
            ->where('rows', fn ($rows) => collect($rows)
                ->contains(fn (array $row) => $row['name'] === 'Alice' && $row['parties_count'] === 2)
            )
        );
});

it('stores a new user from administration create page', function () {
    Party::factory()->create();

    $this->post('/admin/users', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ])->assertRedirect('/admin/users');

    expect(User::query()->where('email', 'jane@example.com')->exists())->toBeTrue();
});

it('stores a user without any password column', function () {
    Party::factory()->create();

    $this->post('/admin/users', [
        'name' => 'Jane Doe',
        'email' => 'jane@example.com',
    ])->assertRedirect('/admin/users');

    $user = User::query()->where('email', 'jane@example.com')->first();

    expect($user)->not->toBeNull();
    expect($user->getAttributes())->not->toHaveKey('password');
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

    $this->assertModelMissing($user);
});
