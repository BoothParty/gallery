<?php

use App\Models\Party;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('renders administration users index with datatable data', function () {
    $user = User::factory()->create(['name' => 'Alice']);
    Party::factory()->count(2)->create(['user_id' => $user->id]);

    $this->get('/admin/users')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/users/index')
            ->has('tableData.columns')
            ->has('tableData.rows', 2)
            ->where('tableData.rows', fn ($rows) => collect($rows)
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
