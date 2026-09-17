<?php

namespace App\Http\Controllers;

use App\Models\AudioGreeting;
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
            'greeting' => $greeting instanceof AudioGreeting ? [
                'status' => $greeting->status->value,
                'url' => $greeting->playbackUrl(),
                'duration_seconds' => $greeting->duration_seconds,
                'client_note' => $greeting->client_note,
                'rejection_reason' => $greeting->rejection_reason,
                'submitted_at' => $greeting->submitted_at?->toIso8601String(),
            ] : null,
        ]);
    }
}
