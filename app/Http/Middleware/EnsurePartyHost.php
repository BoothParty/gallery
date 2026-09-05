<?php

namespace App\Http\Middleware;

use App\Models\Party;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsurePartyHost
{
    /**
     * Guard the host's write endpoint. Reading a gallery never goes through
     * here; only editing one does.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $party = $request->route('party');

        abort_unless(
            $party instanceof Party && ResolvePartyHost::isHost($request, $party),
            403,
        );

        return $next($request);
    }
}
