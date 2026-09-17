<?php

namespace App\Http\Controllers\Admin;

use App\Enums\GreetingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\RejectAudioGreetingRequest;
use App\Models\AudioGreeting;
use App\Models\Party;
use App\Services\AdminDashboardDataService;
use App\Services\AudiobookQueue;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class AudiobookController extends Controller
{
    /**
     * What the audio guestbook still asks of the studio, across every party.
     */
    public function queue(
        AdminDashboardDataService $adminDashboardDataService,
        AudiobookQueue $audiobookQueue,
    ): Response {
        return Inertia::render('admin/audiobook/queue', [
            ...$adminDashboardDataService->sidebarData(),
            'awaitingValidation' => $audiobookQueue->awaitingValidation(),
            'awaitingUpload' => $audiobookQueue->awaitingUpload(),
            'withoutGreeting' => $audiobookQueue->withoutGreeting(),
        ]);
    }

    /**
     * The studio's view of one party's audio guestbook.
     */
    public function show(Party $party, AdminDashboardDataService $adminDashboardDataService): Response
    {
        $greeting = $party->greeting()->first();

        return Inertia::render('admin/audiobook/index', [
            ...$adminDashboardDataService->sidebarData($party->id),
            'party' => [
                'id' => $party->id,
                'name' => $party->displayTitle(),
                'client_email' => $party->client_email,
                'client_url' => $party->audiobookUrl(),
            ],
            'greeting' => $greeting instanceof AudioGreeting ? [
                'status' => $greeting->status->value,
                'url' => $greeting->playbackUrl(),
                'duration_seconds' => $greeting->duration_seconds,
                'client_note' => $greeting->client_note,
                'rejection_reason' => $greeting->rejection_reason,
                'submitted_at' => $greeting->submitted_at?->toIso8601String(),
                'validated_at' => $greeting->validated_at?->toIso8601String(),
                'awaiting_conversion' => $greeting->mp3_path === null,
            ] : null,
        ]);
    }

    /**
     * Accept the greeting as it stands.
     *
     * No confirmation guards this: the studio can send a greeting back to
     * `rejected` afterwards, so the gesture is reversible.
     */
    public function approve(Party $party): RedirectResponse
    {
        $greeting = $party->greeting()->first();

        abort_unless($greeting instanceof AudioGreeting, 404);

        $greeting->update([
            'status' => GreetingStatus::Validated,
            'rejection_reason' => null,
            'validated_at' => now(),
        ]);

        return back();
    }

    /**
     * Send the greeting back to the client with a reason.
     *
     * The reason is mandatory and travels verbatim to the client: it is the
     * only thing telling them what to do differently.
     */
    public function reject(RejectAudioGreetingRequest $request, Party $party): RedirectResponse
    {
        $greeting = $party->greeting()->first();

        abort_unless($greeting instanceof AudioGreeting, 404);

        $greeting->update([
            'status' => GreetingStatus::Rejected,
            'rejection_reason' => $request->validated('rejection_reason'),
            'validated_at' => null,
        ]);

        return back();
    }
}
