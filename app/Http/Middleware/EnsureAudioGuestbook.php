<?php

namespace App\Http\Middleware;

use App\Models\Party;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAudioGuestbook
{
    /**
     * Guard the client's audio space.
     *
     * A party sold without the module answers 404 rather than 403: the very
     * existence of the space is not confirmed to whoever guessed at the uuid.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $party = $request->route('party');

        abort_unless($party instanceof Party && $party->has_audio_guestbook, 404);

        return $next($request);
    }
}
