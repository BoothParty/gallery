<?php

use App\Enums\GreetingStatus;
use App\Models\AudioGreeting;
use App\Models\Party;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function audiobookParty(): Party
{
    return Party::factory()->withAudioGuestbook()->create();
}

it('stores a recording as a draft', function () {
    Storage::fake('s3');
    $party = audiobookParty();

    $this->post('/audiobook/'.$party->client_uuid.'/annonce', [
        'recording' => UploadedFile::fake()->create('take.webm', 400, 'audio/webm'),
    ])->assertRedirect();

    $greeting = $party->fresh()->greeting;

    expect($greeting)->not->toBeNull()
        ->and($greeting->status)->toBe(GreetingStatus::Draft)
        ->and($greeting->original_path)->toStartWith('parties/'.$party->id.'/audio/');

    Storage::disk('s3')->assertExists($greeting->original_path);
});

it('replaces the previous take rather than piling them up', function () {
    Storage::fake('s3');
    $party = audiobookParty();
    $first = AudioGreeting::factory()->for($party)->create();
    Storage::disk('s3')->put($first->original_path, 'old');

    $this->post('/audiobook/'.$party->client_uuid.'/annonce', [
        'recording' => UploadedFile::fake()->create('take.webm', 400, 'audio/webm'),
    ])->assertRedirect();

    expect($party->fresh()->greeting->id)->toBe($first->id);
    Storage::disk('s3')->assertMissing($first->original_path);
});

it('refuses a recording that is not audio', function () {
    Storage::fake('s3');
    $party = audiobookParty();

    $this->post('/audiobook/'.$party->client_uuid.'/annonce', [
        'recording' => UploadedFile::fake()->image('selfie.jpg'),
    ])->assertSessionHasErrors('recording');

    expect($party->fresh()->greeting)->toBeNull();
});

it('submits a draft for validation with the studio note', function () {
    $party = audiobookParty();
    AudioGreeting::factory()->for($party)->create();

    $this->post('/audiobook/'.$party->client_uuid.'/annonce/soumettre', [
        'client_note' => 'Le prénom se prononce « Lo-ic ».',
    ])->assertRedirect();

    expect($party->fresh()->greeting)
        ->status->toBe(GreetingStatus::Pending)
        ->client_note->toBe('Le prénom se prononce « Lo-ic ».')
        ->submitted_at->not->toBeNull();
});

it('submits without a note', function () {
    $party = audiobookParty();
    AudioGreeting::factory()->for($party)->create();

    $this->post('/audiobook/'.$party->client_uuid.'/annonce/soumettre')
        ->assertRedirect();

    expect($party->fresh()->greeting->status)->toBe(GreetingStatus::Pending);
});

it('refuses a note longer than 120 characters', function () {
    $party = audiobookParty();
    AudioGreeting::factory()->for($party)->create();

    $this->post('/audiobook/'.$party->client_uuid.'/annonce/soumettre', [
        'client_note' => str_repeat('a', 121),
    ])->assertSessionHasErrors('client_note');
});

it('refuses to submit when there is nothing recorded', function () {
    $party = audiobookParty();

    $this->post('/audiobook/'.$party->client_uuid.'/annonce/soumettre')
        ->assertNotFound();
});

it('sends a rejected greeting back to draft when a new take is stored', function () {
    Storage::fake('s3');
    $party = audiobookParty();
    AudioGreeting::factory()->for($party)->rejected('Son saturé')->create();

    $this->post('/audiobook/'.$party->client_uuid.'/annonce', [
        'recording' => UploadedFile::fake()->create('take.webm', 400, 'audio/webm'),
    ])->assertRedirect();

    expect($party->fresh()->greeting)
        ->status->toBe(GreetingStatus::Draft)
        ->rejection_reason->toBeNull()
        ->submitted_at->toBeNull();
});

it('reopens a validated greeting the same way', function () {
    Storage::fake('s3');
    $party = audiobookParty();
    AudioGreeting::factory()->for($party)->validated()->create();

    $this->post('/audiobook/'.$party->client_uuid.'/annonce', [
        'recording' => UploadedFile::fake()->create('take.webm', 400, 'audio/webm'),
    ])->assertRedirect();

    expect($party->fresh()->greeting)
        ->status->toBe(GreetingStatus::Draft)
        ->validated_at->toBeNull()
        ->mp3_path->toBeNull();
});

it('does not accept a recording for a party without the module', function () {
    Storage::fake('s3');
    $party = Party::factory()->create(['has_audio_guestbook' => false]);

    $this->post('/audiobook/'.$party->client_uuid.'/annonce', [
        'recording' => UploadedFile::fake()->create('take.webm', 400, 'audio/webm'),
    ])->assertNotFound();
});
