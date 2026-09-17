<?php

use App\Enums\GreetingStatus;
use App\Models\AudioGreeting;
use App\Models\Party;
use Illuminate\Support\Facades\Storage;

it('belongs to a party', function () {
    $party = Party::factory()->withAudioGuestbook()->create();
    $greeting = AudioGreeting::factory()->for($party)->create();

    expect($greeting->party->id)->toBe($party->id);
});

it('starts as a draft', function () {
    $greeting = AudioGreeting::factory()->create();

    expect($greeting->status)->toBe(GreetingStatus::Draft);
});

it('casts the status as an enum', function () {
    $greeting = AudioGreeting::factory()->validated()->create();

    expect($greeting->fresh()->status)->toBe(GreetingStatus::Validated);
});

it('carries the rejection reason when rejected', function () {
    $greeting = AudioGreeting::factory()->rejected('Son saturé')->create();

    expect($greeting->fresh())
        ->status->toBe(GreetingStatus::Rejected)
        ->rejection_reason->toBe('Son saturé');
});

it('has no playable url until the conversion has run', function () {
    $greeting = AudioGreeting::factory()->create(['mp3_path' => null]);

    expect($greeting->url())->toBeNull();
});

it('signs the url of its mp3 once converted', function () {
    $greeting = AudioGreeting::factory()->validated()->create();

    expect($greeting->url())->toBeString()->toContain($greeting->mp3_path);
});

it('deletes both files when the greeting is deleted', function () {
    Storage::fake('s3');

    $greeting = AudioGreeting::factory()->validated()->create();
    Storage::disk('s3')->put($greeting->original_path, 'original');
    Storage::disk('s3')->put($greeting->mp3_path, 'converted');

    $greeting->delete();

    Storage::disk('s3')->assertMissing($greeting->original_path);
    Storage::disk('s3')->assertMissing($greeting->mp3_path);
});

it('is reachable from its party as a single record', function () {
    $party = Party::factory()->withAudioGuestbook()->create();
    AudioGreeting::factory()->for($party)->create();

    expect($party->fresh()->greeting)->toBeInstanceOf(AudioGreeting::class);
});
