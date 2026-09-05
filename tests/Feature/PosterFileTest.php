<?php

use App\Models\Party;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

/**
 * The signed URL a batch page would hand to the phone.
 */
function signedPosterUrl(int $partyId, ?DateTimeInterface $expiresAt = null): string
{
    return URL::temporarySignedRoute(
        'posters.file',
        $expiresAt ?? now()->addHours(6),
        ['party' => $partyId],
    );
}

it('serves the poster of a party to whoever holds a signed link', function () {
    $party = Party::factory()->create(['name' => 'my-party']);

    $response = $this->get(signedPosterUrl($party->id));

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Content-Disposition', 'inline; filename="my-party-affiche.png"');

    [$width, $height] = getimagesizefromstring($response->getContent());

    expect([$width, $height])->toBe([4000, 2828]);
});

it('refuses an unsigned link', function () {
    $party = Party::factory()->create();

    $this->get('/affiches/'.$party->id.'/png')->assertForbidden();
});

it('refuses a link tampered with to point at another party', function () {
    $party = Party::factory()->create();
    $other = Party::factory()->create();

    $url = str_replace(
        '/affiches/'.$party->id.'/',
        '/affiches/'.$other->id.'/',
        signedPosterUrl($party->id),
    );

    $this->get($url)->assertForbidden();
});

it('refuses an expired link', function () {
    $party = Party::factory()->create();

    $url = signedPosterUrl($party->id, now()->addHours(6));

    $this->travel(7)->hours();

    $this->get($url)->assertForbidden();
});

it('answers not found for a party deleted since the link was minted', function () {
    $party = Party::factory()->create();

    $url = signedPosterUrl($party->id);

    $party->delete();

    $this->get($url)->assertNotFound();
});

/**
 * A poster costs almost a second of CPU and 54 MB, so the limiter is asserted
 * on the route rather than by hammering it.
 */
it('rate limits the poster route', function () {
    expect(app('router')->getRoutes()->getByName('posters.file')->gatherMiddleware())
        ->toContain('throttle:poster-file');
});
