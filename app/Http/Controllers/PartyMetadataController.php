<?php

namespace App\Http\Controllers;

use App\Enums\PartyActivityType;
use App\Http\Requests\UpdatePartyMetadataRequest;
use App\Models\Party;
use App\Services\PartyBannerStorage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Arr;

class PartyMetadataController extends Controller
{
    /**
     * Apply the host's edits to their own gallery.
     *
     * A removal wins over an upload sent in the same submission, as in
     * `Admin\PartyController::update`, so that two contradictory intents have a
     * settled outcome. Removing the cover also recentres the framing: an
     * absent picture has no framing worth keeping.
     */
    public function __invoke(
        UpdatePartyMetadataRequest $request,
        Party $party,
        PartyBannerStorage $partyBannerStorage,
    ): RedirectResponse {
        $party->update(Arr::only($request->validated(), [
            'display_name',
            'description',
            'banner_position_x',
            'banner_position_y',
            'banner_zoom',
        ]));

        if ($request->boolean('remove_banner')) {
            $partyBannerStorage->remove($party);

            $party->update([
                'banner_position_x' => 50,
                'banner_position_y' => 50,
                'banner_zoom' => 1,
            ]);
        } elseif ($request->hasFile('banner')) {
            $partyBannerStorage->store($party, $request->file('banner'));
        }

        $party->recordActivity(PartyActivityType::MetadataUpdated);

        return back();
    }
}
