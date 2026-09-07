<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ResolvePartyHost;
use App\Http\Resources\PartyResource;
use App\Http\Resources\PictureResource;
use App\Models\Party;
use App\Services\PartyPicturesState;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class PartyController extends Controller
{
    public const PICTURES_PER_PAGE = 24;

    /**
     * `picturesState` is the reference the page's polling starts from. It has
     * to travel with the render rather than be established by the first poll:
     * a picture arriving between the two would otherwise be absorbed into the
     * reference and never displayed.
     */
    public function show(Request $request, Party $party, PartyPicturesState $partyPicturesState): Response
    {
        $hidePictures = $party->hidePictures();
        $picturesPage = $this->pictures($party, $hidePictures);

        return Inertia::render('party/gallery', [
            'party' => PartyResource::make($party)->resolve(),
            'pictures' => Inertia::scroll(
                fn () => PictureResource::collection($picturesPage)
            ),
            'picturesCount' => $picturesPage->total(),
            'picturesState' => $partyPicturesState->for($party),
            'refresh' => [
                'hot' => (int) config('party.refresh.hot'),
                'warm' => (int) config('party.refresh.warm'),
                'cold' => (int) config('party.refresh.cold'),
                'warm_after' => (int) config('party.refresh.warm_after'),
                'cold_after' => (int) config('party.refresh.cold_after'),
            ],
            'hidePictures' => $hidePictures,
            'canEdit' => ResolvePartyHost::isHost($request, $party),
            'maxDownloadPictures' => (int) config('party.download.max_pictures'),
            'csrfToken' => csrf_token(),
        ]);
    }

    /**
     * An expired party resolves to an empty paginator rather than to a
     * different prop shape, so the page always receives the same structure.
     */
    private function pictures(Party $party, bool $hidePictures): LengthAwarePaginatorContract
    {
        if ($hidePictures) {
            return new LengthAwarePaginator([], 0, self::PICTURES_PER_PAGE);
        }

        return $party->pictures()
            ->latest()
            ->paginate(self::PICTURES_PER_PAGE);
    }
}
