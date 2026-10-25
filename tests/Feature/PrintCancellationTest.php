<?php

use App\Enums\PartyActivityType;
use App\Models\Party;
use App\Models\Picture;
use App\Models\PrintJob;

it('cancels every pending print of the picture', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create();
    PrintJob::factory()->count(2)->for($picture)->create();

    $this->withSession(hostSession($party))
        ->delete(route('party.pictures.prints.destroy', [$party, $picture]))
        ->assertRedirect();

    expect($picture->printJobs()->count())->toBe(0);
});

it('keeps the prints the booth already made', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create();
    PrintJob::factory()->for($picture)->create();
    $printed = PrintJob::factory()->for($picture)->printed()->create();

    $this->withSession(hostSession($party))
        ->delete(route('party.pictures.prints.destroy', [$party, $picture]));

    expect($picture->printJobs()->pluck('id')->all())->toBe([$printed->id]);
});

it('leaves the other pictures queued', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create();
    PrintJob::factory()->for($picture)->create();
    $other = PrintJob::factory()->for(Picture::factory()->for($party))->create();

    $this->withSession(hostSession($party))
        ->delete(route('party.pictures.prints.destroy', [$party, $picture]));

    $this->assertModelExists($other);
});

it('records the cancellation in the activity feed', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create();
    PrintJob::factory()->count(2)->for($picture)->create();

    $this->withSession(hostSession($party))
        ->delete(route('party.pictures.prints.destroy', [$party, $picture]));

    $activity = $party->activities()->sole();

    expect($activity->type)->toBe(PartyActivityType::PrintsCancelled)
        ->and($activity->properties['count'])->toBe(1);
});

it('does nothing when no print is pending anymore', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create();
    PrintJob::factory()->for($picture)->printed()->create();

    $this->withSession(hostSession($party))
        ->delete(route('party.pictures.prints.destroy', [$party, $picture]))
        ->assertRedirect();

    expect($picture->printJobs()->count())->toBe(1)
        ->and($party->activities()->exists())->toBeFalse();
});

it('refuses a plain visitor', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create();
    PrintJob::factory()->for($picture)->create();

    $this->delete(route('party.pictures.prints.destroy', [$party, $picture]))
        ->assertForbidden();

    expect($picture->printJobs()->count())->toBe(1);
});

it('refuses a picture from another party', function () {
    $mine = Party::factory()->create();
    $picture = Picture::factory()->create();
    PrintJob::factory()->for($picture)->create();

    $this->withSession(hostSession($mine))
        ->delete(route('party.pictures.prints.destroy', [$mine, $picture]))
        ->assertNotFound();

    expect($picture->printJobs()->count())->toBe(1);
});
