<?php

use App\Models\Party;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('guests cannot access parties page', function () {
    $response = $this->get(route('parties.index'));
    $response->assertRedirect(route('login'));
});

test('non-admin users cannot access parties page', function () {
    $user = User::factory()->create(['is_admin' => false]);
    
    $response = $this->actingAs($user)->get(route('parties.index'));
    $response->assertForbidden();
});

test('admin users can access parties page', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    
    $response = $this->actingAs($admin)->get(route('parties.index'));
    $response->assertOk();
});

test('parties page renders with correct Inertia component', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    
    $response = $this->actingAs($admin)->get(route('parties.index'));
    
    $response->assertInertia(fn (Assert $page) => $page
        ->component('parties')
        ->has('parties')
    );
});

test('parties page displays all parties', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $parties = Party::factory()->count(3)->create();
    
    $response = $this->actingAs($admin)->get(route('parties.index'));
    
    $response->assertInertia(fn (Assert $page) => $page
        ->component('parties')
        ->has('parties', 3)
    );
});

test('admin can update party name', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $party = Party::factory()->create(['name' => 'Old Name']);
    
    $response = $this->actingAs($admin)->put(route('parties.update', $party), [
        'name' => 'New Name',
        'expiration_date' => null,
    ]);
    
    $response->assertRedirect();
    $this->assertDatabaseHas('parties', [
        'id' => $party->id,
        'name' => 'New Name',
    ]);
});

test('admin can update party expiration date', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $party = Party::factory()->create();
    $newDate = now()->addDays(30)->toDateString();
    
    $response = $this->actingAs($admin)->put(route('parties.update', $party), [
        'name' => $party->name,
        'expiration_date' => $newDate,
    ]);
    
    $response->assertRedirect();
    $this->assertDatabaseHas('parties', [
        'id' => $party->id,
        'expiration_date' => $newDate,
    ]);
});

test('party update requires name', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $party = Party::factory()->create();
    
    $response = $this->actingAs($admin)->put(route('parties.update', $party), [
        'name' => '',
    ]);
    
    $response->assertSessionHasErrors(['name']);
});

test('party expiration date must be valid date', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $party = Party::factory()->create();
    
    $response = $this->actingAs($admin)->put(route('parties.update', $party), [
        'name' => 'Test Party',
        'expiration_date' => 'not-a-date',
    ]);
    
    $response->assertSessionHasErrors(['expiration_date']);
});

test('non-admin users cannot update parties', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $party = Party::factory()->create(['name' => 'Original Name']);
    
    $response = $this->actingAs($user)->put(route('parties.update', $party), [
        'name' => 'Hacked Name',
    ]);
    
    $response->assertForbidden();
    $this->assertDatabaseHas('parties', [
        'id' => $party->id,
        'name' => 'Original Name',
    ]);
});

test('admin can delete party', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $party = Party::factory()->create();
    
    $response = $this->actingAs($admin)->delete(route('parties.destroy', $party));
    
    $response->assertRedirect();
    $this->assertDatabaseMissing('parties', [
        'id' => $party->id,
    ]);
});

test('non-admin users cannot delete parties', function () {
    $user = User::factory()->create(['is_admin' => false]);
    $party = Party::factory()->create();
    
    $response = $this->actingAs($user)->delete(route('parties.destroy', $party));
    
    $response->assertForbidden();
    $this->assertDatabaseHas('parties', [
        'id' => $party->id,
    ]);
});

test('parties are displayed with owner information', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $owner = User::factory()->create(['name' => 'John Doe']);
    $party = Party::factory()->create(['owned_by' => $owner->id]);
    
    $response = $this->actingAs($admin)->get(route('parties.index'));
    
    $response->assertInertia(fn (Assert $page) => $page
        ->component('parties')
        ->where('parties.0.owner_name', 'John Doe')
    );
});

test('parties are displayed with pictures count', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $party = Party::factory()->hasPictures(5)->create();
    
    $response = $this->actingAs($admin)->get(route('parties.index'));
    
    $response->assertInertia(fn (Assert $page) => $page
        ->component('parties')
        ->where('parties.0.pictures_count', 5)
    );
});
