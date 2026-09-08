<?php

use Aws\CommandInterface;
use Aws\Exception\AwsException;
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
    $client->shouldReceive('deleteBucketPolicy');

    fakeS3Client($client);

    $this->artisan('storage:ensure-bucket')->assertSuccessful();
});

it('closes an existing bucket to anonymous reads without recreating it', function () {
    $client = Mockery::mock(S3Client::class);
    $client->shouldReceive('doesBucketExistV2')->andReturnTrue();
    $client->shouldNotReceive('createBucket');
    $client->shouldReceive('deleteBucketPolicy')->once()->with(['Bucket' => 'boothparty']);

    fakeS3Client($client);

    $this->artisan('storage:ensure-bucket')->assertSuccessful();
});

it('survives a bucket that carries no policy', function () {
    $client = Mockery::mock(S3Client::class);
    $client->shouldReceive('doesBucketExistV2')->andReturnTrue();
    $client->shouldReceive('deleteBucketPolicy')->andThrow(new AwsException(
        'no policy',
        Mockery::mock(CommandInterface::class),
        ['code' => 'NoSuchBucketPolicy'],
    ));

    fakeS3Client($client);

    $this->artisan('storage:ensure-bucket')->assertSuccessful();
});

/**
 * A policy that could not be removed must not read as a closed bucket: the
 * signed-URL expiry the whole gallery relies on would be short-circuited by a
 * public policy left in place.
 */
it('surfaces a policy deletion that failed for any other reason', function () {
    $client = Mockery::mock(S3Client::class);
    $client->shouldReceive('doesBucketExistV2')->andReturnTrue();
    $client->shouldReceive('deleteBucketPolicy')->andThrow(new AwsException(
        'access denied',
        Mockery::mock(CommandInterface::class),
        ['code' => 'AccessDenied'],
    ));

    fakeS3Client($client);

    expect(fn () => $this->artisan('storage:ensure-bucket'))->toThrow(AwsException::class);
});
