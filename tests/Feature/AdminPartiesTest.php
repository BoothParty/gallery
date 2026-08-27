<?php

use App\Models\Party;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('renders administration parties index with datatable data', function () {
    $party = Party::factory()->create(['name' => 'My Party']);
    $owner = User::factory()->create(['name' => 'Owner User']);

    $this->get('/admin/parties')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/parties/index')
            ->has('tableData.columns')
            ->has('tableData.rows', 1)
            ->has('editableParties', 1)
            ->where('users', fn ($users) => collect($users)
                ->contains(fn (array $user) => $user['id'] === $owner->id && $user['name'] === $owner->name)
            )
            ->where('editableParties.0.id', $party->id)
            ->where('editableParties.0.name', $party->name)
            ->where('editableParties.0.route_key', $party->name)
            ->where('tableData.rows.0.name', $party->name)
        );
});

it('stores a new party from administration create modal', function () {
    $owner = User::factory()->create();

    $this->post('/admin/parties', [
        'name' => 'Birthday Bash',
        'user_id' => $owner->id,
    ])->assertRedirect('/admin/parties');

    $party = Party::query()->where('name', 'Birthday Bash')->first();

    expect($party)->not->toBeNull();
    expect($party?->user_id)->toBe($owner->id);
});

it('maps relation labels and exposes count-link metadata in parties datatable', function () {
    $owner = User::factory()->create(['name' => 'Owner Label']);
    $party = Party::factory()->create([
        'name' => 'Linked Party',
        'user_id' => $owner->id,
    ]);

    $this->get('/admin/parties')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('tableData.rows.0.name', $party->name)
            ->where('tableData.rows.0.user_id', 'Owner Label')
            ->where('tableData.rows.0.pictures_count', 0)
            ->where('tableData.columns', fn ($columns) => collect($columns)
                ->contains(fn (array $column) => ($column['name'] ?? null) === 'pictures_count'
                    && ($column['type'] ?? null) === 'count_link'
                    && ($column['linkHref'] ?? null) === '/admin/parties'
                    && ! array_key_exists('countRelation', $column)
                )
            )
        );
});

it('renders administration party edit page', function () {
    $owner = User::factory()->create(['name' => 'Current Owner']);
    $party = Party::factory()->create([
        'name' => 'Editable Party',
        'user_id' => $owner->id,
    ]);

    $this->get(route('admin.parties.edit', $party))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/parties/edit')
            ->where('party.id', $party->id)
            ->where('party.name', 'Editable Party')
            ->where('party.user_id', $owner->id)
            ->where('users', fn ($users) => collect($users)
                ->contains(fn (array $user) => $user['id'] === $owner->id)
            )
        );
});

it('updates an existing party from administration', function () {
    $owner = User::factory()->create();
    $newOwner = User::factory()->create();
    $party = Party::factory()->create([
        'name' => 'Old Party Name',
        'user_id' => $owner->id,
    ]);

    $this->put(route('admin.parties.update', $party), [
        'name' => 'New Party Name',
        'user_id' => $newOwner->id,
    ])->assertRedirect('/admin/parties');

    $party->refresh();

    expect($party->name)->toBe('New Party Name');
    expect($party->user_id)->toBe($newOwner->id);
});

it('stores a party with an expiry date from administration', function () {
    $this->post('/admin/parties', [
        'name' => 'Expiring Party',
        'user_id' => null,
        'expire_at' => '2030-06-15T16:00:00.000Z',
    ])->assertRedirect('/admin/parties');

    $party = Party::query()->where('name', 'Expiring Party')->first();

    expect($party?->expire_at?->toDateTimeString())->toBe('2030-06-15 16:00:00');
});

it('updates the expiry date of an existing party from administration', function () {
    $party = Party::factory()->create(['expire_at' => null]);

    $this->put(route('admin.parties.update', $party), [
        'name' => $party->name,
        'user_id' => null,
        'expire_at' => '2031-01-02T09:30:00.000Z',
    ])->assertRedirect('/admin/parties');

    expect($party->refresh()->expire_at?->toDateTimeString())->toBe('2031-01-02 09:30:00');
});

it('clears the expiry date when the field is submitted empty', function () {
    $party = Party::factory()->create(['expire_at' => now()->addWeek()]);

    $this->put(route('admin.parties.update', $party), [
        'name' => $party->name,
        'user_id' => null,
        'expire_at' => null,
    ])->assertRedirect('/admin/parties');

    expect($party->refresh()->expire_at)->toBeNull();
});

it('exposes the expiry date to the administration forms as an absolute instant', function () {
    $party = Party::factory()->create(['expire_at' => '2030-06-15 18:00:00']);

    $this->get(route('admin.parties.edit', $party))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('party.expire_at', '2030-06-15T18:00:00+00:00'));

    $this->get('/admin/parties')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('editableParties.0.expire_at', '2030-06-15T18:00:00+00:00'));
});

it('exposes a null expiry date when the party never expires', function () {
    $party = Party::factory()->create(['expire_at' => null]);

    $this->get(route('admin.parties.edit', $party))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('party.expire_at', null));
});

it('rejects an invalid expiry date from administration', function () {
    $this->post('/admin/parties', [
        'name' => 'Bad Date Party',
        'expire_at' => 'not-a-date',
    ])->assertSessionHasErrors('expire_at');

    expect(Party::query()->where('name', 'Bad Date Party')->exists())->toBeFalse();
});

it('deletes a party from administration', function () {
    $party = Party::factory()->create();

    $this->delete(route('admin.parties.destroy', $party))
        ->assertRedirect('/admin/parties');

    expect(Party::query()->whereKey($party->id)->exists())->toBeFalse();
});

it('rejects creating a party whose name is already taken', function () {
    Party::factory()->create(['name' => 'Taken Name']);

    $this->post('/admin/parties', [
        'name' => 'Taken Name',
    ])->assertSessionHasErrors('name');

    expect(Party::query()->where('name', 'Taken Name')->count())->toBe(1);
});

it('accepts updating a party with its own unchanged name', function () {
    $party = Party::factory()->create(['name' => 'Unchanged Name']);

    $this->put(route('admin.parties.update', $party), [
        'name' => 'Unchanged Name',
        'user_id' => null,
    ])->assertRedirect('/admin/parties')
        ->assertSessionDoesntHaveErrors();

    expect($party->refresh()->name)->toBe('Unchanged Name');
});
