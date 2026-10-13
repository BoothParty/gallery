<?php

namespace App\Http\Controllers;

use App\Enums\PartyActivityType;
use App\Models\Party;
use App\Models\Picture;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PictureVisibilityController extends Controller
{
    /**
     * Take one picture off the guests' gallery, or put it back.
     *
     * The desired state travels with the request rather than being toggled
     * server-side: a host tapping twice on a slow connection then lands on the
     * state they last asked for instead of on whichever order the two requests
     * happened to arrive in.
     *
     * That state is required rather than defaulted: a malformed request would
     * otherwise coerce to `false` and put back on display a picture the host
     * had deliberately masked.
     */
    public function __invoke(Request $request, Party $party, Picture $picture): RedirectResponse
    {
        $request->validate([
            'hidden' => ['required', 'boolean'],
        ]);

        $hidden = $request->boolean('hidden');

        if ($picture->isHidden() === $hidden) {
            return back();
        }

        $picture->update([
            'hidden_at' => $hidden ? now() : null,
        ]);

        $party->recordActivity(
            $hidden ? PartyActivityType::PicturesHidden : PartyActivityType::PicturesShown,
            ['count' => 1],
        );

        return back();
    }
}
