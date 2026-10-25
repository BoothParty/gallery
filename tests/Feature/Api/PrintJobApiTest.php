<?php

use App\Enums\PartyActivityType;
use App\Models\Party;
use App\Models\Picture;
use App\Models\PrintJob;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

it('rejects unauthenticated requests', function () {
    $party = Party::factory()->create();
    $job = PrintJob::factory()->for(Picture::factory()->for($party))->create();

    $this->getJson(route('print-jobs.index', $party))->assertUnauthorized();
    $this->postJson(route('print-jobs.printed', ['party' => $party, 'printJob' => $job]))->assertUnauthorized();
});

it('lists the pending jobs oldest first', function () {
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->create();
    $first = PrintJob::factory()->for(Picture::factory()->for($party))->create();
    $second = PrintJob::factory()->for(Picture::factory()->for($party))->create();
    PrintJob::factory()->printed()->for(Picture::factory()->for($party))->create();

    $response = $this->getJson(route('print-jobs.index', $party))->assertOk();

    expect($response->json('data.*.id'))->toBe([$first->id, $second->id]);
    $response->assertJsonPath('data.0.picture_id', $first->picture_id)
        ->assertJsonPath('data.0.url', $first->picture->url());
});

it('lists the jobs of hidden pictures', function () {
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->create();
    PrintJob::factory()->for(Picture::factory()->hidden()->for($party))->create();

    $this->getJson(route('print-jobs.index', $party))->assertOk()->assertJsonCount(1, 'data');
});

it('leaves out other parties jobs', function () {
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->create();
    PrintJob::factory()->for(Picture::factory()->for(Party::factory()))->create();

    $this->getJson(route('print-jobs.index', $party))->assertOk()->assertJsonCount(0, 'data');
});

it('marks a job printed and logs it', function () {
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->create();
    $job = PrintJob::factory()->for(Picture::factory()->for($party))->create();

    $this->postJson(route('print-jobs.printed', ['party' => $party, 'printJob' => $job]))->assertOk();

    expect($job->fresh()->printed_at)->not->toBeNull();
    $activity = $party->activities()->where('type', PartyActivityType::PicturesPrinted)->sole();
    expect($activity->properties['count'])->toBe(1);
});

it('ignores a second confirmation', function () {
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->create();
    $job = PrintJob::factory()->for(Picture::factory()->for($party))->create();
    $url = route('print-jobs.printed', ['party' => $party, 'printJob' => $job]);

    $this->postJson($url)->assertOk();
    $printedAt = $job->fresh()->printed_at;
    $this->travel(1)->minutes();
    $this->postJson($url)->assertOk();

    expect($job->fresh()->printed_at->equalTo($printedAt))->toBeTrue();
    $activity = $party->activities()->where('type', PartyActivityType::PicturesPrinted)->sole();
    expect($activity->properties['count'])->toBe(1);
});

it('refuses a job of another party', function () {
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->create();
    $job = PrintJob::factory()->for(Picture::factory()->for(Party::factory()))->create();

    $this->postJson(route('print-jobs.printed', ['party' => $party, 'printJob' => $job]))->assertNotFound();

    expect($job->fresh()->printed_at)->toBeNull();
});
