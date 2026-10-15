<?php

namespace App\Http\Controllers\Admin;

use App\Enums\PartyKind;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTransferRequest;
use App\Models\Party;
use App\Services\AdminDashboardDataService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class TransferController extends Controller
{
    public function index(AdminDashboardDataService $adminDashboardDataService): Response
    {
        return Inertia::render('admin/transfers/index', [
            ...$adminDashboardDataService->sidebarData(),
            'rows' => Party::query()
                ->where('kind', PartyKind::Transfer)
                ->withPicturesSummary()
                ->latest()
                ->latest('id')
                ->get()
                ->map(fn (Party $transfer): array => [
                    'id' => $transfer->id,
                    'name' => $transfer->name,
                    'display_name' => $transfer->display_name,
                    ...$transfer->picturesSummary(),
                    'expire_at' => $transfer->expire_at?->toIso8601String(),
                    'created_at' => $transfer->created_at?->toIso8601String(),
                    'public_url' => $transfer->publicUrl(),
                ]),
        ]);
    }

    /**
     * Answered in JSON rather than by a redirect: the dialog that calls it goes
     * straight on to post the folder's pictures to the returned upload URL.
     */
    public function store(StoreTransferRequest $request): JsonResponse
    {
        $transfer = Party::query()->create([
            ...$request->validated(),
            'kind' => PartyKind::Transfer,
            'name' => (string) Str::uuid(),
        ]);

        return response()->json([
            'name' => $transfer->name,
            'public_url' => $transfer->publicUrl(),
            'upload_url' => route('party.dashboard.pictures.store', $transfer),
        ], 201);
    }

    public function destroy(Party $transfer): RedirectResponse
    {
        abort_unless($transfer->isTransfer(), 404);

        $transfer->delete();

        return to_route('admin.transfers.index');
    }
}
