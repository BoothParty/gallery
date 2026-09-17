<?php

use App\Models\AudioMessage;
use App\Models\Party;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function messageFile(string $name = 'REC001.mp3'): UploadedFile
{
    return new UploadedFile(base_path('tests/Fixtures/message.mp3'), $name, 'audio/mpeg', null, true);
}

// ── Dépôt par le studio ─────────────────────────────────────────────────────

it('takes the recordings off the handset', function () {
    Storage::fake('s3');
    $this->actingAs(User::factory()->create());

    $party = Party::factory()->withAudioGuestbook()->create();

    $this->post(route('admin.audiobook.messages.store', $party), [
        'messages' => [messageFile('REC001.mp3'), messageFile('REC002.mp3')],
    ])->assertRedirect();

    $messages = $party->fresh()->audioMessages;

    expect($messages)->toHaveCount(2)
        ->and($messages->pluck('position')->all())->toBe([1, 2])
        ->and($messages->first()->duration_seconds)->toBe(3);

    Storage::disk('s3')->assertExists($messages->first()->path);
});

it('orders the messages by the moment they were recorded', function () {
    Storage::fake('s3');
    $this->actingAs(User::factory()->create());

    $party = Party::factory()->withAudioGuestbook()->create();

    $this->post(route('admin.audiobook.messages.store', $party), [
        'messages' => [messageFile('late.mp3'), messageFile('early.mp3')],
        'recorded_at' => ['2026-06-12T22:15:00+02:00', '2026-06-12T18:12:00+02:00'],
    ])->assertRedirect();

    expect($party->fresh()->audioMessages->pluck('recorded_at')->map->format('H:i')->all())
        ->toBe(['18:12', '22:15']);
});

it('appends to the messages already uploaded', function () {
    Storage::fake('s3');
    $this->actingAs(User::factory()->create());

    $party = Party::factory()->withAudioGuestbook()->create();
    AudioMessage::factory()->for($party)->create(['position' => 1]);
    AudioMessage::factory()->for($party)->create(['position' => 2]);

    $this->post(route('admin.audiobook.messages.store', $party), [
        'messages' => [messageFile()],
    ])->assertRedirect();

    expect($party->fresh()->audioMessages->pluck('position')->all())->toBe([1, 2, 3]);
});

it('refuses anything the handset would not have produced', function () {
    Storage::fake('s3');
    $this->actingAs(User::factory()->create());

    $party = Party::factory()->withAudioGuestbook()->create();

    $this->post(route('admin.audiobook.messages.store', $party), [
        'messages' => [UploadedFile::fake()->image('selfie.jpg')],
    ])->assertSessionHasErrors('messages.0');

    expect($party->fresh()->audioMessages)->toHaveCount(0);
});

it('drops one message and its file', function () {
    Storage::fake('s3');
    $this->actingAs(User::factory()->create());

    $party = Party::factory()->withAudioGuestbook()->create();
    $message = AudioMessage::factory()->for($party)->create();
    Storage::disk('s3')->put($message->path, 'audio');

    $this->delete(route('admin.audiobook.messages.destroy', [$party, $message]))->assertRedirect();

    expect(AudioMessage::query()->find($message->id))->toBeNull();
    Storage::disk('s3')->assertMissing($message->path);
});

it('will not drop a message belonging to another party', function () {
    $this->actingAs(User::factory()->create());

    $party = Party::factory()->withAudioGuestbook()->create();
    $other = Party::factory()->withAudioGuestbook()->create();
    $message = AudioMessage::factory()->for($other)->create();

    $this->delete(route('admin.audiobook.messages.destroy', [$party, $message]))->assertNotFound();

    expect(AudioMessage::query()->find($message->id))->not->toBeNull();
});

// ── Côté client ─────────────────────────────────────────────────────────────

it('lets the client name a message', function () {
    $party = Party::factory()->withAudioGuestbook()->create();
    $message = AudioMessage::factory()->for($party)->create(['title' => null, 'position' => 4]);

    expect($message->displayTitle())->toBe('Message 04');

    $this->patch("/audiobook/{$party->client_uuid}/messages/{$message->id}", [
        'title' => 'Mamie Jeanne',
    ])->assertRedirect();

    expect($message->fresh()->displayTitle())->toBe('Mamie Jeanne');
});

it('lets the client clear a name back to the order of passage', function () {
    $party = Party::factory()->withAudioGuestbook()->create();
    $message = AudioMessage::factory()->for($party)->create(['title' => 'Mamie Jeanne', 'position' => 4]);

    $this->patch("/audiobook/{$party->client_uuid}/messages/{$message->id}", ['title' => ''])
        ->assertRedirect();

    expect($message->fresh()->title)->toBeNull()
        ->and($message->fresh()->displayTitle())->toBe('Message 04');
});

it('will not let a client rename another party message', function () {
    $party = Party::factory()->withAudioGuestbook()->create();
    $other = Party::factory()->withAudioGuestbook()->create();
    $message = AudioMessage::factory()->for($other)->create();

    $this->patch("/audiobook/{$party->client_uuid}/messages/{$message->id}", ['title' => 'Volé'])
        ->assertNotFound();
});

it('sends the messages to the client space', function () {
    $party = Party::factory()->withAudioGuestbook()->create();
    AudioMessage::factory()->for($party)->create(['title' => 'Papa', 'position' => 1]);

    $this->get('/audiobook/'.$party->client_uuid)
        ->assertInertia(fn ($page) => $page
            ->has('messages', 1)
            ->where('messages.0.display_title', 'Papa')
        );
});

// ── Archive ─────────────────────────────────────────────────────────────────

it('streams every message as one zip', function () {
    Storage::fake('s3');

    $party = Party::factory()->withAudioGuestbook()->create(['display_name' => 'Camille & Antoine']);

    foreach ([1, 2] as $position) {
        $message = AudioMessage::factory()->for($party)->create(['position' => $position]);
        Storage::disk('s3')->put($message->path, file_get_contents(base_path('tests/Fixtures/message.mp3')));
    }

    $response = $this->post("/audiobook/{$party->client_uuid}/telecharger");

    $response->assertOk()
        ->assertHeader('content-type', 'application/zip')
        ->assertHeader('content-disposition', 'attachment; filename="camille-antoine-messages.zip"');

    expect(strlen($response->streamedContent()))->toBeGreaterThan(1000);
});

it('has nothing to archive before the studio uploads', function () {
    $party = Party::factory()->withAudioGuestbook()->create();

    $this->post("/audiobook/{$party->client_uuid}/telecharger")->assertNotFound();
});
