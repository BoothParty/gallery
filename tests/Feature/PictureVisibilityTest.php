<?php

use App\Models\Party;
use App\Models\Picture;
use App\Services\PartyPicturesState;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('s3');
});

/**
 * @return array<string, array<int, bool>>
 */
function hostSession(Party $party): array
{
    return ['party_host' => [$party->id => true]];
}

// ── Toggling ────────────────────────────────────────────────────────────────

it('lets the host take a picture off the guests gallery', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create();

    $this->withSession(hostSession($party))
        ->patch(route('party.pictures.visibility', [$party, $picture]), ['hidden' => true])
        ->assertRedirect();

    expect($picture->fresh()->isHidden())->toBeTrue();
});

it('lets the host put a hidden picture back', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->hidden()->create();

    $this->withSession(hostSession($party))
        ->patch(route('party.pictures.visibility', [$party, $picture]), ['hidden' => false])
        ->assertRedirect();

    expect($picture->fresh()->isHidden())->toBeFalse();
});

it('applies the asked state rather than flipping the stored one', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->hidden()->create();

    $this->withSession(hostSession($party))
        ->patch(route('party.pictures.visibility', [$party, $picture]), ['hidden' => true])
        ->assertRedirect();

    expect($picture->fresh()->isHidden())->toBeTrue();
});

it('refuses a request that does not say which state it wants', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->hidden()->create();

    $this->withSession(hostSession($party))
        ->patch(route('party.pictures.visibility', [$party, $picture]), [])
        ->assertSessionHasErrors('hidden');

    expect($picture->fresh()->isHidden())->toBeTrue();
});

it('refuses to mask a picture for a plain visitor', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create();

    $this->patch(route('party.pictures.visibility', [$party, $picture]), ['hidden' => true])
        ->assertForbidden();

    expect($picture->fresh()->isHidden())->toBeFalse();
});

it('does not let the host of one party mask a picture of another', function () {
    $mine = Party::factory()->create();
    $other = Party::factory()->create();
    $picture = Picture::factory()->for($other)->create();

    $this->withSession(hostSession($mine))
        ->patch(route('party.pictures.visibility', [$other, $picture]), ['hidden' => true])
        ->assertForbidden();

    expect($picture->fresh()->isHidden())->toBeFalse();
});

it('does not mask a picture belonging to another party', function () {
    $party = Party::factory()->create();
    $foreign = Picture::factory()->for(Party::factory())->create();

    $this->withSession(hostSession($party))
        ->patch(route('party.pictures.visibility', [$party, $foreign]), ['hidden' => true])
        ->assertNotFound();
});

// ── The gallery ─────────────────────────────────────────────────────────────

it('leaves a hidden picture out of a guests gallery', function () {
    $party = Party::factory()->create();
    Picture::factory()->for($party)->create();
    Picture::factory()->for($party)->hidden()->create();

    $this->get(route('party.gallery', $party))
        ->assertInertia(fn ($page) => $page
            ->where('picturesCount', 1)
            ->count('pictures.data', 1)
        );
});

it('keeps a hidden picture in the hosts own gallery, marked as hidden', function () {
    $party = Party::factory()->create();
    Picture::factory()->for($party)->create(['created_at' => now()->subMinute()]);
    Picture::factory()->for($party)->hidden()->create(['created_at' => now()]);

    $this->withSession(hostSession($party))
        ->get(route('party.gallery', $party))
        ->assertInertia(fn ($page) => $page
            ->where('picturesCount', 2)
            ->count('pictures.data', 2)
            ->where('pictures.data.0.hidden', true)
            ->where('pictures.data.1.hidden', false)
        );
});

it('moves the polled state as soon as the host masks a picture', function () {
    $party = Party::factory()->create();
    $pictures = Picture::factory()->count(3)->for($party)->create();
    $partyPicturesState = app(PartyPicturesState::class);

    expect($partyPicturesState->for($party)['count'])->toBe(3);

    $this->withSession(hostSession($party))
        ->patch(route('party.pictures.visibility', [$party, $pictures->first()]), ['hidden' => true])
        ->assertRedirect();

    expect($partyPicturesState->for($party)['count'])->toBe(2);
});

it('leaves hidden pictures out of the polled state', function () {
    $party = Party::factory()->create();
    Picture::factory()->count(2)->for($party)->create();
    Picture::factory()->for($party)->hidden()->create();

    expect(app(PartyPicturesState::class)->for($party)['count'])->toBe(2);
});

// ── Reaching the file anyway ────────────────────────────────────────────────

it('refuses to stream a hidden picture to a guest', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->hidden()->create();
    Storage::disk('s3')->put($picture->path, 'fake-jpeg-bytes');

    $this->get(route('party.pictures.show', [$party, $picture]))
        ->assertForbidden();
});

it('still streams a hidden picture to its host', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->hidden()->create();
    Storage::disk('s3')->put($picture->path, 'fake-jpeg-bytes');

    $this->withSession(hostSession($party))
        ->get(route('party.pictures.show', [$party, $picture]))
        ->assertOk();
});

it('refuses to put a hidden picture in a guests zip', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->hidden()->create();
    Storage::disk('s3')->put($picture->path, 'fake-jpeg-bytes');

    $this->post(route('party.download', $party), ['ids' => [$picture->id]])
        ->assertSessionHasErrors('ids.0');
});

it('still puts a hidden picture in its hosts zip', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->hidden()->create();
    Storage::disk('s3')->put($picture->path, 'fake-jpeg-bytes');

    $this->withSession(hostSession($party))
        ->post(route('party.download', $party), ['ids' => [$picture->id]])
        ->assertOk()
        ->assertHeader('content-type', 'application/zip');
});
