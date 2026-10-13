<?php

use App\Jobs\ConvertGreetingToMp3;
use App\Models\AudioGreeting;
use Illuminate\Support\Facades\Storage;

it('converts the browser recording to mp3 and reads its length', function () {
    Storage::fake('s3');

    $greeting = AudioGreeting::factory()->create([
        'original_path' => 'parties/1/audio/take.webm',
        'mp3_path' => null,
        'duration_seconds' => null,
    ]);

    Storage::disk('s3')->put($greeting->original_path, file_get_contents(base_path('tests/Fixtures/greeting.webm')));

    (new ConvertGreetingToMp3($greeting))->handle();

    $greeting->refresh();

    expect($greeting->mp3_path)->toBe('parties/1/audio/take.mp3')
        ->and($greeting->duration_seconds)->toBe(2);

    Storage::disk('s3')->assertExists($greeting->mp3_path);
    Storage::disk('s3')->assertExists($greeting->original_path);
});

it('keeps the original alongside the converted file', function () {
    Storage::fake('s3');

    $greeting = AudioGreeting::factory()->create(['original_path' => 'parties/1/audio/take.webm']);
    Storage::disk('s3')->put($greeting->original_path, file_get_contents(base_path('tests/Fixtures/greeting.webm')));

    (new ConvertGreetingToMp3($greeting))->handle();

    expect(Storage::disk('s3')->get($greeting->fresh()->original_path))
        ->toBe(file_get_contents(base_path('tests/Fixtures/greeting.webm')));
});

it('does nothing when the original has gone', function () {
    Storage::fake('s3');

    $greeting = AudioGreeting::factory()->create([
        'original_path' => 'parties/1/audio/missing.webm',
        'mp3_path' => null,
    ]);

    (new ConvertGreetingToMp3($greeting))->handle();

    expect($greeting->fresh()->mp3_path)->toBeNull();
});
