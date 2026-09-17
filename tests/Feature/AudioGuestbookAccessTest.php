<?php

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
