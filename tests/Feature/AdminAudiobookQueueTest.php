<?php

use App\Models\AudioGreeting;
use App\Models\AudioMessage;
use App\Models\Party;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('lists the greetings waiting for the studio', function () {
    $waiting = Party::factory()->withAudioGuestbook()->eventAt(now()->addWeek())->create();
    AudioGreeting::factory()->for($waiting)->pending()->create();

    $validated = Party::factory()->withAudioGuestbook()->eventAt(now()->addWeek())->create();
    AudioGreeting::factory()->for($validated)->validated()->create();

    $this->get(route('admin.audiobook.queue'))
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/audiobook/queue')
            ->has('awaitingValidation', 1)
            ->where('awaitingValidation.0.id', $waiting->id)
        );
});

it('lists past events whose messages are still missing', function () {
    $toUpload = Party::factory()->withAudioGuestbook()->eventAt(now()->subWeek())->create();

    $done = Party::factory()->withAudioGuestbook()->eventAt(now()->subWeek())->create();
    AudioMessage::factory()->for($done)->create();

    $this->get(route('admin.audiobook.queue'))
        ->assertInertia(fn ($page) => $page
            ->has('awaitingUpload', 1)
            ->where('awaitingUpload.0.id', $toUpload->id)
        );
});

it('lists upcoming events whose client has not recorded', function () {
    $late = Party::factory()->withAudioGuestbook()->eventAt(now()->addWeek())->create();

    $recorded = Party::factory()->withAudioGuestbook()->eventAt(now()->addWeek())->create();
    AudioGreeting::factory()->for($recorded)->pending()->create();

    $this->get(route('admin.audiobook.queue'))
        ->assertInertia(fn ($page) => $page
            ->has('withoutGreeting', 1)
            ->where('withoutGreeting.0.id', $late->id)
        );
});

it('ignores parties sold without the module', function () {
    Party::factory()->eventAt(now()->subWeek())->create(['has_audio_guestbook' => false]);

    $this->get(route('admin.audiobook.queue'))
        ->assertInertia(fn ($page) => $page
            ->has('awaitingValidation', 0)
            ->has('awaitingUpload', 0)
            ->has('withoutGreeting', 0)
        );
});

it('counts only what the studio can act on in the badge', function () {
    $toValidate = Party::factory()->withAudioGuestbook()->eventAt(now()->addWeek())->create();
    AudioGreeting::factory()->for($toValidate)->pending()->create();

    Party::factory()->withAudioGuestbook()->eventAt(now()->subWeek())->create();

    // Un client en retard n'est pas une action du studio : il ne compte pas.
    Party::factory()->withAudioGuestbook()->eventAt(now()->addWeek())->create();

    $this->get(route('admin.audiobook.queue'))
        ->assertInertia(fn ($page) => $page
            ->where('audiobookPending', 2)
            ->has('withoutGreeting', 1)
        );
});

it('shows the badge count on every administration page', function () {
    $party = Party::factory()->withAudioGuestbook()->eventAt(now()->addWeek())->create();
    AudioGreeting::factory()->for($party)->pending()->create();

    $this->get(route('admin.parties.index'))
        ->assertInertia(fn ($page) => $page->where('audiobookPending', 1));
});

it('keeps the queue behind authentication', function () {
    auth()->logout();

    $this->get(route('admin.audiobook.queue'))->assertRedirect(route('login'));
});
