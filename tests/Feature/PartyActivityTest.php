<?php

use App\Enums\PartyActivityType;
use App\Models\Party;
use App\Models\PartyActivity;
use App\Models\Picture;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    Storage::fake('s3');
    Queue::fake();
});

// ── Recording ───────────────────────────────────────────────────────────────

it('records an activity with its properties', function () {
    $party = Party::factory()->create();

    $activity = $party->recordActivity(PartyActivityType::PicturesUploaded, ['count' => 3]);

    expect($activity->type)->toBe(PartyActivityType::PicturesUploaded)
        ->and($activity->properties)->toBe(['count' => 3])
        ->and($party->activities()->count())->toBe(1);
});

it('folds a burst of the same activity into one entry', function () {
    $party = Party::factory()->create();

    $party->recordActivity(PartyActivityType::PicturesImported, ['count' => 1]);
    $this->travel(10)->minutes();
    $party->recordActivity(PartyActivityType::PicturesImported, ['count' => 1]);

    expect($party->activities()->sole()->properties)->toBe(['count' => 2]);
});

it('starts a new entry once the burst is over', function () {
    $party = Party::factory()->create();

    $party->recordActivity(PartyActivityType::PicturesImported, ['count' => 1]);
    $this->travel(16)->minutes();
    $party->recordActivity(PartyActivityType::PicturesImported, ['count' => 1]);

    expect($party->activities()->count())->toBe(2);
});

it('never folds downloads, which the download stats count one by one', function () {
    $party = Party::factory()->create();

    $party->recordActivity(PartyActivityType::PicturesDownloaded, ['count' => 1]);
    $party->recordActivity(PartyActivityType::PicturesDownloaded, ['count' => 5]);

    expect($party->activities()->count())->toBe(2);
});

it('describes each activity in plain words', function (PartyActivityType $type, array $properties, string $description) {
    expect($type->description($properties))->toBe($description);
})->with([
    [PartyActivityType::PicturesImported, ['count' => 1], '1 photo importée depuis la borne'],
    [PartyActivityType::PicturesImported, ['count' => 18], '18 photos importées depuis la borne'],
    [PartyActivityType::PicturesUploaded, ['count' => 4], '4 photos ajoutées depuis l\'administration'],
    [PartyActivityType::PicturesHidden, ['count' => 3], '3 photos masquées'],
    [PartyActivityType::PicturesShown, ['count' => 1], '1 photo remise en ligne'],
    [PartyActivityType::PicturesDownloaded, ['count' => 12], '12 photos téléchargées'],
    [PartyActivityType::MetadataUpdated, [], 'Présentation de la galerie modifiée'],
    [PartyActivityType::PrintsRequested, ['count' => 1], '1 photo envoyée à l\'impression'],
    [PartyActivityType::PrintsRequested, ['count' => 3], '3 photos envoyées à l\'impression'],
    [PartyActivityType::PicturesPrinted, ['count' => 1], '1 photo réimprimée'],
    [PartyActivityType::PicturesPrinted, ['count' => 3], '3 photos réimprimées'],
]);

it('lists and folds the print activities', function (PartyActivityType $type) {
    expect($type->isListed())->toBeTrue()
        ->and($type->foldWithin()->totalMinutes)->toBe(15.0);
})->with([PartyActivityType::PrintsRequested, PartyActivityType::PicturesPrinted]);

it('removes the activities with their party', function () {
    $party = Party::factory()->create();
    $party->recordActivity(PartyActivityType::MetadataUpdated);

    $party->delete();

    expect(PartyActivity::query()->count())->toBe(0);
});

// ── Actions that record ─────────────────────────────────────────────────────

it('records pictures sent by the booth', function () {
    Sanctum::actingAs(User::factory()->create());
    $party = Party::factory()->create();

    $this->postJson(route('pictures.store', $party), ['photo' => UploadedFile::fake()->image('a.jpg')])->assertCreated();
    $this->postJson(route('pictures.store', $party), ['photo' => UploadedFile::fake()->image('b.jpg')])->assertCreated();

    expect($party->activities()->sole())
        ->type->toBe(PartyActivityType::PicturesImported)
        ->properties->toBe(['count' => 2]);
});

it('records pictures uploaded from administration', function () {
    $this->actingAs(User::factory()->create());
    $party = Party::factory()->create();

    $this->post(route('party.dashboard.pictures.store', $party), [
        'photos' => [UploadedFile::fake()->image('a.jpg'), UploadedFile::fake()->image('b.jpg')],
    ]);

    expect($party->activities()->sole())
        ->type->toBe(PartyActivityType::PicturesUploaded)
        ->properties->toBe(['count' => 2]);
});

it('records a picture taken off and put back on the gallery', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->create();
    $host = ['party_host' => [$party->id => true]];

    $this->withSession($host)->patch(route('party.pictures.visibility', [$party, $picture]), ['hidden' => true]);
    $this->withSession($host)->patch(route('party.pictures.visibility', [$party, $picture]), ['hidden' => false]);

    expect($party->activities()->pluck('type')->all())
        ->toBe([PartyActivityType::PicturesHidden, PartyActivityType::PicturesShown]);
});

it('records nothing when the visibility does not change', function () {
    $party = Party::factory()->create();
    $picture = Picture::factory()->for($party)->hidden()->create();

    $this->withSession(['party_host' => [$party->id => true]])
        ->patch(route('party.pictures.visibility', [$party, $picture]), ['hidden' => true]);

    expect($party->activities()->count())->toBe(0);
});

it('records a download with the number of pictures', function () {
    $party = Party::factory()->create();
    $pictures = Picture::factory()->for($party)->count(2)->create();
    $pictures->each(fn (Picture $picture) => Storage::disk('s3')->put($picture->path, 'jpeg'));

    $this->post(route('party.download', $party), ['ids' => $pictures->pluck('id')->all()])->assertOk();

    expect($party->activities()->sole())
        ->type->toBe(PartyActivityType::PicturesDownloaded)
        ->properties->toBe(['count' => 2]);
});

it('records the host editing the gallery presentation', function () {
    $party = Party::factory()->create();

    $this->withSession(['party_host' => [$party->id => true]])
        ->post(route('party.metadata.update', $party), [
            'display_name' => 'Camille & Antoine',
            'banner_position_x' => 50,
            'banner_position_y' => 50,
            'banner_zoom' => 1,
        ]);

    expect($party->activities()->sole()->type)->toBe(PartyActivityType::MetadataUpdated);
});

it('records an administrator editing the gallery presentation, not its settings', function () {
    $this->actingAs(User::factory()->create());
    $party = Party::factory()->create(['name' => 'alpha']);

    $this->put(route('admin.parties.update', $party), ['name' => 'beta']);
    expect($party->activities()->count())->toBe(0);

    $this->put(route('admin.parties.update', $party->refresh()), ['name' => 'beta', 'description' => 'Domaine des Ormes']);
    expect($party->activities()->sole()->type)->toBe(PartyActivityType::MetadataUpdated);
});
