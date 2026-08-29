<?php

namespace App\Http\Controllers;

use App\Http\Middleware\ResolvePartyHost;
use App\Http\Resources\PartyResource;
use App\Http\Resources\PictureResource;
use App\Models\Party;
use Illuminate\Contracts\Pagination\LengthAwarePaginator as LengthAwarePaginatorContract;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Inertia\Inertia;
use Inertia\Response;

class PartyController extends Controller
{
    public const PICTURES_PER_PAGE = 24;

    public function show(Request $request, Party $party): Response
    {
        $hidePictures = $party->hidePictures();
        $picturesPage = $this->pictures($party, $hidePictures);

        return Inertia::render('party/gallery', [
            'party' => PartyResource::make($party)->resolve(),
            'pictures' => Inertia::scroll(
                fn () => PictureResource::collection($picturesPage)
            ),
            'picturesCount' => $picturesPage->total(),
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
