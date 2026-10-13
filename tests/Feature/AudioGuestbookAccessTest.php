<?php

use App\Models\AudioGreeting;
use App\Models\Party;

it('opens the client space with the client uuid', function () {
    $party = Party::factory()->withAudioGuestbook()->create();

    $this->get('/audiobook/'.$party->client_uuid)->assertOk();
});

it('does not open the client space with the gallery uuid', function () {
    $party = Party::factory()->withAudioGuestbook()->create();

    $this->get('/audiobook/'.$party->gallery_uuid)->assertNotFound();
});

it('does not open the client space of a party without the module', function () {
    $party = Party::factory()->create(['has_audio_guestbook' => false]);

    $this->get('/audiobook/'.$party->client_uuid)->assertNotFound();
});

it('does not open the client space with an unknown uuid', function () {
    $this->get('/audiobook/00000000-0000-0000-0000-000000000000')->assertNotFound();
});

it('resolves the literal audiobook route ahead of the party catch-all', function () {
    $party = Party::factory()->withAudioGuestbook()->create(['name' => 'anything']);

    $this->get('/audiobook/'.$party->client_uuid)->assertOk();
});

it('builds the client link from the client uuid', function () {
    $party = Party::factory()->withAudioGuestbook()->create();

    expect($party->audiobookUrl())->toBe(url('/audiobook/'.$party->client_uuid));
});

it('throttles the entry point per address, not per uuid', function () {
    $party = Party::factory()->withAudioGuestbook()->create();

    // Une énumération essaie un uuid différent à chaque coup : le compteur ne
    // doit donc pas être remis à zéro par le changement d'uuid.
    foreach (range(1, 30) as $attempt) {
        $this->get('/audiobook/'.fake()->uuid())->assertNotFound();
    }

    $this->get('/audiobook/'.$party->client_uuid)->assertStatus(429);
});

it('sends the event identity and no greeting to a client who has not recorded', function () {
    $party = Party::factory()->withAudioGuestbook()->eventAt(now()->setDate(2026, 6, 12))->create([
        'display_name' => 'Camille & Antoine',
    ]);

    $this->get('/audiobook/'.$party->client_uuid)
        ->assertInertia(fn ($page) => $page
            ->component('party/audiobook')
            ->where('party.title', 'Camille & Antoine')
            ->where('party.event_line', '12 juin 2026')
            ->where('greeting', null)
        );
});

it('sends the greeting state to a client who has recorded', function () {
    $party = Party::factory()->withAudioGuestbook()->create();
    AudioGreeting::factory()->for($party)->rejected('Son saturé')->create();

    $this->get('/audiobook/'.$party->client_uuid)
        ->assertInertia(fn ($page) => $page
            ->where('greeting.status', 'rejected')
            ->where('greeting.rejection_reason', 'Son saturé')
            ->has('greeting.url')
        );
});
