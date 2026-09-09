<?php

use App\Models\Party;
use App\Models\User;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->actingAs(User::factory()->create());
});

it('can search parties in the switcher including expired ones', function () {
    $currentParty = Party::factory()->create(['name' => 'Alpha Party']);
    Party::factory()->create(['name' => 'Beta Party']);
    $expiredParty = Party::factory()->expired()->create(['name' => 'Zeta Expired']);

    $page = browserPage(fn () => visit(route('party.dashboard.show', $currentParty)));

    $page->click($currentParty->name)
        ->assertSee('Parties')
        ->assertDontSee($expiredParty->name)
        ->fill('input[aria-label="Search parties"]', 'Zeta')
        ->assertSee($expiredParty->name)
        ->assertSee('Expired')
        ->assertDontSee('Beta Party')
        ->assertNoJavaScriptErrors();
});

it('shows the gallery audience once umami has answered', function () {
    Http::fake(function (Request $request) {
        return match (true) {
            str_contains($request->url(), '/auth/login') => Http::response(['token' => 'a-token']),
            str_contains($request->url(), '/stats') => Http::response([
                'visitors' => ['value' => 42],
                'visits' => ['value' => 50],
                'pageviews' => ['value' => 130],
                'totaltime' => ['value' => 4000],
            ]),
            str_contains($request->url(), '/pageviews') => Http::response([
                'sessions' => [['x' => '2026-09-09 21:00:00', 'y' => 30]],
            ]),
            str_contains($request->url(), 'type=event') => Http::response([['x' => 'photo_open', 'y' => 88]]),
            str_contains($request->url(), 'type=referrer') => Http::response([['x' => 'whatsapp.com', 'y' => 12]]),
            default => Http::response([['x' => 'mobile', 'y' => 45]]),
        };
    });

    config()->set('services.umami', [
        'website_id' => 'the-website-id',
        'url' => 'https://umami.test',
        'username' => 'reader',
        'password' => 'secret',
        'timeout' => 5,
        'cache_ttl' => 300,
    ]);

    $page = browserPage(fn () => visit(route('party.dashboard.show', Party::factory()->create())));

    $page->assertSee('Audience')
        ->assertSee('Visiteurs')
        ->assertSee('42')
        ->assertSee('Photos ouvertes')
        ->assertSee('88')
        ->assertSee('Visites par heure')
        ->assertSee('whatsapp.com')
        ->assertSee('mobile')
        ->assertNoJavaScriptErrors();
});

it('says so when the stats are not configured', function () {
    config()->set('services.umami.url', null);

    $page = browserPage(fn () => visit(route('party.dashboard.show', Party::factory()->create())));

    $page->assertSee('Statistiques désactivées')
        ->assertNoJavaScriptErrors();
});
