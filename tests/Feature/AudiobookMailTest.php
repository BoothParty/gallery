<?php

use App\Mail\AudiobookReady;
use App\Mail\ExpiryReminder;
use App\Mail\GreetingRejected;
use App\Mail\GreetingReminder;
use App\Mail\GreetingSubmitted;
use App\Mail\GreetingValidated;
use App\Mail\MessagesAvailable;
use App\Models\AudioGreeting;
use App\Models\AudioMessage;
use App\Models\Party;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Storage;

// ── Transactionnels ─────────────────────────────────────────────────────────

it('warns the studio when a client submits a greeting', function () {
    Mail::fake();
    config(['party.studio_email' => 'studio@boothparty.test']);

    $party = Party::factory()->withAudioGuestbook()->create();
    AudioGreeting::factory()->for($party)->create();

    $this->post("/audiobook/{$party->client_uuid}/annonce/soumettre")->assertRedirect();

    Mail::assertQueued(GreetingSubmitted::class, fn (GreetingSubmitted $mail): bool => $mail->hasTo('studio@boothparty.test'));
});

it('says nothing to a studio that has no mailbox configured', function () {
    Mail::fake();
    config(['party.studio_email' => null]);

    $party = Party::factory()->withAudioGuestbook()->create();
    AudioGreeting::factory()->for($party)->create();

    $this->post("/audiobook/{$party->client_uuid}/annonce/soumettre")->assertRedirect();

    Mail::assertNothingQueued();
    expect($party->fresh()->greeting->status->value)->toBe('pending');
});

it('tells the client their greeting was validated', function () {
    Mail::fake();
    $this->actingAs(User::factory()->create());

    $party = Party::factory()->withAudioGuestbook()->create(['client_email' => 'camille@example.test']);
    AudioGreeting::factory()->for($party)->pending()->create();

    $this->post("/admin/livre-or/{$party->id}/annonce/valider")->assertRedirect();

    Mail::assertQueued(GreetingValidated::class, fn (GreetingValidated $mail): bool => $mail->hasTo('camille@example.test'));
});

it('sends the studio reason to the client on a refusal', function () {
    Mail::fake();
    $this->actingAs(User::factory()->create());

    $party = Party::factory()->withAudioGuestbook()->create(['client_email' => 'camille@example.test']);
    AudioGreeting::factory()->for($party)->pending()->create();

    $this->post("/admin/livre-or/{$party->id}/annonce/refuser", [
        'rejection_reason' => 'Son saturé.',
    ])->assertRedirect();

    Mail::assertQueued(GreetingRejected::class, fn (GreetingRejected $mail): bool => $mail->reason === 'Son saturé.');
});

it('never fails an action because the client has no address', function () {
    Mail::fake();
    $this->actingAs(User::factory()->create());

    $party = Party::factory()->withAudioGuestbook()->create(['client_email' => null]);
    AudioGreeting::factory()->for($party)->pending()->create();

    $this->post("/admin/livre-or/{$party->id}/annonce/valider")->assertRedirect();

    Mail::assertNothingQueued();
    expect($party->fresh()->greeting->status->value)->toBe('validated');
});

it('tells the client their messages are online', function () {
    Mail::fake();
    Storage::fake('s3');
    $this->actingAs(User::factory()->create());

    $party = Party::factory()->withAudioGuestbook()->create(['client_email' => 'camille@example.test']);

    $this->post(route('admin.audiobook.messages.store', $party), [
        'messages' => [new UploadedFile(base_path('tests/Fixtures/message.mp3'), 'REC001.mp3', 'audio/mpeg', null, true)],
    ])->assertRedirect();

    Mail::assertQueued(MessagesAvailable::class);
});

// ── Lien client ─────────────────────────────────────────────────────────────

it('sends the client their link on demand and records when', function () {
    Mail::fake();
    $this->actingAs(User::factory()->create());

    $party = Party::factory()->withAudioGuestbook()->create(['client_email' => 'camille@example.test']);

    $this->post(route('admin.parties.audiobook-link', $party))->assertRedirect();

    Mail::assertQueued(AudiobookReady::class, fn (AudiobookReady $mail): bool => $mail->hasTo('camille@example.test'));
    expect($party->fresh()->link_sent_at)->not->toBeNull();
});

it('records nothing when there is no address to send to', function () {
    Mail::fake();
    $this->actingAs(User::factory()->create());

    $party = Party::factory()->withAudioGuestbook()->create(['client_email' => null]);

    $this->post(route('admin.parties.audiobook-link', $party))->assertRedirect();

    Mail::assertNothingQueued();
    expect($party->fresh()->link_sent_at)->toBeNull();
});

// ── Rendu ───────────────────────────────────────────────────────────────────

/**
 * Rendered for real rather than faked: a missing variable in a template is
 * invisible until someone receives the mail.
 */
it('renders every mailable without a missing variable', function (string $mailable) {
    $party = Party::factory()->withAudioGuestbook()->create([
        'display_name' => 'Camille & Antoine',
        'client_email' => 'camille@example.test',
        'expire_at' => now()->addDays(90),
    ]);
    AudioGreeting::factory()->for($party)->pending()->create(['client_note' => 'Prénom : Lo-ic']);
    AudioMessage::factory()->for($party)->create();

    $mail = $mailable === GreetingRejected::class
        ? new GreetingRejected($party, 'Son saturé.')
        : new $mailable($party);

    $html = $mail->render();

    expect($html)->toContain('Livre d\'or audio')
        ->and($html)->not->toContain('__id__');
})->with([
    AudiobookReady::class,
    GreetingSubmitted::class,
    GreetingValidated::class,
    GreetingRejected::class,
    MessagesAvailable::class,
    GreetingReminder::class,
    ExpiryReminder::class,
]);
