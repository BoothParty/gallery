<?php

use App\Mail\ExpiryReminder;
use App\Mail\GreetingReminder;
use App\Models\AudioGreeting;
use App\Models\AudioMessage;
use App\Models\Party;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    Mail::fake();
});

// ── Annonce manquante ───────────────────────────────────────────────────────

it('nudges a client whose event is a week away and whose greeting is missing', function () {
    $party = Party::factory()->withAudioGuestbook()
        ->eventAt(now()->addDays(5))
        ->create(['client_email' => 'camille@example.test']);

    $this->artisan('audiobook:remind')->assertSuccessful();

    Mail::assertQueued(GreetingReminder::class, fn (GreetingReminder $mail): bool => $mail->party->is($party));
    expect($party->fresh()->greeting_reminder_sent_at)->not->toBeNull();
});

it('nudges a client whose greeting was refused', function () {
    $party = Party::factory()->withAudioGuestbook()
        ->eventAt(now()->addDays(3))
        ->create(['client_email' => 'camille@example.test']);
    AudioGreeting::factory()->for($party)->rejected('Son saturé')->create();

    $this->artisan('audiobook:remind')->assertSuccessful();

    Mail::assertQueued(GreetingReminder::class);
});

it('leaves alone a client whose greeting is validated', function () {
    $party = Party::factory()->withAudioGuestbook()
        ->eventAt(now()->addDays(3))
        ->create(['client_email' => 'camille@example.test']);
    AudioGreeting::factory()->for($party)->validated()->create();

    $this->artisan('audiobook:remind')->assertSuccessful();

    Mail::assertNotQueued(GreetingReminder::class);
    expect($party->fresh()->greeting_reminder_sent_at)->toBeNull();
});

it('leaves alone an event that is still far off', function () {
    Party::factory()->withAudioGuestbook()
        ->eventAt(now()->addDays(30))
        ->create(['client_email' => 'camille@example.test']);

    $this->artisan('audiobook:remind')->assertSuccessful();

    Mail::assertNotQueued(GreetingReminder::class);
});

it('leaves alone a party sold without the module', function () {
    Party::factory()->eventAt(now()->addDays(3))->create([
        'has_audio_guestbook' => false,
        'client_email' => 'camille@example.test',
    ]);

    $this->artisan('audiobook:remind')->assertSuccessful();

    Mail::assertNotQueued(GreetingReminder::class);
});

// ── Expiration ──────────────────────────────────────────────────────────────

it('warns a client whose recordings expire in a week', function () {
    $party = Party::factory()->withAudioGuestbook()
        ->expiresAt(now()->addDays(5))
        ->create(['client_email' => 'camille@example.test']);
    AudioMessage::factory()->for($party)->create();

    $this->artisan('audiobook:remind')->assertSuccessful();

    Mail::assertQueued(ExpiryReminder::class, fn (ExpiryReminder $mail): bool => $mail->party->is($party));
    expect($party->fresh()->expiry_reminder_sent_at)->not->toBeNull();
});

it('says nothing about an expiry when there is nothing to lose', function () {
    Party::factory()->withAudioGuestbook()
        ->expiresAt(now()->addDays(5))
        ->create(['client_email' => 'camille@example.test']);

    $this->artisan('audiobook:remind')->assertSuccessful();

    Mail::assertNotQueued(ExpiryReminder::class);
});

// ── Idempotence ─────────────────────────────────────────────────────────────

it('sends each reminder once and only once', function () {
    $party = Party::factory()->withAudioGuestbook()
        ->eventAt(now()->addDays(5))
        ->create(['client_email' => 'camille@example.test']);

    $this->artisan('audiobook:remind')->assertSuccessful();
    $this->artisan('audiobook:remind')->assertSuccessful();
    $this->artisan('audiobook:remind')->assertSuccessful();

    Mail::assertQueuedCount(1);
    expect($party->fresh()->greeting_reminder_sent_at)->not->toBeNull();
});

/**
 * The reminder must survive a day the scheduler did not run — a machine down,
 * a deploy. A window covering a single day would drop it for good.
 */
it('catches up a day the scheduler missed', function () {
    $party = Party::factory()->withAudioGuestbook()
        ->eventAt(now()->addDays(7))
        ->create(['client_email' => 'camille@example.test']);

    // Personne ne tourne à J-7. On reprend trois jours plus tard.
    $this->travel(3)->days();

    $this->artisan('audiobook:remind')->assertSuccessful();

    Mail::assertQueued(GreetingReminder::class);
    expect($party->fresh()->greeting_reminder_sent_at)->not->toBeNull();
});

it('is quiet when nothing is due', function () {
    Party::factory()->withAudioGuestbook()->eventAt(now()->addMonths(3))->create();

    $this->artisan('audiobook:remind')->assertSuccessful();

    Mail::assertNothingQueued();
});

it('is scheduled every day', function () {
    $events = collect(app(Illuminate\Console\Scheduling\Schedule::class)->events())
        ->filter(fn ($event): bool => str_contains($event->command ?? '', 'audiobook:remind'));

    expect($events)->toHaveCount(1)
        ->and($events->first()->expression)->toBe('0 9 * * *');
});
