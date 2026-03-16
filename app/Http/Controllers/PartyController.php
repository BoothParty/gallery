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
        $pictures = $party->pictures()
            ->latest()
            ->paginate(self::PICTURES_PER_PAGE);

        return Inertia::render('party/gallery', [
            'party' => PartyResource::make($party)->resolve(),
            'pictures' => PictureResource::collection($pictures)->resolve(),
            'page' => $pictures->currentPage(),
            'pagesCount' => max(1, $pictures->lastPage()),
            'hidePictures' => $party->hidePictures(),
        ]);
    }
}
