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
    $client->shouldReceive('putBucketPolicy')->once();

    fakeS3Client($client);

    $this->artisan('storage:ensure-bucket')->assertSuccessful();
});

it('opens an existing bucket for anonymous reads without recreating it', function () {
    $client = Mockery::mock(S3Client::class);
    $client->shouldReceive('doesBucketExistV2')->with('boothparty', false)->andReturnTrue();
    $client->shouldNotReceive('createBucket');
    $client->shouldReceive('putBucketPolicy')->once();

    fakeS3Client($client);

    $this->artisan('storage:ensure-bucket')->assertSuccessful();
});

it('grants anonymous read on every object of the bucket', function () {
    $policy = null;

    $client = Mockery::mock(S3Client::class);
    $client->shouldReceive('doesBucketExistV2')->andReturnTrue();
    $client->shouldReceive('putBucketPolicy')->once()
        ->andReturnUsing(function (array $args) use (&$policy) {
            $policy = json_decode($args['Policy'], true);
        });

    fakeS3Client($client);

    $this->artisan('storage:ensure-bucket')->assertSuccessful();

    expect($policy['Statement'][0])
        ->toMatchArray([
            'Effect' => 'Allow',
            'Action' => ['s3:GetObject'],
            'Resource' => ['arn:aws:s3:::boothparty/*'],
        ]);
});
