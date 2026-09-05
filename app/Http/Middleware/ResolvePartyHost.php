<?php

namespace App\Http\Middleware;

use App\Models\Party;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ResolvePartyHost
{
    /**
     * Trade a valid `?token=` for a session flag, then send the visitor to the
     * same page without it.
     *
     * The redirect happens whether or not the token was right, so that a
     * correct and an incorrect token are indistinguishable from outside: an
     * attacker gets no oracle to guess a token by trial. It also keeps the
     * secret out of the address bar, the history, outgoing `Referer` headers
     * and any URL the host copies to their guests.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->query('token');

        if (! is_string($token)) {
            return $next($request);
        }

        $party = $request->route('party');

        if ($party instanceof Party
            && is_string($party->host_token)
            && hash_equals($party->host_token, $token)) {
            $request->session()->put(self::sessionKey($party), true);
        }

        return redirect()->to($request->fullUrlWithoutQuery('token'));
    }

    /**
     * Whether the current session carries the right to edit this party. Both
     * `EnsurePartyHost` and the gallery controller ask through here rather
     * than reading the session themselves.
     */
    public static function isHost(Request $request, Party $party): bool
    {
        return $request->session()->get(self::sessionKey($party)) === true;
    }

    /**
     * Session key carrying the right to edit one party.
     */
    public static function sessionKey(Party $party): string
    {
        return 'party_host.'.$party->id;
    }
}
