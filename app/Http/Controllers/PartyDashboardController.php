<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePartyMediaAvailabilityRequest;
use App\Models\Party;
use App\Models\Picture;
use App\Services\AdminDashboardDataService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PartyDashboardController extends Controller
{
    public function show(Party $party, AdminDashboardDataService $adminDashboardDataService): Response
    {
        return Inertia::render('dashboard', [
            ...$adminDashboardDataService->sidebarData($party->id),
        ]);
    }

    public function pictures(Request $request, Party $party, AdminDashboardDataService $adminDashboardDataService): Response
    {
        $allowedPerPage = [24, 48, 72, 96];
        $perPage = in_array($request->integer('per_page', 24), $allowedPerPage)
            ? $request->integer('per_page', 24)
            : 24;

        $pictures = $party->pictures()
            ->latest()
            ->paginate($perPage)
            ->through(fn (Picture $picture) => [
                'id' => $picture->id,
                'url' => $picture->url(),
                'thumbnailUrl' => $picture->thumbnailUrl(),
                'width' => $picture->width,
                'height' => $picture->height,
            ]);

        return Inertia::render('admin/gallery/index', [
            ...$adminDashboardDataService->sidebarData($party->id),
            'party' => [
                'id' => $party->id,
                'name' => $party->name,
            ],
            'pictures' => $pictures,
            'perPage' => $perPage,
        ]);
    }

    public function enableMedia(UpdatePartyMediaAvailabilityRequest $request, Party $party): RedirectResponse
    {
        $mediaType = $request->validated('media_type');

        if ($mediaType === 'pictures') {
            $party->update(['pictures_enabled' => true]);
        }

        if ($mediaType === 'audios') {
            $party->update(['audios_enabled' => true]);
        }

        return to_route('party.dashboard.show', $party);
    }
}
