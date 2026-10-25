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
        abort_if($request->routeIs('transfers.show') && ! $party->isTransfer(), 404);

        $isHost = ResolvePartyHost::isHost($request, $party);
        $hidePictures = $party->hidePictures();
        $picturesPage = $this->pictures($party, $hidePictures, $isHost);

        return Inertia::render('party/gallery', [
            'party' => PartyResource::make($party)->resolve(),
            'pictures' => Inertia::scroll(
                fn () => PictureResource::collection($picturesPage)
            ),
            'picturesCount' => $picturesPage->total(),
            'picturesState' => $partyPicturesState->for($party),
            'refresh' => config('party.refresh'),
            'hidePictures' => $hidePictures,
            'canEdit' => $isHost,
            'maxDownloadPictures' => (int) config('party.download.max_pictures'),
            'csrfToken' => csrf_token(),
        ]);
    }

    /**
     * An expired party resolves to an empty paginator rather than to a
     * different prop shape, so the page always receives the same structure.
     *
     * The host keeps the pictures they have hidden in their own grid, marked
     * as such, since they are the ones who decide to put them back.
     */
    private function pictures(Party $party, bool $hidePictures, bool $isHost): LengthAwarePaginatorContract
    {
        if ($hidePictures) {
            return new LengthAwarePaginator([], 0, self::PICTURES_PER_PAGE);
        }

        return $party->pictures()
            ->unless($isHost, fn ($query) => $query->visible())
            ->when($isHost, fn ($query) => $query->withPrintPending())
            ->latest()
            ->latest('id')
            ->paginate(self::PICTURES_PER_PAGE);
    }
}
