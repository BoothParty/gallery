<?php

use App\Models\Party;
use App\Models\User;
use App\Services\PartySources\PartySourceEvent;
use App\Services\PartySources\StubPartySource;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

// ── Ce que la source expose ─────────────────────────────────────────────────

it('carries the modules and the client address from the source', function () {
    $event = new PartySourceEvent(
        id: 'evt-1',
        name: 'Camille & Antoine',
        startsAt: CarbonImmutable::parse('2026-06-12 18:00'),
        clientEmail: 'camille@example.test',
        hasGallery: false,
        hasAudioGuestbook: true,
    );

    expect($event->toArray(new StubPartySource))
        ->client_email->toBe('camille@example.test')
        ->has_gallery->toBeFalse()
        ->has_audio_guestbook->toBeTrue();
});

it('defaults an event to the gallery alone', function () {
    $event = new PartySourceEvent(id: 'evt-2', name: 'Sans options');

    expect($event->toArray(new StubPartySource))
        ->has_gallery->toBeTrue()
        ->has_audio_guestbook->toBeFalse();
});

// ── Création en masse ───────────────────────────────────────────────────────

it('creates a party with the modules the source reported', function () {
    $this->post('/admin/parties/bulk', [
        'parties' => [[
            'name' => 'camille-antoine',
            'client_email' => 'camille@example.test',
            'event_at' => '2026-06-12T18:00:00+02:00',
            'has_gallery' => true,
            'has_audio_guestbook' => true,
        ]],
    ])->assertRedirect();

    $party = Party::query()->firstOrFail();

    expect($party)
        ->client_email->toBe('camille@example.test')
        ->has_gallery->toBeTrue()
        ->has_audio_guestbook->toBeTrue()
        ->and($party->event_at->toDateString())->toBe('2026-06-12');
});

/**
 * One event, one end of life: the recordings and the pictures come off the
 * shelves together, ninety days after the party.
 */
it('sets the expiry ninety days after the event', function () {
    $this->post('/admin/parties/bulk', [
        'parties' => [[
            'name' => 'camille-antoine',
            'event_at' => '2026-06-12T18:00:00+02:00',
            'has_gallery' => true,
            'has_audio_guestbook' => false,
        ]],
    ])->assertRedirect();

    expect(Party::query()->firstOrFail()->expire_at->toDateString())->toBe('2026-09-10');
});

it('creates an audio-only party without a subdomain', function () {
    $this->post('/admin/parties/bulk', [
        'parties' => [[
            'name' => null,
            'client_email' => 'sophie@example.test',
            'event_at' => '2026-07-04T17:00:00+02:00',
            'has_gallery' => false,
            'has_audio_guestbook' => true,
        ]],
    ])->assertRedirect();

    $party = Party::query()->firstOrFail();

    expect($party->name)->toBeNull()
        ->and($party->hasSubdomain())->toBeFalse()
        ->and($party->client_uuid)->not->toBeNull();
});

it('refuses a bulk creation using a reserved name', function () {
    $this->post('/admin/parties/bulk', [
        'parties' => [[
            'name' => 'audiobook',
            'has_gallery' => true,
            'has_audio_guestbook' => false,
        ]],
    ])->assertSessionHasErrors('parties.0.name');

    expect(Party::query()->count())->toBe(0);
});

it('refuses a bulk creation with an invalid client address', function () {
    $this->post('/admin/parties/bulk', [
        'parties' => [[
            'name' => 'camille-antoine',
            'client_email' => 'pas-une-adresse',
            'has_gallery' => true,
            'has_audio_guestbook' => false,
        ]],
    ])->assertSessionHasErrors('parties.0.client_email');
});

// ── Fiche party ─────────────────────────────────────────────────────────────

it('sends the modules and the client link to the parties page', function () {
    $party = Party::factory()->audioOnly()->create(['client_email' => 'sophie@example.test']);

    $this->get(route('admin.parties.index'))
        ->assertInertia(fn ($page) => $page
            ->where('editableParties.0.client_email', 'sophie@example.test')
            ->where('editableParties.0.has_gallery', false)
            ->where('editableParties.0.has_audio_guestbook', true)
            ->where('editableParties.0.audiobook_url', $party->audiobookUrl())
        );
});

it('saves the client address and the event date from the party form', function () {
    $party = Party::factory()->create(['name' => 'camille-antoine']);

    $this->put(route('admin.parties.update', $party), [
        'name' => 'camille-antoine',
        'client_email' => 'camille@example.test',
        'event_at' => '2026-06-12T18:00:00+02:00',
    ])->assertRedirect();

    expect($party->fresh())
        ->client_email->toBe('camille@example.test')
        ->and($party->fresh()->event_at->toDateString())->toBe('2026-06-12');
});

// ── Révocation ──────────────────────────────────────────────────────────────

it('revokes the client link by minting a new uuid', function () {
    $party = Party::factory()->withAudioGuestbook()->create(['link_sent_at' => now()]);
    $before = $party->client_uuid;

    $this->post(route('admin.parties.audiobook-revoke', $party))->assertRedirect();

    expect($party->fresh()->client_uuid)->not->toBe($before)
        ->and($party->fresh()->link_sent_at)->toBeNull();

    $this->get('/audiobook/'.$before)->assertNotFound();
});

it('leaves the gallery uuid untouched when revoking the client link', function () {
    $party = Party::factory()->withAudioGuestbook()->create();
    $galleryUuid = $party->gallery_uuid;

    $this->post(route('admin.parties.audiobook-revoke', $party))->assertRedirect();

    expect($party->fresh()->gallery_uuid)->toBe($galleryUuid);
});
