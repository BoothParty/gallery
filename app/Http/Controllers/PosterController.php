<?php

namespace App\Http\Controllers;

use App\Models\Party;
use App\Services\PartyPosterGenerator;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Str;

class PosterController extends Controller
{
    /**
     * Stream the poster of a party to whoever holds a signed link.
     *
     * The disposition is inline so that the page can fetch the bytes for the
     * share sheet, and so that opening the link shows the poster.
     */
    public function file(Party $party, PartyPosterGenerator $partyPosterGenerator): HttpResponse
    {
        $filename = Str::slug($party->name).'-affiche.png';

        return response($partyPosterGenerator->generate($party), 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'inline; filename="'.$filename.'"',
        ]);
    }
}
