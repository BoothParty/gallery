<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePartyRequest;
use App\Models\Party;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

class PartyController extends Controller
{
    public function index(): Response
    {
        $parties = Party::query()
            ->with('owner')
            ->orderBy('created_at', 'desc')
            ->get()
            ->map(fn ($party) => [
                'id' => $party->id,
                'name' => $party->name,
                'expiration_date' => $party->expiration_date?->format('Y-m-d H:i:s'),
                'owned_by' => $party->owned_by,
                'owner_name' => $party->owner?->name,
                'pictures_count' => $party->pictures()->count(),
                'created_at' => $party->created_at->format('Y-m-d H:i:s'),
            ]);

        return Inertia::render('parties', [
            'parties' => $parties,
        ]);
    }

    public function update(UpdatePartyRequest $request, Party $party): RedirectResponse
    {
        $party->update($request->validated());

        return redirect()->back()->with('success', 'Party updated successfully.');
    }

    public function destroy(Party $party): RedirectResponse
    {
        $name = $party->name;
        $party->delete();

        return redirect()->back()->with('success', "Party {$name} has been deleted.");
    }
}
