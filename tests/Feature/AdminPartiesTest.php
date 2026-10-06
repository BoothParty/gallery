<?php

use App\Models\Party;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('renders administration parties index with one row per party', function () {
    $party = Party::factory()->create(['name' => 'My Party']);
    $owner = User::factory()->create(['name' => 'Owner User']);

    $this->get('/admin/parties')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/parties/index')
            ->has('rows', 1)
            ->where('users', fn ($users) => collect($users)
                ->contains(fn (array $user) => $user['id'] === $owner->id && $user['name'] === $owner->name)
            )
            ->where('rows.0.id', $party->id)
            ->where('rows.0.name', $party->name)
        );
});

it('lists every party, not only the first fifty', function () {
    Party::factory()->count(60)->create();

    $this->get('/admin/parties')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->has('rows', 60));
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

it('exposes the owner and the picture count of each party', function () {
    $owner = User::factory()->create(['name' => 'Owner Label']);
    $party = Party::factory()->create([
        'name' => 'Linked Party',
        'user_id' => $owner->id,
    ]);

    $this->get(route('admin.parties.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('rows.0.name', $party->name)
            ->where('rows.0.user_id', $owner->id)
            ->where('rows.0.owner', 'Owner Label')
            ->where('rows.0.pictures_count', 0)
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
    Party::factory()->create(['expire_at' => '2030-06-15 18:00:00']);

    $this->get('/admin/parties')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('rows.0.expire_at', '2030-06-15T18:00:00+00:00'));
});

it('exposes a null expiry date when the party never expires', function () {
    Party::factory()->create(['expire_at' => null]);

    $this->get('/admin/parties')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('rows.0.expire_at', null));
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

    $this->assertModelMissing($party);
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

// ── Event metadata ───────────────────────────────────────────────────────────

it('updates the display name and description of a party', function () {
    $party = Party::factory()->create();

    $this->put(route('admin.parties.update', $party), [
        'name' => $party->name,
        'display_name' => 'Camille & Antoine',
        'description' => 'Samedi 12 septembre · Domaine des Ormes',
    ])->assertRedirect('/admin/parties');

    expect($party->refresh())
        ->display_name->toBe('Camille & Antoine')
        ->description->toBe('Samedi 12 septembre · Domaine des Ormes');
});

it('leaves the metadata untouched when the fields are not submitted', function () {
    $party = Party::factory()->create([
        'display_name' => 'Kept Title',
        'description' => 'Kept description',
    ]);

    $this->put(route('admin.parties.update', $party), [
        'name' => $party->name,
        'user_id' => null,
    ])->assertRedirect('/admin/parties');

    expect($party->refresh())
        ->display_name->toBe('Kept Title')
        ->description->toBe('Kept description');
});

it('stores an uploaded banner for a party', function () {
    Storage::fake('s3');

    $party = Party::factory()->create();

    $this->put(route('admin.parties.update', $party), [
        'name' => $party->name,
        'banner' => UploadedFile::fake()->image('cover.jpg', 1200, 600),
    ])->assertRedirect('/admin/parties');

    $party->refresh();

    expect($party->banner_path)->not->toBeNull();

    Storage::disk('s3')->assertExists($party->banner_path);
});

it('deletes the previous file when the banner is replaced from administration', function () {
    Storage::fake('s3');

    $party = Party::factory()->create();
    Storage::disk('s3')->put('banners/old.jpg', 'binary');
    $party->forceFill(['banner_path' => 'banners/old.jpg'])->save();

    $this->put(route('admin.parties.update', $party), [
        'name' => $party->name,
        'banner' => UploadedFile::fake()->image('cover.jpg'),
    ])->assertRedirect('/admin/parties');

    expect($party->refresh()->banner_path)->not->toBe('banners/old.jpg');

    Storage::disk('s3')->assertMissing('banners/old.jpg');
});

it('removes the banner when the removal flag is submitted', function () {
    Storage::fake('s3');

    $party = Party::factory()->create();
    Storage::disk('s3')->put('banners/current.jpg', 'binary');
    $party->forceFill(['banner_path' => 'banners/current.jpg'])->save();

    $this->put(route('admin.parties.update', $party), [
        'name' => $party->name,
        'remove_banner' => true,
    ])->assertRedirect('/admin/parties');

    expect($party->refresh()->banner_path)->toBeNull();

    Storage::disk('s3')->assertMissing('banners/current.jpg');
});

it('removes the banner rather than storing one when both are submitted', function () {
    Storage::fake('s3');

    $party = Party::factory()->create();
    Storage::disk('s3')->put('banners/current.jpg', 'binary');
    $party->forceFill(['banner_path' => 'banners/current.jpg'])->save();

    $this->put(route('admin.parties.update', $party), [
        'name' => $party->name,
        'remove_banner' => true,
        'banner' => UploadedFile::fake()->image('cover.jpg'),
    ])->assertRedirect('/admin/parties');

    expect($party->refresh()->banner_path)->toBeNull();
});

it('rejects a description longer than 255 characters', function () {
    $party = Party::factory()->create(['description' => 'Short one']);

    $this->put(route('admin.parties.update', $party), [
        'name' => $party->name,
        'description' => str_repeat('a', 256),
    ])->assertSessionHasErrors('description');

    expect($party->refresh()->description)->toBe('Short one');
});

it('rejects a banner that is not an image', function () {
    Storage::fake('s3');

    $party = Party::factory()->create();

    $this->put(route('admin.parties.update', $party), [
        'name' => $party->name,
        'banner' => UploadedFile::fake()->create('contract.pdf', 100, 'application/pdf'),
    ])->assertSessionHasErrors('banner');

    expect($party->refresh()->banner_path)->toBeNull();
});

it('exposes the metadata to the party edit dialog', function () {
    Storage::fake('s3');

    $party = Party::factory()->create([
        'display_name' => 'Edited Title',
        'description' => 'Edited description',
    ]);
    $party->forceFill(['banner_path' => 'banners/cover.jpg'])->save();

    $this->get('/admin/parties')
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('rows.0.display_name', 'Edited Title')
            ->where('rows.0.description', 'Edited description')
            ->where('rows.0.banner_url', fn (string $url) => str_contains($url, 'banners/cover.jpg')
                && str_contains($url, 'X-Amz-Signature='))
        );
});

it('exposes a null banner url when the party has no banner', function () {
    Party::factory()->create();

    $this->get('/admin/parties')
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('rows.0.banner_url', null));
});

// ── Host edit link ──────────────────────────────────────────────────────────

it('exposes the host edit link to the parties index', function () {
    $party = Party::factory()->create();

    $this->get(route('admin.parties.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('rows.0.host_edit_url', $party->hostEditUrl())
        );
});

it('regenerates the host token', function () {
    $party = Party::factory()->create();
    $before = $party->host_token;

    $this->post(route('admin.parties.host-token', $party))
        ->assertRedirect(route('admin.parties.index'));

    expect($party->fresh()->host_token)->not->toBe($before)->toHaveLength(48);
});

it('stops the old link from opening a host session once regenerated', function () {
    $party = Party::factory()->create();
    $stale = $party->host_token;

    $this->post(route('admin.parties.host-token', $party));

    auth()->logout();

    $this->get(route('party.gallery', $party).'?token='.$stale)
        ->assertRedirect(route('party.gallery', $party))
        ->assertSessionMissing('party_host.'.$party->id);
});

it('refuses to regenerate a host token for a guest', function () {
    $party = Party::factory()->create();

    auth()->logout();

    $this->post(route('admin.parties.host-token', $party))->assertRedirect(route('login'));
});
