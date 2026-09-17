<?php

namespace App\Http\Controllers;

use App\Models\AudioGreeting;
use App\Models\AudioMessage;
use App\Models\Party;
use Inertia\Inertia;
use Inertia\Response;

class AudiobookController extends Controller
{
    /**
     * The client's private audio space: the identity of the event and the
     * state of their greeting. The guests' messages join them later.
     */
    public function show(Party $party): Response
    {
        $greeting = $party->greeting()->first();

        return Inertia::render('party/audiobook', [
            'party' => [
                'title' => $party->displayTitle(),
                'event_line' => $party->event_at?->locale('fr')->isoFormat('D MMMM YYYY'),
            ],
            /**
             * Built here rather than in the page: the party is bound by its
             * private uuid, which `route()` would otherwise replace with the
             * model's default route key.
             */
            'routes' => [
                'store' => route('audiobook.greeting.store', $party->client_uuid),
                'submit' => route('audiobook.greeting.submit', $party->client_uuid),
                'download' => route('audiobook.messages.download', $party->client_uuid),
                'messages' => route('audiobook.messages.update', [$party->client_uuid, '__id__']),
            ],
            'csrfToken' => csrf_token(),
            'greeting' => $greeting instanceof AudioGreeting ? [
                'status' => $greeting->status->value,
                'url' => $greeting->playbackUrl(),
                'duration_seconds' => $greeting->duration_seconds,
                'client_note' => $greeting->client_note,
                'rejection_reason' => $greeting->rejection_reason,
                'submitted_at' => $greeting->submitted_at?->toIso8601String(),
            ] : null,
            'messages' => $party->audioMessages()->get()->map(fn (AudioMessage $message): array => [
                'id' => $message->id,
                'title' => $message->title,
                'display_title' => $message->displayTitle(),
                'url' => $message->url(),
                'duration_seconds' => $message->duration_seconds,
                'recorded_at' => $message->recorded_at?->toIso8601String(),
                'position' => $message->position,
            ])->values(),
        ]);
    }
}
