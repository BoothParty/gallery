<?php

use App\Models\AudioMessage;
use App\Models\Party;
use Illuminate\Support\Facades\Storage;

it('belongs to a party', function () {
    $party = Party::factory()->withAudioGuestbook()->create();
    $message = AudioMessage::factory()->for($party)->create();

    expect($message->party->id)->toBe($party->id);
});

it('falls back to its position when the client has not named it', function () {
    $message = AudioMessage::factory()->create(['title' => null, 'position' => 4]);

    expect($message->displayTitle())->toBe('Message 04');
});

it('uses the title the client gave it', function () {
    $message = AudioMessage::factory()->create(['title' => 'Mamie Jeanne']);

    expect($message->displayTitle())->toBe('Mamie Jeanne');
});

it('signs the url of its file', function () {
    $message = AudioMessage::factory()->create();

    expect($message->url())->toBeString()->toContain($message->path);
});

it('deletes its file when the message is deleted', function () {
    Storage::fake('s3');

    $message = AudioMessage::factory()->create();
    Storage::disk('s3')->put($message->path, 'audio');

    $message->delete();

    Storage::disk('s3')->assertMissing($message->path);
});

it('is ordered by position when read from its party', function () {
    $party = Party::factory()->withAudioGuestbook()->create();
    AudioMessage::factory()->for($party)->create(['position' => 3]);
    AudioMessage::factory()->for($party)->create(['position' => 1]);
    AudioMessage::factory()->for($party)->create(['position' => 2]);

    expect($party->audioMessages->pluck('position')->all())->toBe([1, 2, 3]);
});
