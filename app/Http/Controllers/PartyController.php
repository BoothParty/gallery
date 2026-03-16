<?php

namespace App\Http\Controllers;

use App\Http\Resources\PartyResource;
use App\Http\Resources\PictureResource;
use App\Models\Party;
use Inertia\Inertia;
use Inertia\Response;

class PartyController extends Controller
{
    public const PICTURES_PER_PAGE = 30;

    public function show(Party $party): Response
    {
        $hidePictures = $party->hidePictures();

        if ($hidePictures) {
            $pictures = [];
            $page = 1;
            $pagesCount = 1;
        } else {
            $picturesPaginator = $party->pictures()
                ->latest()
                ->paginate(self::PICTURES_PER_PAGE);

            $pictures = PictureResource::collection($picturesPaginator)->resolve();
            $page = $picturesPaginator->currentPage();
            $pagesCount = max(1, $picturesPaginator->lastPage());
        }

        return Inertia::render('party/gallery', [
            'party' => PartyResource::make($party)->resolve(),
            'pictures' => $pictures,
            'page' => $page,
            'pagesCount' => $pagesCount,
            'hidePictures' => $hidePictures,
        ]);
    }
}
