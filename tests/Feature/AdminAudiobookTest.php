<?php

use App\Enums\GreetingStatus;
use App\Models\AudioGreeting;
use App\Models\Party;
use App\Models\User;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('shows the studio the greeting waiting for it', function () {
    $party = Party::factory()->withAudioGuestbook()->create(['display_name' => 'Camille & Antoine']);
    AudioGreeting::factory()->for($party)->pending()->create(['client_note' => 'Prénom : Lo-ic']);

    $this->get("/admin/livre-or/{$party->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page
            ->component('admin/audiobook/index')
            ->where('party.name', 'Camille & Antoine')
            ->where('greeting.status', 'pending')
            ->where('greeting.client_note', 'Prénom : Lo-ic')
            ->where('greeting.awaiting_conversion', false)
        );
});

it('shows an empty state when the client has recorded nothing', function () {
    $party = Party::factory()->withAudioGuestbook()->create();

    $this->get("/admin/livre-or/{$party->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('greeting', null));
});

it('validates a greeting in one click', function () {
    $party = Party::factory()->withAudioGuestbook()->create();
    AudioGreeting::factory()->for($party)->pending()->create();

    $this->post("/admin/livre-or/{$party->id}/annonce/valider")->assertRedirect();

    expect($party->fresh()->greeting)
        ->status->toBe(GreetingStatus::Validated)
        ->validated_at->not->toBeNull();
});

it('clears a previous reason when validating', function () {
    $party = Party::factory()->withAudioGuestbook()->create();
    AudioGreeting::factory()->for($party)->rejected('Son saturé')->create();

    $this->post("/admin/livre-or/{$party->id}/annonce/valider")->assertRedirect();

    expect($party->fresh()->greeting->rejection_reason)->toBeNull();
});

it('refuses a greeting with a reason', function () {
    $party = Party::factory()->withAudioGuestbook()->create();
    AudioGreeting::factory()->for($party)->pending()->create();

    $this->post("/admin/livre-or/{$party->id}/annonce/refuser", [
        'rejection_reason' => 'Le son est saturé, reculez du micro.',
    ])->assertRedirect();

    expect($party->fresh()->greeting)
        ->status->toBe(GreetingStatus::Rejected)
        ->rejection_reason->toBe('Le son est saturé, reculez du micro.')
        ->validated_at->toBeNull();
});

it('will not refuse a greeting without a reason', function () {
    $party = Party::factory()->withAudioGuestbook()->create();
    AudioGreeting::factory()->for($party)->pending()->create();

    $this->post("/admin/livre-or/{$party->id}/annonce/refuser")
        ->assertSessionHasErrors('rejection_reason');

    expect($party->fresh()->greeting->status)->toBe(GreetingStatus::Pending);
});

it('takes a validated greeting back to refused', function () {
    $party = Party::factory()->withAudioGuestbook()->create();
    AudioGreeting::factory()->for($party)->validated()->create();

    $this->post("/admin/livre-or/{$party->id}/annonce/refuser", [
        'rejection_reason' => 'Finalement trop long.',
    ])->assertRedirect();

    expect($party->fresh()->greeting)
        ->status->toBe(GreetingStatus::Rejected)
        ->validated_at->toBeNull();
});

it('404s when there is no greeting to act on', function () {
    $party = Party::factory()->withAudioGuestbook()->create();

    $this->post("/admin/livre-or/{$party->id}/annonce/valider")->assertNotFound();
});

it('keeps the studio page behind authentication', function () {
    auth()->logout();

    $party = Party::factory()->withAudioGuestbook()->create();

    $this->get("/admin/livre-or/{$party->id}")->assertRedirect(route('login'));
});

// ── Modules ─────────────────────────────────────────────────────────────────

it('turns the audio guestbook on for a party that did not have it', function () {
    $party = Party::factory()->create(['has_audio_guestbook' => false]);

    $this->patch(route('admin.parties.modules', $party), ['has_audio_guestbook' => true])
        ->assertRedirect();

    expect($party->fresh()->has_audio_guestbook)->toBeTrue()
        ->and($party->fresh()->has_gallery)->toBeTrue();
});

it('turns a module off without touching the other', function () {
    $party = Party::factory()->withAudioGuestbook()->create();

    $this->patch(route('admin.parties.modules', $party), ['has_gallery' => false])
        ->assertRedirect();

    expect($party->fresh())
        ->has_gallery->toBeFalse()
        ->has_audio_guestbook->toBeTrue();
});

it('sends the module flags to the sidebar', function () {
    $party = Party::factory()->audioOnly()->create(['display_name' => 'Sophie & Marc']);

    $this->get(route('admin.parties.index'))
        ->assertInertia(fn ($page) => $page
            ->where('searchableParties.0.has_gallery', false)
            ->where('searchableParties.0.has_audio_guestbook', true)
            ->where('searchableParties.0.name', 'Sophie & Marc')
        );
});

/**
 * The whole point of the module is that a party may be sold without a gallery,
 * and therefore without a subdomain. Addressing the studio page by name would
 * leave exactly those parties unreachable.
 */
it('reaches the studio page of a party that has no subdomain', function () {
    $party = Party::factory()->audioOnly()->create(['display_name' => 'Sophie & Marc']);
    AudioGreeting::factory()->for($party)->pending()->create();

    $this->get("/admin/livre-or/{$party->id}")
        ->assertOk()
        ->assertInertia(fn ($page) => $page->where('party.name', 'Sophie & Marc'));
});

it('updates and deletes a party that has no subdomain', function () {
    $party = Party::factory()->audioOnly()->create();

    $this->put(route('admin.parties.update', $party), ['display_name' => 'Renommée'])
        ->assertRedirect();

    expect($party->fresh()->display_name)->toBe('Renommée');

    $this->delete(route('admin.parties.destroy', $party))->assertRedirect();

    expect(Party::query()->find($party->id))->toBeNull();
});
