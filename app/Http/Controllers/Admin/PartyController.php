<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePartyRequest;
use App\Http\Requests\UpdateAdminPartyRequest;
use App\Models\Party;
use App\Models\User;
use App\Services\AdminDashboardDataService;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class PartyController extends Controller
{
    public function index(AdminDashboardDataService $adminDashboardDataService): Response
    {
        return Inertia::render('admin/parties/index', [
            ...$adminDashboardDataService->sidebarData(),
            'tableData' => $adminDashboardDataService->tableDataForResource('parties'),
            'users' => $this->userOptions(),
            'editableParties' => Party::query()
                ->get(['id', 'name', 'user_id'])
                ->map(fn (Party $party): array => [
                    'id' => $party->id,
                    'name' => $party->name,
                    'user_id' => $party->user_id,
                    'route_key' => $party->name,
                ])
                ->values(),
        ]);
    }

    public function store(StorePartyRequest $request): RedirectResponse
    {
        Party::query()->create($request->validated());

        return to_route('admin.parties.index');
    }

    public function edit(Party $party, AdminDashboardDataService $adminDashboardDataService): Response
    {
        return Inertia::render('admin/parties/edit', [
            ...$adminDashboardDataService->sidebarData($party->id),
            'users' => $this->userOptions(),
            'party' => [
                'id' => $party->id,
                'name' => $party->name,
                'user_id' => $party->user_id,
            ],
        ]);
    }

    public function update(UpdateAdminPartyRequest $request, Party $party): RedirectResponse
    {
        $party->update($request->validated());

        return to_route('admin.parties.index');
    }

    public function destroy(Party $party): RedirectResponse
    {
        $party->delete();

        return to_route('admin.parties.index');
    }

    private function userOptions()
    {
        return User::query()
            ->orderBy('name')
            ->get(['id', 'name'])
            ->map(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
            ])
            ->values();
    }
}
