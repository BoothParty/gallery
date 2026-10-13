<?php

namespace App\Http\Controllers;

use App\Models\Party;
use Inertia\Inertia;
use Inertia\Response;

class AudiobookController extends Controller
{
    /**
     * The client's private audio space.
     *
     * Only the event's identity is sent for now; the greeting and the guests'
     * messages are added by the tasks that build them.
     */
    public function show(Party $party): Response
    {
        return Inertia::render('party/audiobook', [
            'party' => [
                'title' => $party->displayTitle(),
                'event_at' => $party->event_at?->toIso8601String(),
            ],
        ]);
    }
}
