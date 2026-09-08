<?php

use App\Models\Party;
use App\Models\Picture;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('s3');
});

it('streams the original bytes of a picture', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create();
    Storage::disk('s3')->put($picture->path, 'fake-jpeg-bytes');

    $response = $this->get(route('party.pictures.show', [$party, $picture]));

    $response->assertOk()
        ->assertHeader('content-type', 'image/jpeg');

    expect($response->streamedContent())->toBe('fake-jpeg-bytes');
});

/**
 * Symfony reorders the Cache-Control directives, so the assertion compares the
 * set of directives rather than the literal header value.
 */
it('lets browsers cache a picture forever', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create();
    Storage::disk('s3')->put($picture->path, 'fake-jpeg-bytes');

    $response = $this->get(route('party.pictures.show', [$party, $picture]))->assertOk();

    $directives = explode(', ', (string) $response->headers->get('cache-control'));
    $expected = explode(', ', (string) config('filesystems.disks.s3.options.CacheControl'));

    sort($directives);
    sort($expected);

    expect($directives)->toBe($expected);
});

it('does not serve a picture belonging to another party', function () {
    $party = Party::factory()->create();
    $otherParty = Party::factory()->create();
    $foreignPicture = Picture::factory()->for($otherParty)->create();
    Storage::disk('s3')->put($foreignPicture->path, 'fake-jpeg-bytes');

    $this->get(route('party.pictures.show', [$party, $foreignPicture]))
        ->assertNotFound();
});

it('does not serve a picture whose file is missing from storage', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create();

    $this->get(route('party.pictures.show', [$party, $picture]))
        ->assertNotFound();
});

it('refuses to serve a picture of an expired party', function () {
    $party = Party::factory()->expired()->create();
    $picture = Picture::factory()->for($party)->create();
    Storage::disk('s3')->put($picture->path, 'fake-jpeg-bytes');

    $this->get(route('party.pictures.show', [$party, $picture]))
        ->assertForbidden();
});

it('throttles repeated picture requests', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create();
    Storage::disk('s3')->put($picture->path, 'fake-jpeg-bytes');

    foreach (range(1, 240) as $ignored) {
        $this->get(route('party.pictures.show', [$party, $picture]))->assertOk();
    }

    $this->get(route('party.pictures.show', [$party, $picture]))
        ->assertStatus(429);
});

it('keeps picture throttling independent per party', function () {
    $throttledParty = Party::factory()->create();
    $throttledPicture = Picture::factory()->for($throttledParty)->create();
    Storage::disk('s3')->put($throttledPicture->path, 'fake-jpeg-bytes');

    foreach (range(1, 240) as $ignored) {
        $this->get(route('party.pictures.show', [$throttledParty, $throttledPicture]))->assertOk();
    }

    $this->get(route('party.pictures.show', [$throttledParty, $throttledPicture]))
        ->assertStatus(429);

    $otherParty = Party::factory()->create();
    $otherPicture = Picture::factory()->for($otherParty)->create();
    Storage::disk('s3')->put($otherPicture->path, 'fake-jpeg-bytes');

    $this->get(route('party.pictures.show', [$otherParty, $otherPicture]))
        ->assertOk();
});
