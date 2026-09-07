<?php

use App\Models\Party;
use App\Models\Picture;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(function () {
    Storage::fake('s3');
});

it('returns the picture count and the last change', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create();

    $this->getJson(route('api.parties.state', $party))
        ->assertOk()
        ->assertExactJson([
            'count' => 1,
            'last_change' => $picture->updated_at->toIso8601String(),
        ]);
});

it('returns zeros for an empty gallery', function () {
    $party = Party::factory()->create();

    $this->getJson(route('api.parties.state', $party))
        ->assertOk()
        ->assertExactJson(['count' => 0, 'last_change' => null]);
});

it('returns zeros for an expired party', function () {
    $party = Party::factory()->expired()->create();
    Picture::factory()->count(3)->for($party)->create();

    $this->getJson(route('api.parties.state', $party))
        ->assertOk()
        ->assertExactJson(['count' => 0, 'last_change' => null]);
});

it('needs no authentication', function () {
    $party = Party::factory()->create();

    $this->getJson(route('api.parties.state', $party))->assertOk();
});

it('reflects a new picture on the next call', function () {
    $party = Party::factory()->create();

    $this->getJson(route('api.parties.state', $party))->assertJsonPath('count', 0);

    Picture::factory()->for($party)->create();

    $this->getJson(route('api.parties.state', $party))->assertJsonPath('count', 1);
});

it('throttles repeated state requests', function () {
    $party = Party::factory()->create();

    foreach (range(1, 600) as $ignored) {
        $this->getJson(route('api.parties.state', $party))->assertOk();
    }

    $this->getJson(route('api.parties.state', $party))->assertStatus(429);
});
