<?php

use App\Enums\PartyKind;
use App\Models\Party;
use App\Models\Picture;
use App\Models\User;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('lists only transfers, newest first, with their link and picture count', function () {
    Party::factory()->create();
    $older = Party::factory()->transfer()->create(['created_at' => now()->subDay()]);
    $newer = Party::factory()->transfer()->create();
    Picture::factory()->for($newer)->count(2)->create();

    $this->get(route('admin.transfers.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/transfers/index')
            ->has('rows', 2)
            ->where('rows.0.id', $newer->id)
            ->where('rows.0.pictures_count', 2)
            ->where('rows.0.public_url', url('/transfers/'.$newer->name))
            ->where('rows.1.id', $older->id)
            ->has('parties')
        );
});

it('sums the weight of each transfer and lists its first four thumbnails', function () {
    $transfer = Party::factory()->transfer()->create();
    $pictures = Picture::factory()->for($transfer)->count(5)->create(['size' => 1_000_000]);
    Picture::factory()->for($transfer)->withoutThumbnail()->create(['size' => 500_000]);

    $this->get(route('admin.transfers.index'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('rows.0.pictures_size', 5_500_000)
            ->where('rows.0.thumbnail_urls', $pictures->take(4)->map->thumbnailUrl()->values()->all())
        );
});

it('creates a transfer named by a uuid and answers with its urls', function () {
    $response = $this->postJson(route('admin.transfers.store'), [
        'display_name' => 'Mariage Julie & Tom',
        'expire_at' => null,
    ])->assertCreated();

    $transfer = Party::query()->sole();

    expect($transfer->kind)->toBe(PartyKind::Transfer)
        ->and(Str::isUuid($transfer->name))->toBeTrue()
        ->and($transfer->display_name)->toBe('Mariage Julie & Tom')
        ->and($transfer->user_id)->toBeNull();

    $response->assertExactJson([
        'name' => $transfer->name,
        'public_url' => url('/transfers/'.$transfer->name),
        'upload_url' => route('party.dashboard.pictures.store', $transfer),
    ]);
});

it('stores the optional expiry date of a transfer', function () {
    $this->postJson(route('admin.transfers.store'), [
        'display_name' => 'Gala',
        'expire_at' => '2026-12-31T22:00:00.000Z',
    ])->assertCreated();

    expect(Party::query()->sole()->expire_at->toIso8601String())->toBe('2026-12-31T22:00:00+00:00');
});

it('requires a name to create a transfer', function () {
    $this->postJson(route('admin.transfers.store'), ['display_name' => ''])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('display_name');

    expect(Party::query()->exists())->toBeFalse();
});

it('deletes a transfer', function () {
    Storage::fake('s3');
    $transfer = Party::factory()->transfer()->create();

    $this->delete(route('admin.transfers.destroy', $transfer))
        ->assertRedirect(route('admin.transfers.index'));

    $this->assertModelMissing($transfer);
});

it('refuses to delete an event through the transfers route', function () {
    $event = Party::factory()->create();

    $this->delete(route('admin.transfers.destroy', $event))->assertNotFound();

    $this->assertModelExists($event);
});

it('keeps the transfers screen behind authentication', function () {
    auth()->logout();

    $this->get(route('admin.transfers.index'))->assertRedirect(route('login'));
});
