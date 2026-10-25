<?php

namespace App\Http\Controllers;

use App\Models\Party;
use App\Models\Picture;
use App\Services\AdminDashboardDataService;
use App\Services\PartyDashboard;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PartyDashboardController extends Controller
{
    public function show(Party $party, AdminDashboardDataService $adminDashboardDataService, PartyDashboard $partyDashboard): Response
    {
        return Inertia::render('dashboard', [
            ...$adminDashboardDataService->sidebarData($party->id),
            ...$partyDashboard->for($party),
        ]);
    }

    public function pictures(Request $request, Party $party, AdminDashboardDataService $adminDashboardDataService): Response
    {
        $allowedPerPage = [24, 48, 72, 96];
        $perPage = in_array($request->integer('per_page', 24), $allowedPerPage)
            ? $request->integer('per_page', 24)
            : 24;

        $pendingPrintsOnly = $request->query('print') === 'pending';

        $pictures = $party->pictures()
            ->withPrintPending()
            ->when($pendingPrintsOnly, fn ($query) => $query->whereHas('printJobs', fn ($jobs) => $jobs->pending()))
            ->latest()
            ->latest('id')
            ->paginate($perPage)
            ->through(fn (Picture $picture) => [
                'id' => $picture->id,
                'url' => $picture->url(),
                'thumbnailUrl' => $picture->thumbnailUrl(),
                'width' => $picture->width,
                'height' => $picture->height,
                'printPending' => (bool) $picture->print_pending,
            ]);

        return Inertia::render('admin/gallery/index', [
            ...$adminDashboardDataService->sidebarData($party->id),
            'party' => [
                'id' => $party->id,
                'name' => $party->name,
            ],
            'pictures' => $pictures,
            'perPage' => $perPage,
            'pendingPrintsOnly' => $pendingPrintsOnly,
        ]);
    }
}
