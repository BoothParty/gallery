<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkStorePartiesRequest;
use App\Http\Requests\StorePartyRequest;
use App\Http\Requests\UpdateAdminPartyRequest;
use App\Models\Party;
use App\Models\User;
use App\Services\AdminDashboardDataService;
use App\Services\PartySources\PartySourceManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class PartyController extends Controller
{
    public function index(
        Request $request,
        AdminDashboardDataService $adminDashboardDataService,
        PartySourceManager $partySourceManager,
    ): Response {
        $refreshSources = $request->boolean('refresh');

        return Inertia::render('admin/parties/index', [
            ...$adminDashboardDataService->sidebarData(),
            'tableData' => $adminDashboardDataService->tableDataForResource('parties'),
            'users' => $this->userOptions(),
            'partyDomain' => config('party.domain'),
            'upcomingSourceEvents' => Inertia::defer(
                fn (): array => $partySourceManager->upcomingEvents($refreshSources)
            ),
            'editableParties' => Party::query()
                ->get(['id', 'name', 'user_id', 'expire_at'])
                ->map(fn (Party $party): array => [
                    'id' => $party->id,
                    'name' => $party->name,
                    'user_id' => $party->user_id,
                    'expire_at' => $this->expiryForInput($party),
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

    public function bulkStore(BulkStorePartiesRequest $request): RedirectResponse
    {
        $parties = $request->validated('parties');

        DB::transaction(function () use ($parties): void {
            foreach ($parties as $party) {
                $sourceEventId = $party['source_event_id'] ?? null;

                Party::query()->create([
                    'name' => $party['name'],
                    'source_key' => $sourceEventId === null ? null : Str::before($sourceEventId, ':'),
                    'source_event_id' => $sourceEventId,
                ]);
            }
        });

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
                'expire_at' => $this->expiryForInput($party),
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

    /**
     * Expose the expiry date as an absolute instant; the admin forms convert it
     * to and from the browser's local time for their datetime-local input.
     */
    private function expiryForInput(Party $party): ?string
    {
        return $party->expire_at?->toIso8601String();
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
