<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\BulkStorePartiesRequest;
use App\Http\Requests\StoreAdminPartyRequest;
use App\Http\Requests\UpdateAdminPartyRequest;
use App\Http\Requests\UpdatePartyModulesRequest;
use App\Models\Party;
use App\Models\User;
use App\Services\AdminDashboardDataService;
use App\Services\PartyBannerStorage;
use App\Services\PartyPosterGenerator;
use App\Services\PartySources\PartySourceManager;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response as HttpResponse;
use Illuminate\Support\Arr;
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
                ->get(['id', 'name', 'host_token', 'user_id', 'expire_at', 'display_name', 'description', 'banner_path'])
                ->map(fn (Party $party): array => [
                    'id' => $party->id,
                    'name' => $party->name,
                    'user_id' => $party->user_id,
                    'expire_at' => $this->expiryForInput($party),
                    'display_name' => $party->display_name,
                    'description' => $party->description,
                    'banner_url' => $party->bannerUrl(),
                    'host_edit_url' => $party->hostEditUrl(),
                    'route_key' => $party->name,
                ])
                ->values(),
        ]);
    }

    public function store(StoreAdminPartyRequest $request): RedirectResponse
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

    /**
     * Stream the party's printable poster: the template with its QR code and
     * gallery address filled in.
     */
    public function poster(Party $party, PartyPosterGenerator $partyPosterGenerator): HttpResponse
    {
        $filename = Str::slug($party->name).'-affiche.png';

        return response($partyPosterGenerator->generate($party), 200, [
            'Content-Type' => 'image/png',
            'Content-Disposition' => 'attachment; filename="'.$filename.'"',
        ]);
    }

    /**
     * Fields missing from the request keep their stored value, so a partial
     * submission never wipes the metadata of a party.
     */
    public function update(
        UpdateAdminPartyRequest $request,
        Party $party,
        PartyBannerStorage $partyBannerStorage,
    ): RedirectResponse {
        $party->update(Arr::only(
            $request->validated(),
            ['name', 'user_id', 'expire_at', 'display_name', 'description'],
        ));

        if ($request->boolean('remove_banner')) {
            $partyBannerStorage->remove($party);
        } elseif ($request->hasFile('banner')) {
            $partyBannerStorage->store($party, $request->file('banner'));
        }

        return to_route('admin.parties.index');
    }

    /**
     * Turn a module on or off for one party.
     *
     * The only place a module is switched: the sidebar's activation dialog and
     * the party form both come through here rather than each writing the flag
     * their own way.
     */
    public function updateModules(UpdatePartyModulesRequest $request, Party $party): RedirectResponse
    {
        $party->update($request->validated());

        return back();
    }

    /**
     * Cut the party's current edit link and mint a new one.
     *
     * Host sessions already open survive, since they only carry a boolean.
     * That is acceptable: regeneration exists to kill a link that leaked to a
     * third party, not to evict a host from their own browser.
     */
    public function regenerateHostToken(Party $party): RedirectResponse
    {
        $party->forceFill(['host_token' => Str::random(48)])->save();

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
