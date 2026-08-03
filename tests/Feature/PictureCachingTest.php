<?php

use Illuminate\Support\Facades\Storage;

/**
 * Pictures and thumbnails live under an immutable UUID path and are never
 * rewritten, so they must be served with a long-lived Cache-Control header.
 * Without it, every pagination step re-downloads the whole grid.
 */
const EXPECTED_PICTURE_CACHE_CONTROL = 'public, max-age=31536000, immutable';

it('configures a long-lived cache-control on the s3 disk', function () {
    expect(config('filesystems.disks.s3.options.CacheControl'))
        ->toBe(EXPECTED_PICTURE_CACHE_CONTROL);
});

it('forwards the cache-control option to the s3 adapter used for writes', function () {
    $adapter = Storage::disk('s3')->getAdapter();

    $options = (new ReflectionProperty($adapter, 'options'))->getValue($adapter);

    expect($options)->toHaveKey('CacheControl')
        ->and($options['CacheControl'])->toBe(EXPECTED_PICTURE_CACHE_CONTROL);
});
