<?php

use App\Http\Controllers\PartyController;
use App\Models\Party;
use App\Models\Picture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
});

// ── Route model binding ───────────────────────────────────────────────────────

it('returns 404 for a non-existent party', function () {
    $this->get('/unknown-party')->assertNotFound();
});

// ── Inertia rendering ─────────────────────────────────────────────────────────

it('renders the gallery page with party data', function () {
    $party = Party::factory()->create(['name' => 'Test Party']);

    $this->get(route('party.gallery', $party))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('party/gallery')
            ->has('party')
            ->where('party.id', $party->id)
            ->where('party.name', 'Test Party')
        );
});

it('passes the pictures collection to the gallery page', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(3)->for($party)->create();

    $this->get(route('party.gallery', $party))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('party/gallery')
            ->has('pictures.data', 3)
        );
});

it('passes picture url in the pictures collection', function () {
    $party = Party::factory()->create();
    Picture::factory()->for($party)->create();

    $this->get(route('party.gallery', $party))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('party/gallery')
            ->has('pictures.data.0.url')
        );
});

it('passes a null thumbnail url when the thumbnail is not generated yet', function () {
    $party = Party::factory()->create();
    Picture::factory()->for($party)->withoutThumbnail()->create();

    $this->get(route('party.gallery', $party))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pictures.data.0.thumbnail_url', null)
        );
});

it('passes the total picture count regardless of the loaded batch size', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(30)->for($party)->create();

    $this->get(route('party.gallery', $party))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pictures.data', PartyController::PICTURES_PER_PAGE)
            ->where('picturesCount', 30)
        );
});

it('passes a zero picture count for an expired party', function () {
    $party = Party::factory()->expired()->create();
    Picture::factory()->count(3)->for($party)->create();

    $this->get(route('party.gallery', $party))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('picturesCount', 0)
        );
});

it('passes an empty pictures collection when the party has no pictures', function () {
    $party = Party::factory()->create();

    $this->get(route('party.gallery', $party))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('party/gallery')
            ->has('pictures.data', 0)
        );
});

// ── hidePictures prop ─────────────────────────────────────────────────────────

it('passes hidePictures as false for a non-expired party', function () {
    $party = Party::factory()->expiresAt(now()->addDay())->create();

    $this->get(route('party.gallery', $party))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('hidePictures', false)
        );
});

it('passes hidePictures as true for an expired party', function () {
    $party = Party::factory()->expired()->create();

    $this->get(route('party.gallery', $party))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('hidePictures', true)
        );
});

it('passes hidePictures as false when expire_at is null', function () {
    $party = Party::factory()->create(['expire_at' => null]);

    $this->get(route('party.gallery', $party))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('hidePictures', false)
        );
});

// ── Infinite scroll props ─────────────────────────────────────────────────────

it('paginates pictures at 24 per page', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(25)->for($party)->create();

    $this->get(route('party.gallery', $party))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pictures.data', PartyController::PICTURES_PER_PAGE)
        );
});

it('returns the correct page when page query param is provided', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(25)->for($party)->create();

    $this->get(route('party.gallery', ['party' => $party, 'page' => 2]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pictures.data', 1)
        );
});

it('advertises the pictures prop as a scroll prop', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(25)->for($party)->create();

    $this->get(route('party.gallery', $party));

    $payload = $this->withHeaders(['X-Inertia' => 'true', 'X-Inertia-Version' => Inertia::getVersion()])
        ->get(route('party.gallery', $party))
        ->assertOk()
        ->json();

    expect($payload['scrollProps']['pictures']['nextPage'])->toBe(2)
        ->and($payload['scrollProps']['pictures']['currentPage'])->toBe(1)
        ->and($payload['mergeProps'])->toContain('pictures.data');
});

it('returns an empty pictures collection for an expired party', function () {
    $party = Party::factory()->expired()->create();
    Picture::factory()->count(3)->for($party)->create();

    $this->get(route('party.gallery', $party))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pictures.data', 0)
            ->where('hidePictures', true)
        );
});

it('returns pictures in latest-first order', function () {
    $party = Party::factory()->create();
    $oldest = Picture::factory()->for($party)->create(['created_at' => now()->subHour()]);
    $newest = Picture::factory()->for($party)->create(['created_at' => now()]);

    $this->get(route('party.gallery', $party))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pictures.data.0.id', $newest->id)
            ->where('pictures.data.1.id', $oldest->id)
        );
});

// ── Download props ────────────────────────────────────────────────────────────

it('passes the download cap to the gallery page', function () {
    config()->set('party.download.max_pictures', 42);

    $party = Party::factory()->create();

    $this->get(route('party.gallery', $party))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('maxDownloadPictures', 42)
            ->has('csrfToken')
        );
});

// ── Event metadata ───────────────────────────────────────────────────────────

it('exposes the event metadata to the gallery page', function () {
    $party = Party::factory()->create([
        'name' => 'metadata-party',
        'display_name' => 'Camille & Antoine',
        'description' => 'Samedi 12 septembre · Domaine des Ormes',
    ]);
    $party->forceFill(['banner_path' => 'banners/cover.jpg'])->save();

    $this->get(route('party.gallery', $party))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('party.display_name', 'Camille & Antoine')
            ->where('party.description', 'Samedi 12 septembre · Domaine des Ormes')
            ->where('party.banner_url', fn (string $url) => str_contains($url, 'banners/cover.jpg')
                && str_contains($url, 'X-Amz-Signature='))
        );
});

it('exposes a null banner url when the party has no banner', function () {
    $party = Party::factory()->create();

    $this->get(route('party.gallery', $party))
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('party.banner_url', null));
});

it('passes the banner framing to the gallery page', function () {
    $party = Party::factory()->create([
        'banner_position_x' => 30,
        'banner_position_y' => 70,
        'banner_zoom' => 1.5,
    ]);

    $this->get(route('party.gallery', $party))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('party.banner_position_x', 30)
            ->where('party.banner_position_y', 70)
            ->where('party.banner_zoom', 1.5)
        );
});

// ── Polling props ─────────────────────────────────────────────────────────────

it('passes the pictures state as the polling baseline', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create();

    $this->get(route('party.gallery', $party))
        ->assertInertia(fn ($page) => $page
            ->where('picturesState.count', 1)
            ->where('picturesState.last_change', $picture->updated_at->toIso8601String())
        );
});

it('passes a zeroed pictures state for an expired party', function () {
    $party = Party::factory()->expired()->create();
    Picture::factory()->count(2)->for($party)->create();

    $this->get(route('party.gallery', $party))
        ->assertInertia(fn ($page) => $page
            ->where('picturesState.count', 0)
            ->where('picturesState.last_change', null)
        );
});

it('passes the refresh cadence to the page', function () {
    $party = Party::factory()->create();

    $this->get(route('party.gallery', $party))
        ->assertInertia(fn ($page) => $page
            ->where('refresh.hot', 10)
            ->where('refresh.warm', 30)
            ->where('refresh.cold', 60)
            ->where('refresh.warm_after', 120)
            ->where('refresh.cold_after', 300)
        );
});
