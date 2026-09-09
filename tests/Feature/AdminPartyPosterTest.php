<?php

use App\Models\Party;
use App\Models\User;
use App\Services\PartyPosterGenerator;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('downloads the poster as a png named after the party', function () {
    $party = Party::factory()->create(['name' => 'my-party']);

    $response = $this->get(route('admin.parties.poster', $party));

    $response->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Content-Disposition', 'attachment; filename="my-party-affiche.png"');

    [$width, $height] = getimagesizefromstring($response->getContent());

    expect([$width, $height])->toBe([4000, 2828]);
});

it('draws the qr code inside the template card', function () {
    $party = Party::factory()->create(['name' => 'qr-party']);

    $poster = imagecreatefromstring(app(PartyPosterGenerator::class)->generate($party));

    $blackPixels = 0;

    for ($x = 2300; $x < 3300; $x += 25) {
        for ($y = 730; $y < 1730; $y += 25) {
            if (imagecolorat($poster, $x, $y) === 0) {
                $blackPixels++;
            }
        }
    }

    expect($blackPixels)->toBeGreaterThan(100);
});

it('keeps the largest font size for a short address', function () {
    expect(app(PartyPosterGenerator::class)->resolveFontSize('a.fr'))
        ->toBe(PartyPosterGenerator::MAX_FONT_SIZE);
});

it('shrinks the font size for a long address', function () {
    $size = app(PartyPosterGenerator::class)
        ->resolveFontSize('la-soiree-de-fin-dannee-du-service-comptabilite.boothparty.fr');

    expect($size)->toBeLessThan(PartyPosterGenerator::MAX_FONT_SIZE)
        ->and($size)->toBeGreaterThanOrEqual(PartyPosterGenerator::MIN_FONT_SIZE);
});

it('never goes below the minimum font size', function () {
    expect(app(PartyPosterGenerator::class)->resolveFontSize(str_repeat('a', 500).'.boothparty.fr'))
        ->toBe(PartyPosterGenerator::MIN_FONT_SIZE);
});

it('prints the gallery host under the configured party domain', function () {
    config()->set('party.domain', 'example.test');
    config()->set('app.url', 'https://example.test');

    $party = Party::factory()->create(['name' => 'hosted-party']);

    expect($party->publicHost())->toBe('hosted-party.example.test')
        ->and($party->publicUrl())->toBe('https://hosted-party.example.test');
});
