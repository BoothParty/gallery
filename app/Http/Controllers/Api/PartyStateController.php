<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Party;
use App\Services\PartyPicturesState;
use Illuminate\Http\JsonResponse;

class PartyStateController extends Controller
{
    /**
     * The signal an open gallery polls to know whether its content moved.
     */
    public function __invoke(Party $party, PartyPicturesState $partyPicturesState): JsonResponse
    {
        return response()->json($partyPicturesState->for($party));
    }
}
