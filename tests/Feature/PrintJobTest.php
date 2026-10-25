<?php

use App\Models\Party;
use App\Models\Picture;
use App\Models\PrintJob;
use Illuminate\Support\Facades\Storage;

it('keeps only unprinted jobs in the pending scope', function () {
    $picture = Picture::factory()->create();
    $pending = PrintJob::factory()->for($picture)->create();
    PrintJob::factory()->for($picture)->printed()->create();

    expect(PrintJob::pending()->pluck('id')->all())->toBe([$pending->id]);
});

it('reaches its jobs from the party through the pictures', function () {
    $party = Party::factory()->create();
    $job = PrintJob::factory()->for(Picture::factory()->for($party))->create();
    PrintJob::factory()->create();

    expect($party->printJobs()->pluck('print_jobs.id')->all())->toBe([$job->id]);
});

it('drops the jobs of a deleted picture', function () {
    Storage::fake('s3');
    $job = PrintJob::factory()->create();

    $job->picture->delete();

    $this->assertModelMissing($job);
});

it('flags the pictures that have a pending job', function () {
    $pending = Picture::factory()->create();
    PrintJob::factory()->for($pending)->create();
    $printed = Picture::factory()->create();
    PrintJob::factory()->for($printed)->printed()->create();
    $untouched = Picture::factory()->create();

    $flags = Picture::withPrintPending()->get()->pluck('print_pending', 'id');

    expect($flags[$pending->id])->toBeTrue()
        ->and($flags[$printed->id])->toBeFalse()
        ->and($flags[$untouched->id])->toBeFalse();
});
