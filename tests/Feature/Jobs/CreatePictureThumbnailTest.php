<?php

use App\Jobs\CreatePictureThumbnail;
use App\Models\Party;
use App\Models\Picture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

uses(RefreshDatabase::class);

it('creates a thumbnail for a given picture', function () {
    Storage::fake('s3');

    // Generate a fake image of 1600x1200
    $file = UploadedFile::fake()->image('test.jpg', 1600, 1200);
    $path = Storage::disk('s3')->putFile('pictures/test', $file);

    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create([
        'path' => $path,
        'thumbnail_path' => null,
    ]);

    (new CreatePictureThumbnail($picture))->handle();

    $picture->refresh();

    expect($picture->thumbnail_path)->not->toBeNull()
        ->and($picture->thumbnail_path)->toEndWith('_thumb.jpg');

    Storage::disk('s3')->assertExists($picture->thumbnail_path);

    $image = (new ImageManager(new Driver))->read(
        Storage::disk('s3')->get($picture->thumbnail_path)
    );

    expect($image->width())->toBe(480)
        ->and($image->height())->toBe(360); // 1600x1200 scaled down to a 480px max edge
});

it('never scales a thumbnail beyond the configured max edge', function () {
    Storage::fake('s3');

    $file = UploadedFile::fake()->image('portrait.jpg', 900, 3000);
    $path = Storage::disk('s3')->putFile('pictures/test', $file);

    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create([
        'path' => $path,
        'thumbnail_path' => null,
    ]);

    (new CreatePictureThumbnail($picture))->handle();

    $image = (new ImageManager(new Driver))->read(
        Storage::disk('s3')->get($picture->refresh()->thumbnail_path)
    );

    expect(max($image->width(), $image->height()))->toBe(CreatePictureThumbnail::MAX_EDGE);
});

it('does not upscale a picture smaller than the max edge', function () {
    Storage::fake('s3');

    $file = UploadedFile::fake()->image('small.jpg', 200, 150);
    $path = Storage::disk('s3')->putFile('pictures/test', $file);

    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create([
        'path' => $path,
        'thumbnail_path' => null,
    ]);

    (new CreatePictureThumbnail($picture))->handle();

    $image = (new ImageManager(new Driver))->read(
        Storage::disk('s3')->get($picture->refresh()->thumbnail_path)
    );

    expect($image->width())->toBe(200)
        ->and($image->height())->toBe(150);
});

it('does nothing if the original picture file does not exist on disk', function () {
    Storage::fake('s3');

    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create([
        'path' => 'pictures/missing_file.jpg',
        'thumbnail_path' => null,
    ]);

    $job = new CreatePictureThumbnail($picture);
    $job->handle();

    $picture->refresh();

    expect($picture->thumbnail_path)->toBeNull();
});
