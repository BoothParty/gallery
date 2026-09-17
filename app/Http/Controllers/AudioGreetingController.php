<?php

namespace App\Http\Controllers;

use App\Enums\GreetingStatus;
use App\Http\Requests\StoreAudioGreetingRequest;
use App\Http\Requests\SubmitAudioGreetingRequest;
use App\Models\AudioGreeting;
use App\Models\Party;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;

class AudioGreetingController extends Controller
{
    /**
     * Take the client's recording.
     *
     * A party keeps a single greeting: a new take replaces the previous one,
     * files included, and sends the record back to `draft` whatever it was.
     * Re-recording is offered from every state, validated included.
     */
    public function store(StoreAudioGreetingRequest $request, Party $party): RedirectResponse
    {
        $greeting = $party->greeting()->first() ?? new AudioGreeting(['party_id' => $party->id]);

        $previousPaths = array_filter([$greeting->original_path, $greeting->mp3_path]);

        $path = $request->file('recording')->store('parties/'.$party->id.'/audio', 's3');

        $greeting->fill([
            'original_path' => $path,
            'mp3_path' => null,
            'duration_seconds' => null,
            'status' => GreetingStatus::Draft,
            'rejection_reason' => null,
            'submitted_at' => null,
            'validated_at' => null,
        ])->save();

        if ($previousPaths !== []) {
            Storage::disk('s3')->delete($previousPaths);
        }

        return back();
    }

    /**
     * Hand the take to the studio. The note travels with it and is written
     * even when left empty, so a second submission never inherits the note of
     * the first.
     */
    public function submit(SubmitAudioGreetingRequest $request, Party $party): RedirectResponse
    {
        $greeting = $party->greeting()->first();

        abort_unless($greeting instanceof AudioGreeting, 404);

        $greeting->update([
            'client_note' => $request->validated('client_note'),
            'status' => GreetingStatus::Pending,
            'submitted_at' => now(),
        ]);

        return back();
    }
}
