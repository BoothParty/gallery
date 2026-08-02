<?php

use App\Models\Party;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Matches a stylesheet entry in the rendered head, in both Vite dev mode
 * (resources/css/name.css) and built mode (/build/assets/name-HASH.css).
 */
function stylesheetPattern(string $name): string
{
    return '#(resources/css/'.$name.'\.css|/build/assets/'.$name.'-[^"\']+\.css)#';
}

it('serves the party stylesheet without shadcn on the public gallery', function () {
    $party = Party::factory()->create();

    $content = $this->get(route('party.gallery', $party))->assertOk()->getContent();

    expect($content)
        ->toMatch(stylesheetPattern('app'))
        ->not->toMatch(stylesheetPattern('admin'));
});

it('serves the shadcn stylesheet on the party dashboard', function () {
    $party = Party::factory()->create();

    $content = $this->get(route('party.dashboard.show', $party))->assertOk()->getContent();

    expect($content)->toMatch(stylesheetPattern('admin'));
});

it('serves the shadcn stylesheet on the admin panel', function () {
    $content = $this->get(route('admin.parties.index'))->assertOk()->getContent();

    expect($content)->toMatch(stylesheetPattern('admin'));
});

it('keeps shadcn out of the javascript entry chunk shared with the gallery', function () {
    $manifestPath = public_path('build/manifest.json');

    if (! file_exists($manifestPath)) {
        $this->markTestSkipped('Vite manifest is missing; run npm run build first.');
    }

    $manifest = json_decode(file_get_contents($manifestPath), associative: true);
    $entry = file_get_contents(public_path('build/'.$manifest['resources/js/app.tsx']['file']));

    expect($entry)
        ->not->toContain('SidebarProvider')
        ->not->toContain('radix');
});
