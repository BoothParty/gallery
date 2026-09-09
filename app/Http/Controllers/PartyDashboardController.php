<?php

namespace App\Http\Controllers;

use App\Models\Party;
use App\Models\Picture;
use App\Services\AdminDashboardDataService;
use App\Services\UmamiStats;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class PartyDashboardController extends Controller
{
    /**
     * The audience is deferred: it costs a handful of round trips to Umami,
     * which the page has no reason to wait for.
     */
    public function show(
        Party $party,
        AdminDashboardDataService $adminDashboardDataService,
        UmamiStats $umamiStats,
    ): Response {
        return Inertia::render('dashboard', [
            ...$adminDashboardDataService->sidebarData($party->id),
            'partyName' => $party->displayTitle(),
            'analyticsEnabled' => $umamiStats->isConfigured(),
            'analytics' => Inertia::defer(fn (): ?array => $umamiStats->for($party)),
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
            ->latest('id')
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
}
