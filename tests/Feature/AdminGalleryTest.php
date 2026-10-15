<?php

use App\Jobs\CreatePictureThumbnail;
use App\Models\Party;
use App\Models\Picture;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

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
    Picture::factory()->for($party)->count(3)->create([
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
    $pictures = Picture::factory()->for($party)->count(3)->create();
    $otherPicture = Picture::factory()->create();

    $ids = $pictures->pluck('id')->all();

    $this->delete(route('party.dashboard.pictures', $party), ['ids' => $ids])
        ->assertRedirect(route('party.dashboard.pictures', $party));

    expect(Picture::query()->whereIn('id', $ids)->exists())->toBeFalse();
    $this->assertModelExists($otherPicture);
});

it('does not delete pictures belonging to another party', function () {
    Storage::fake('s3');

    $party = Party::factory()->create();
    $otherParty = Party::factory()->create();
    $otherPicture = Picture::factory()->for($otherParty)->create();

    // IDs from another party are silently ignored by the query scope
    $this->delete(route('party.dashboard.pictures', $party), ['ids' => [$otherPicture->id]])
        ->assertRedirect(route('party.dashboard.pictures', $party));

    $this->assertModelExists($otherPicture);
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

it('serves only the picture props on a partial reload', function () {
    $party = Party::factory()->create();
    Picture::factory()->for($party)->count(30)->create();

    $this->get(route('party.dashboard.pictures', $party))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('parties')
            ->reloadOnly(['pictures', 'perPage'], fn ($reload) => $reload
                ->has('pictures')
                ->has('perPage')
                ->missing('parties')
                ->missing('searchableParties')
                ->missing('party')
            )
        );
});

it('supports allowed per_page values and falls back to default for invalid values', function () {
    $party = Party::factory()->create();
    Picture::factory()->for($party)->count(30)->create();

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

it('records the relative path of a picture sent with paths and answers in json', function () {
    Storage::fake('s3');
    Queue::fake();
    $party = Party::factory()->transfer()->create();

    $photo = UploadedFile::fake()->image('IMG_0042.jpg', 1200, 800);

    $this->postJson(route('party.dashboard.pictures.store', $party), [
        'photos' => [$photo],
        'paths' => ['raw/IMG_0042.jpg'],
    ])->assertOk()->assertExactJson(['created' => 1, 'skipped' => 0]);

    expect($party->pictures()->sole())
        ->original_name->toBe('raw/IMG_0042.jpg')
        ->size->toBe($photo->getSize());
    Queue::assertPushed(CreatePictureThumbnail::class, 1);
});

it('skips a picture already sent under the same path without touching storage', function () {
    Storage::fake('s3');
    Queue::fake();
    $party = Party::factory()->transfer()->create();
    Picture::factory()->for($party)->create(['original_name' => 'IMG_0042.jpg']);

    $this->postJson(route('party.dashboard.pictures.store', $party), [
        'photos' => [UploadedFile::fake()->image('IMG_0042.jpg')],
        'paths' => ['IMG_0042.jpg'],
    ])->assertOk()->assertExactJson(['created' => 0, 'skipped' => 1]);

    expect($party->pictures()->count())->toBe(1);
    expect(Storage::disk('s3')->allFiles())->toBe([]);
    Queue::assertNothingPushed();
    expect($party->activities()->exists())->toBeFalse();
});

it('accepts the same path in two different parties', function () {
    Storage::fake('s3');
    Queue::fake();
    $other = Party::factory()->transfer()->create();
    Picture::factory()->for($other)->create(['original_name' => 'IMG_0042.jpg']);
    $party = Party::factory()->transfer()->create();

    $this->postJson(route('party.dashboard.pictures.store', $party), [
        'photos' => [UploadedFile::fake()->image('IMG_0042.jpg')],
        'paths' => ['IMG_0042.jpg'],
    ])->assertOk()->assertExactJson(['created' => 1, 'skipped' => 0]);
});

it('stores homonymous pictures sent from the dashboard form without paths', function () {
    Storage::fake('s3');
    Queue::fake();
    $party = Party::factory()->create();

    foreach ([1, 2] as $attempt) {
        $this->post(route('party.dashboard.pictures.store', $party), [
            'photos' => [UploadedFile::fake()->image('IMG_1234.jpg')],
        ])->assertRedirect(route('party.dashboard.pictures', $party));
    }

    expect($party->pictures()->count())->toBe(2)
        ->and($party->pictures()->whereNull('original_name')->count())->toBe(2);
});

it('rejects a path longer than 255 characters', function () {
    $party = Party::factory()->transfer()->create();

    $this->postJson(route('party.dashboard.pictures.store', $party), [
        'photos' => [UploadedFile::fake()->image('a.jpg')],
        'paths' => [str_repeat('a', 256).'.jpg'],
    ])->assertUnprocessable()->assertJsonValidationErrors('paths.0');
});
