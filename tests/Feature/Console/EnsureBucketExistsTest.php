<?php

use Aws\S3\S3Client;
use Illuminate\Filesystem\AwsS3V3Adapter;
use Illuminate\Support\Facades\Storage;

function fakeS3Client(S3Client $client): void
{
    $disk = Mockery::mock(AwsS3V3Adapter::class);
    $disk->shouldReceive('getClient')->andReturn($client);

    Storage::shouldReceive('disk')->with('s3')->andReturn($disk);
}

beforeEach(function () {
    config(['filesystems.disks.s3.bucket' => 'boothparty']);
});

it('creates the bucket when it is missing', function () {
    $client = Mockery::mock(S3Client::class);
    $client->shouldReceive('doesBucketExistV2')->with('boothparty', false)->andReturnFalse();
    $client->shouldReceive('createBucket')->once()->with(['Bucket' => 'boothparty']);

    fakeS3Client($client);

    $this->artisan('storage:ensure-bucket')->assertSuccessful();
});

it('leaves an existing bucket alone', function () {
    $client = Mockery::mock(S3Client::class);
    $client->shouldReceive('doesBucketExistV2')->with('boothparty', false)->andReturnTrue();
    $client->shouldNotReceive('createBucket');

    fakeS3Client($client);

    $this->artisan('storage:ensure-bucket')->assertSuccessful();
});
