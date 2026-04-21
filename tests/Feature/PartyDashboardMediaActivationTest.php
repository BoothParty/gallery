<?php

use App\Models\Party;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('can activate pictures from the party dashboard sidebar flow', function () {
    $party = Party::factory()->create([
        'pictures_enabled' => false,
        'audios_enabled' => false,
    ]);

    $this->post(route('party.dashboard.media.enable', ['party' => $party, 'media_type' => 'pictures']))
        ->assertRedirect(route('party.dashboard.show', $party));

    expect($party->fresh()->pictures_enabled)->toBeTrue()
        ->and($party->fresh()->audios_enabled)->toBeFalse();
});

it('can activate audios from the party dashboard sidebar flow', function () {
    $party = Party::factory()->create([
        'pictures_enabled' => true,
        'audios_enabled' => false,
    ]);

    $this->post(route('party.dashboard.media.enable', ['party' => $party, 'media_type' => 'audios']))
        ->assertRedirect(route('party.dashboard.show', $party));

    expect($party->fresh()->audios_enabled)->toBeTrue()
        ->and($party->fresh()->pictures_enabled)->toBeTrue();
});
