<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePartyRequest;
use App\Http\Requests\UpdatePartyRequest;
use App\Http\Resources\PartyResource;
use App\Models\Party;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PartyController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return PartyResource::collection(Party::query()->latest()->get());
    }

    public function store(StorePartyRequest $request): PartyResource
    {
        $party = Party::query()->create([
            'name' => $request->validated('name'),
            'user_id' => $request->user()->id,
            'expire_at' => $request->validated('expire_at'),
        ]);

        return new PartyResource($party);
    }

    public function show(Party $party): PartyResource
    {
        return new PartyResource($party);
    }

    public function update(UpdatePartyRequest $request, Party $party): PartyResource
    {
        $party->update($request->validated());

        return new PartyResource($party);
    }

    public function destroy(Party $party): Response
    {
        $party->delete();

        return response()->noContent();
    }
}
