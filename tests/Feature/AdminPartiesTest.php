<?php

use App\Models\Party;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('renders administration parties index with datatable data', function () {
    $party = Party::factory()->create(['name' => 'My Party']);
    $owner = User::factory()->create(['name' => 'Owner User']);

    $this->get('/admin/parties')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/parties/index')
            ->has('tableData.columns')
            ->has('tableData.rows', 1)
            ->has('users', 1)
            ->has('editableParties', 1)
            ->where('users.0.id', $owner->id)
            ->where('users.0.name', $owner->name)
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
        'pictures_enabled' => false,
        'audios_enabled' => true,
    ])->assertRedirect('/admin/parties');

    $party = Party::query()->where('name', 'Birthday Bash')->first();

    expect($party)->not->toBeNull();
    expect($party?->user_id)->toBe($owner->id)
        ->and($party?->pictures_enabled)->toBeFalse()
        ->and($party?->audios_enabled)->toBeTrue();
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
            ->has('users', 1)
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

it('deletes a party from administration', function () {
    $party = Party::factory()->create();

    $this->delete(route('admin.parties.destroy', $party))
        ->assertRedirect('/admin/parties');

    expect(Party::query()->whereKey($party->id)->exists())->toBeFalse();
});
