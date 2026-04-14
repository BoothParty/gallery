<?php

use App\Jobs\CreatePictureThumbnail;
use App\Models\Party;
use App\Models\Picture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

it('renders administration gallery for a party', function () {
    $party = Party::factory()->create(['name' => 'My Party']);

    $this->get(route('party.dashboard.pictures', $party))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/gallery/index')
            ->where('party.id', $party->id)
            ->where('party.name', 'My Party')
            ->has('pictures.data')
            ->where('pictures.total', 0)
        );
});

it('renders gallery pictures for a party with count and pagination', function () {
    Storage::fake('s3');

    $party = Party::factory()->create();
    Picture::factory()->forParty($party)->count(3)->create([
        'width' => 1920,
        'height' => 1080,
    ]);

    $this->get(route('party.dashboard.pictures', $party))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/gallery/index')
            ->where('pictures.total', 3)
            ->has('pictures.data', 3)
        );
});

it('bulk deletes pictures from a party gallery', function () {
    Storage::fake('s3');

    $party = Party::factory()->create();
    $pictures = Picture::factory()->forParty($party)->count(3)->create();
    $otherPicture = Picture::factory()->create();

    $ids = $pictures->pluck('id')->all();

    $this->delete(route('party.dashboard.pictures', $party), ['ids' => $ids])
        ->assertRedirect(route('party.dashboard.pictures', $party));

    expect(Picture::query()->whereIn('id', $ids)->exists())->toBeFalse();
    expect(Picture::query()->whereKey($otherPicture->id)->exists())->toBeTrue();
});

it('does not delete pictures belonging to another party', function () {
    Storage::fake('s3');

    $party = Party::factory()->create();
    $otherParty = Party::factory()->create();
    $otherPicture = Picture::factory()->forParty($otherParty)->create();

    // IDs from another party are silently ignored by the query scope
    $this->delete(route('party.dashboard.pictures', $party), ['ids' => [$otherPicture->id]])
        ->assertRedirect(route('party.dashboard.pictures', $party));

    expect(Picture::query()->whereKey($otherPicture->id)->exists())->toBeTrue();
});

it('returns 404 for a non-existent party gallery', function () {
    $this->get('/unknown-party/dashboard/pictures')
        ->assertNotFound();
});

it('stores uploaded pictures from administration gallery and dispatches thumbnails', function () {
    Storage::fake('s3');
    Queue::fake();

    $party = Party::factory()->create();

    $this->post(route('party.dashboard.pictures.store', $party), [
        'photos' => [
            UploadedFile::fake()->image('first.jpg', 1200, 800),
            UploadedFile::fake()->image('second.jpg', 1600, 900),
        ],
    ])->assertRedirect(route('party.dashboard.pictures', $party));

    expect(Picture::query()->where('party_id', $party->id)->count())->toBe(2);
    expect(Picture::query()->where('party_id', $party->id)->where('width', 1200)->where('height', 800)->exists())->toBeTrue();
    expect(Picture::query()->where('party_id', $party->id)->where('width', 1600)->where('height', 900)->exists())->toBeTrue();

    Queue::assertPushed(CreatePictureThumbnail::class, 2);
});

it('validates uploaded pictures from administration gallery', function () {
    $party = Party::factory()->create();

    $this->post(route('party.dashboard.pictures.store', $party), [])
        ->assertSessionHasErrors('photos');

    $this->post(route('party.dashboard.pictures.store', $party), [
        'photos' => [UploadedFile::fake()->create('document.pdf', 100, 'application/pdf')],
    ])->assertSessionHasErrors('photos.0');
});

it('supports allowed per_page values and falls back to default for invalid values', function () {
    $party = Party::factory()->create();
    Picture::factory()->forParty($party)->count(30)->create();

    $this->get(route('party.dashboard.pictures', ['party' => $party, 'per_page' => 48]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('perPage', 48)
            ->where('pictures.per_page', 48)
            ->has('pictures.data', 30)
        );

    $this->get(route('party.dashboard.pictures', ['party' => $party, 'per_page' => 25]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('perPage', 24)
            ->where('pictures.per_page', 24)
            ->has('pictures.data', 24)
        );
});
