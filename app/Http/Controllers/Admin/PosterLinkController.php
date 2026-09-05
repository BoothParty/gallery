<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PosterLinkRequest;
use App\Services\PosterBatchLink;
use Illuminate\Http\JsonResponse;

class PosterLinkController extends Controller
{
    /**
     * The signed link and QR code opening the posters of a selection.
     */
    public function show(PosterLinkRequest $request, PosterBatchLink $posterBatchLink): JsonResponse
    {
        return response()->json($posterBatchLink->forParties($request->partyIds()));
    }
}
