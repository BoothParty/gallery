<?php

use App\Enums\PartyActivityType;
use App\Models\Party;
use App\Models\Picture;
use App\Models\PrintJob;

it('creates one pending job per selected picture', function () {
    $party = Party::factory()->create();
    $pictures = Picture::factory()->count(3)->for($party)->create();

    $this->withSession(hostSession($party))
        ->post(route('party.prints.store', $party), ['ids' => $pictures->pluck('id')->all()])
        ->assertRedirect();

    expect(PrintJob::pending()->count())->toBe(3);
    foreach ($pictures as $picture) {
        expect(PrintJob::where('picture_id', $picture->id)->exists())->toBeTrue();
    }
});

it('queues a second copy when a picture is asked again', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create();
    PrintJob::factory()->for($picture)->create();

    $this->withSession(hostSession($party))
        ->post(route('party.prints.store', $party), ['ids' => [$picture->id]])
        ->assertRedirect();

    expect(PrintJob::where('picture_id', $picture->id)->count())->toBe(2);
});

it('records the request in the activity feed', function () {
    $party = Party::factory()->create();
    $pictures = Picture::factory()->count(3)->for($party)->create();

    $this->withSession(hostSession($party))
        ->post(route('party.prints.store', $party), ['ids' => $pictures->pluck('id')->all()]);

    $activity = $party->activities()->where('type', PartyActivityType::PrintsRequested)->sole();
    expect($activity->properties['count'])->toBe(3);
});

it('lets the host reprint a hidden picture', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->hidden()->create();

    $this->withSession(hostSession($party))
        ->post(route('party.prints.store', $party), ['ids' => [$picture->id]])
        ->assertRedirect();

    expect(PrintJob::pending()->count())->toBe(1);
});

it('refuses a plain visitor', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create();

    $this->post(route('party.prints.store', $party), ['ids' => [$picture->id]])
        ->assertForbidden();

    expect(PrintJob::count())->toBe(0);
});

it('refuses a picture from another party', function () {
    $party = Party::factory()->create();
    $foreign = Picture::factory()->for(Party::factory())->create();

    $this->withSession(hostSession($party))
        ->post(route('party.prints.store', $party), ['ids' => [$foreign->id]])
        ->assertSessionHasErrors('ids.0');

    expect(PrintJob::count())->toBe(0);
});

it('refuses the same picture twice in one request', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create();

    $this->withSession(hostSession($party))
        ->post(route('party.prints.store', $party), ['ids' => [$picture->id, $picture->id]])
        ->assertSessionHasErrors('ids.1');

    expect(PrintJob::count())->toBe(0);
});

it('refuses more pictures than the cap', function () {
    config(['party.download.max_pictures' => 1]);
    $party = Party::factory()->create();
    $pictures = Picture::factory()->count(2)->for($party)->create();

    $this->withSession(hostSession($party))
        ->post(route('party.prints.store', $party), ['ids' => $pictures->pluck('id')->all()])
        ->assertSessionHasErrors('ids');

    expect(PrintJob::count())->toBe(0);
});

it('refuses an expired party', function () {
    $party = Party::factory()->create(['expire_at' => now()->subDay()]);
    $picture = Picture::factory()->for($party)->create();

    $this->withSession(hostSession($party))
        ->post(route('party.prints.store', $party), ['ids' => [$picture->id]])
        ->assertForbidden();

    expect(PrintJob::count())->toBe(0);
});
