<?php

use App\Services\SignedStorageUrl;

/**
 * Les photos sont chargées en direct depuis le stockage, qui n'accorde plus
 * rien anonymement : chaque URL porte une signature à durée de vie limitée.
 */
it('signs the url against the endpoint the browser can reach', function () {
    config(['filesystems.disks.s3-public.endpoint' => 'https://s3.example.test']);

    $url = SignedStorageUrl::for('pictures/1/photo.jpg');

    expect($url)->toStartWith('https://s3.example.test/')
        ->and($url)->toContain('pictures/1/photo.jpg')
        ->and($url)->toContain('X-Amz-Signature=');
});

/**
 * L'heure de signature doit être arrondie, pas seulement l'échéance : un
 * X-Amz-Date à la seconde près produirait une URL différente à chaque rendu.
 */
it('signs from the start of the day rather than from the current second', function () {
    expect(SignedStorageUrl::for('pictures/1/photo.jpg'))->toContain('X-Amz-Date=')
        ->and(SignedStorageUrl::for('pictures/1/photo.jpg'))
        ->toContain(today()->format('Ymd').'T000000Z');
});

it('keeps the same url across renders so the browser cache still applies', function () {
    $this->travelTo(today()->addHours(8));

    $first = SignedStorageUrl::for('pictures/1/photo.jpg');

    $this->travel(4)->hours();

    expect(SignedStorageUrl::for('pictures/1/photo.jpg'))->toBe($first);
});

it('rolls the url over to a fresh signature the next day', function () {
    $this->travelTo(today()->addHours(8));

    $today = SignedStorageUrl::for('pictures/1/photo.jpg');

    $this->travel(1)->day();

    expect(SignedStorageUrl::for('pictures/1/photo.jpg'))->not->toBe($today);
});

it('expires the url once the window has passed', function () {
    $url = SignedStorageUrl::for('pictures/1/photo.jpg');

    parse_str(parse_url($url, PHP_URL_QUERY), $query);

    $signedFor = now()->createFromFormat('Ymd\THis\Z', $query['X-Amz-Date'])
        ->addSeconds((int) $query['X-Amz-Expires']);

    expect($signedFor->diffInDays(now()))
        ->toBeLessThanOrEqual(SignedStorageUrl::TTL_DAYS + 1);
});
