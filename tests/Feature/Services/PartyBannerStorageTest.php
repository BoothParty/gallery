<?php

use App\Models\Party;
use App\Services\PartyBannerStorage;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
    $this->storage = new PartyBannerStorage;
});

it('stores the banner under the party and records its path', function () {
    $party = Party::factory()->create();

    $this->storage->store($party, UploadedFile::fake()->image('cover.jpg', 1200, 600));

    $party->refresh();

    expect($party->banner_path)->toStartWith("banners/{$party->id}/")
        ->and($party->banner_path)->toEndWith('.jpg');

    Storage::disk('s3')->assertExists($party->banner_path);
});

it('scales a banner down to the max width', function () {
    $party = Party::factory()->create();

    $this->storage->store($party, UploadedFile::fake()->image('cover.jpg', 4000, 2000));

    $image = (new ImageManager(new Driver))->read(
        Storage::disk('s3')->get($party->refresh()->banner_path)
    );

    expect($image->width())->toBe(PartyBannerStorage::MAX_WIDTH)
        ->and($image->height())->toBe(PartyBannerStorage::MAX_WIDTH / 2);
});

it('does not upscale a banner narrower than the max width', function () {
    $party = Party::factory()->create();

    $this->storage->store($party, UploadedFile::fake()->image('cover.jpg', 900, 450));

    $image = (new ImageManager(new Driver))->read(
        Storage::disk('s3')->get($party->refresh()->banner_path)
    );

    expect($image->width())->toBe(900);
});

it('deletes the previous file when the banner is replaced', function () {
    $party = Party::factory()->create();

    $this->storage->store($party, UploadedFile::fake()->image('first.jpg'));
    $firstPath = $party->refresh()->banner_path;

    $this->storage->store($party, UploadedFile::fake()->image('second.jpg'));
    $secondPath = $party->refresh()->banner_path;

    expect($secondPath)->not->toBe($firstPath);

    Storage::disk('s3')->assertMissing($firstPath);
    Storage::disk('s3')->assertExists($secondPath);
});

it('removes the banner file and clears the path', function () {
    $party = Party::factory()->create();
    $this->storage->store($party, UploadedFile::fake()->image('cover.jpg'));
    $path = $party->refresh()->banner_path;

    $this->storage->remove($party);

    expect($party->refresh()->banner_path)->toBeNull();

    Storage::disk('s3')->assertMissing($path);
});

it('removing a banner from a party without one does nothing', function () {
    $party = Party::factory()->create();

    $this->storage->remove($party);

    expect($party->refresh()->banner_path)->toBeNull();
});
