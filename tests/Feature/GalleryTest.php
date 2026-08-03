<?php

use App\Http\Controllers\PartyController;
use App\Models\Party;
use App\Models\Picture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

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
            ->has('pictures', 3)
        );
});

it('passes picture url in the pictures collection', function () {
    $party = Party::factory()->create();
    Picture::factory()->for($party)->create();

    $this->get(route('party.gallery', $party))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('party/gallery')
            ->has('pictures.0.url')
        );
});

it('passes a null thumbnail url when the thumbnail is not generated yet', function () {
    $party = Party::factory()->create();
    Picture::factory()->for($party)->withoutThumbnail()->create();

    $this->get(route('party.gallery', $party))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pictures.0.thumbnail_url', null)
        );
});

it('passes an empty pictures collection when the party has no pictures', function () {
    $party = Party::factory()->create();

    $this->get(route('party.gallery', $party))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('party/gallery')
            ->has('pictures', 0)
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

// ── Pagination props ──────────────────────────────────────────────────────────

it('passes page and pagesCount props', function () {
    $party = Party::factory()->create();

    $this->get(route('party.gallery', $party))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('page', 1)
            ->where('pagesCount', 1)
        );
});

it('paginates pictures at 30 per page', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(31)->for($party)->create();

    $this->get(route('party.gallery', $party))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pictures', PartyController::PICTURES_PER_PAGE)
            ->where('page', 1)
            ->where('pagesCount', 2)
        );
});

it('returns the correct page when page query param is provided', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(31)->for($party)->create();

    $this->get(route('party.gallery', ['party' => $party, 'page' => 2]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('pictures', 1)
            ->where('page', 2)
            ->where('pagesCount', 2)
        );
});

it('serves only the picture props on a partial reload', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(31)->for($party)->create();

    $this->get(route('party.gallery', $party))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->has('party')
            ->reloadOnly(['pictures', 'page', 'pagesCount'], fn ($reload) => $reload
                ->has('pictures')
                ->has('page')
                ->has('pagesCount')
                ->missing('party')
                ->missing('hidePictures')
            )
        );
});

it('returns pictures in latest-first order', function () {
    $party = Party::factory()->create();
    $oldest = Picture::factory()->for($party)->create(['created_at' => now()->subHour()]);
    $newest = Picture::factory()->for($party)->create(['created_at' => now()]);

    $this->get(route('party.gallery', $party))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->where('pictures.0.id', $newest->id)
            ->where('pictures.1.id', $oldest->id)
        );
});
