<?php

use App\Models\AudioGreeting;
use App\Models\AudioMessage;
use App\Models\Party;

/**
 * The microphone cannot be granted to a headless browser, so the recording
 * itself stays out of reach here. What this covers is everything around it:
 * the walkthrough, the state panels and the step counter.
 */
function audiobookUrlFor(Party $party): string
{
    return route('audiobook.show', $party->client_uuid);
}

it('walks a first-time client through the three steps', function () {
    $party = Party::factory()->withAudioGuestbook()->create(['display_name' => 'Camille & Antoine']);

    $page = browserPage(fn () => visit(audiobookUrlFor($party)));

    $page->assertPresent('[data-onboarding]')
        ->assertSee('Trois étapes, cinq minutes')
        ->assertSee('Le studio le valide')
        ->assertNoJavaScriptErrors();
});

it('does not walk the client through it a second time', function () {
    $party = Party::factory()->withAudioGuestbook()->create();

    $page = browserPage(fn () => visit(audiobookUrlFor($party)));

    $page->click('[data-onboarding-dismiss]')
        ->assertMissing('[data-onboarding]');

    $page->navigate(audiobookUrlFor($party))
        ->assertPresent('[data-reopen-onboarding]')
        ->assertMissing('[data-onboarding]')
        ->assertNoJavaScriptErrors();
});

it('reopens the walkthrough on demand', function () {
    $party = Party::factory()->withAudioGuestbook()->create();

    $page = browserPage(fn () => visit(audiobookUrlFor($party)));

    $page->click('[data-onboarding-dismiss]')
        ->assertMissing('[data-onboarding]')
        ->click('[data-reopen-onboarding]')
        ->assertPresent('[data-onboarding]')
        ->assertNoJavaScriptErrors();
});

it('tells a client whose greeting is waiting for the studio', function () {
    $party = Party::factory()->withAudioGuestbook()->create();
    AudioGreeting::factory()->for($party)->pending()->create();

    $page = browserPage(fn () => visit(audiobookUrlFor($party)));

    $page->click('[data-onboarding-dismiss]')
        ->assertPresent('[data-greeting-status="pending"]')
        ->assertSee('Message en relecture au studio')
        ->assertSee('En attente de validation')
        ->assertNoJavaScriptErrors();
});

it('shows the studio reason to a client whose greeting was refused', function () {
    $party = Party::factory()->withAudioGuestbook()->create();
    AudioGreeting::factory()->for($party)->rejected('Le son est saturé, reculez du micro.')->create();

    $page = browserPage(fn () => visit(audiobookUrlFor($party)));

    $page->click('[data-onboarding-dismiss]')
        ->assertPresent('[data-greeting-status="rejected"]')
        ->assertSee('Votre message est à refaire')
        ->assertSee('Le son est saturé, reculez du micro.')
        ->assertNoJavaScriptErrors();
});

it('offers a validated greeting back for changes', function () {
    $party = Party::factory()->withAudioGuestbook()->create();
    AudioGreeting::factory()->for($party)->validated()->create();

    $page = browserPage(fn () => visit(audiobookUrlFor($party)));

    $page->click('[data-onboarding-dismiss]')
        ->assertPresent('[data-greeting-status="validated"]')
        ->assertSee('Validé par le studio')
        ->click('[data-greeting-status="validated"] button')
        ->assertMissing('[data-greeting-status]')
        ->assertSee('Enregistrez le message que vos invités entendront')
        ->assertNoJavaScriptErrors();
});

it('lists the guests messages once the studio has uploaded them', function () {
    $party = Party::factory()->withAudioGuestbook()->create();
    AudioMessage::factory()->for($party)->create(['title' => 'Mamie Jeanne', 'position' => 1]);
    AudioMessage::factory()->for($party)->create(['title' => null, 'position' => 2]);

    $page = browserPage(fn () => visit(audiobookUrlFor($party)));

    $page->click('[data-onboarding-dismiss]')
        ->assertPresent('[data-guest-messages]')
        ->assertSee('Les messages de vos invités')
        ->assertSee('Mamie Jeanne')
        ->assertSee('Message 02')
        ->assertSee('Tout télécharger')
        ->assertNoJavaScriptErrors();
});

it('hides the messages section while the studio has uploaded nothing', function () {
    $party = Party::factory()->withAudioGuestbook()->create();

    $page = browserPage(fn () => visit(audiobookUrlFor($party)));

    $page->click('[data-onboarding-dismiss]')
        ->assertMissing('[data-guest-messages]')
        ->assertNoJavaScriptErrors();
});

it('renames a message from the client space', function () {
    $party = Party::factory()->withAudioGuestbook()->create();
    $message = AudioMessage::factory()->for($party)->create(['title' => null, 'position' => 3]);

    $page = browserPage(fn () => visit(audiobookUrlFor($party)));

    $page->click('[data-onboarding-dismiss]')
        ->assertSee('Message 03')
        ->click('[data-rename="'.$message->id.'"]')
        ->type('[data-message="'.$message->id.'"] input', 'Théo (5 ans)')
        ->keys('[data-message="'.$message->id.'"] input', ['Enter'])
        ->assertSee('Théo (5 ans)')
        ->assertNoJavaScriptErrors();

    expect($message->fresh()->title)->toBe('Théo (5 ans)');
});
