<?php

use App\Models\Party;
use App\Models\Picture;
use App\Services\PartyPicturesState;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
});

function picturesState(Party $party): array
{
    return app(PartyPicturesState::class)->for($party);
}

it('reports an empty gallery as zero without a last change', function () {
    $party = Party::factory()->create();

    expect(picturesState($party))->toBe(['count' => 0, 'last_change' => null]);
});

it('counts the pictures and reports the most recent change', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(3)->for($party)->create();
    $latest = Picture::factory()->for($party)->create(['updated_at' => now()->addMinute()]);

    $state = picturesState($party);

    expect($state['count'])->toBe(4)
        ->and($state['last_change'])->toBe($latest->updated_at->toIso8601String());
});

it('ignores the pictures of other parties', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(2)->for($party)->create();
    Picture::factory()->count(5)->for(Party::factory()->create())->create();

    expect(picturesState($party)['count'])->toBe(2);
});

it('drops the count when a picture is deleted', function () {
    $party = Party::factory()->create();
    $pictures = Picture::factory()->count(3)->for($party)->create();

    expect(picturesState($party)['count'])->toBe(3);

    $pictures->first()->delete();

    expect(picturesState($party)['count'])->toBe(2);
});

it('moves the last change when a thumbnail is written, without touching the count', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create(['thumbnail_path' => null]);
    $before = picturesState($party);

    $this->travel(1)->minutes();
    $picture->update(['thumbnail_path' => 'thumbnails/x.jpg']);

    $after = picturesState($party);

    expect($after['count'])->toBe($before['count'])
        ->and($after['last_change'])->not->toBe($before['last_change']);
});

it('reports zeros for an expired party even when it holds pictures', function () {
    $party = Party::factory()->expired()->create();
    Picture::factory()->count(4)->for($party)->create();

    expect(picturesState($party))->toBe(['count' => 0, 'last_change' => null]);
});

it('serves the aggregate from cache between writes', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(2)->for($party)->create();

    picturesState($party);

    /*
     * `withoutEvents` also silences the `creating` hook that fills `path`, so
     * the write has to carry its own.
     */
    Picture::withoutEvents(fn () => Picture::factory()->for($party)->create(['path' => 'pictures/bypass.jpg']));

    expect(picturesState($party)['count'])->toBe(2);
});

it('invalidates the cache when a picture is created', function () {
    $party = Party::factory()->create();
    picturesState($party);

    Picture::factory()->for($party)->create();

    expect(picturesState($party)['count'])->toBe(1);
});

it('invalidates the cache when the dashboard deletes pictures in bulk', function () {
    $party = Party::factory()->create();
    $pictures = Picture::factory()->count(3)->for($party)->create();
    picturesState($party);

    $party->pictures()->whereIn('id', $pictures->take(2)->pluck('id'))->get()->each->delete();

    expect(picturesState($party)['count'])->toBe(1);
});
