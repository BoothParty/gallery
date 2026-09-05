<?php

use App\Models\Party;
use App\Services\PosterBatchLink;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\URL;

uses(RefreshDatabase::class);

/**
 * The signed page URL the QR code would carry.
 */
function signedBatchUrl(array $partyIds, ?DateTimeInterface $expiresAt = null): string
{
    return URL::temporarySignedRoute(
        'posters.index',
        $expiresAt ?? now()->addHours(PosterBatchLink::LIFETIME_IN_HOURS),
        ['parties' => implode(',', $partyIds)],
    );
}

it('reads a comma separated list of ids', function () {
    expect(app(PosterBatchLink::class)->parseIds('12,13'))->toBe([12, 13]);
});

it('drops anything that is not a positive integer', function () {
    expect(app(PosterBatchLink::class)->parseIds('12,,abc,-3,0,13'))->toBe([12, 13]);
});

it('reads an empty list from a missing parameter', function () {
    expect(app(PosterBatchLink::class)->parseIds(null))->toBe([]);
});

it('signs every poster with the expiry of the page listing it', function () {
    $party = Party::factory()->create(['name' => 'signed-party']);

    $expiresAt = now()->addHours(6);

    $posters = app(PosterBatchLink::class)->posters([$party->id], $expiresAt);

    expect($posters)->toHaveCount(1)
        ->and($posters[0]['id'])->toBe($party->id)
        ->and($posters[0]['name'])->toBe('signed-party')
        ->and($posters[0]['url'])->toContain('expires='.$expiresAt->getTimestamp());
});

it('keeps the posters in the order of the requested ids', function () {
    $first = Party::factory()->create();
    $second = Party::factory()->create();

    $posters = app(PosterBatchLink::class)->posters([$second->id, $first->id], now()->addHour());

    expect(array_column($posters, 'id'))->toBe([$second->id, $first->id]);
});

it('renders the batch page for a signed link', function () {
    $first = Party::factory()->create(['name' => 'premiere-soiree']);
    $second = Party::factory()->create(['name' => 'seconde-soiree']);

    $this->get(signedBatchUrl([$first->id, $second->id]))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('party/posters')
            ->has('posters', 2)
            ->where('posters.0.name', 'premiere-soiree')
            ->where('posters.1.name', 'seconde-soiree')
        );
});

it('hands out poster urls that the file route accepts', function () {
    $party = Party::factory()->create();

    $response = $this->get(signedBatchUrl([$party->id]));

    $url = $response->viewData('page')['props']['posters'][0]['url'];

    $this->get($url)->assertOk()->assertHeader('Content-Type', 'image/png');
});

it('skips a party deleted since the link was minted', function () {
    $kept = Party::factory()->create();
    $gone = Party::factory()->create();

    $url = signedBatchUrl([$kept->id, $gone->id]);

    $gone->delete();

    $this->get($url)
        ->assertOk()
        ->assertInertia(fn ($page) => $page->component('party/posters')->has('posters', 1));
});

it('refuses an unsigned page link', function () {
    $party = Party::factory()->create();

    $this->get('/affiches?parties='.$party->id)->assertForbidden();
});

it('refuses an expired page link', function () {
    $party = Party::factory()->create();

    $url = signedBatchUrl([$party->id]);

    $this->travel(7)->hours();

    $this->get($url)->assertForbidden();
});
