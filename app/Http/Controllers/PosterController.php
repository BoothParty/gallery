<?php

namespace App\Http\Controllers;

use App\Models\Party;
use App\Services\PartyPosterGenerator;
use App\Services\PosterBatchLink;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PosterController extends Controller
{
    /**
     * List the posters of a batch, for the phone that scanned its QR code.
     *
     * The signature guarantees the list of parties, and its expiry is reused to
     * sign each poster so that page and files die together.
     */
    public function index(Request $request, PosterBatchLink $posterBatchLink): Response
    {
        $expiresAt = now()->setTimestamp($request->integer('expires'));

        return Inertia::render('party/posters', [
            'posters' => $posterBatchLink->posters(
                $posterBatchLink->parseIds($request->string('parties')->toString()),
                $expiresAt,
            ),
        ]);
    }

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
