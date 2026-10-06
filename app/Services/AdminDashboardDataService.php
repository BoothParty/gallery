<?php

namespace App\Services;

use App\Models\Party;

class AdminDashboardDataService
{
    /**
     * How many live parties the sidebar lists before the search box takes over.
     */
    private const SIDEBAR_PARTIES = 10;

    /**
     * The sidebar's party list, its search index, and which party the page
     * lands on. Both lists come from one pass over the table, ordered by name
     * so the default party is a stable choice rather than whichever row the
     * database happened to return first.
     */
    public function sidebarData(?int $currentPartyId = null): array
    {
        $searchableParties = Party::query()
            ->orderBy('name')
            ->get(['id', 'name', 'expire_at'])
            ->map(fn (Party $availableParty): array => [
                'id' => $availableParty->id,
                'name' => $availableParty->name,
                'expired' => $availableParty->expire_at !== null && $availableParty->expire_at->isPast(),
            ])
            ->values();

        $parties = $searchableParties
            ->reject(fn (array $availableParty): bool => $availableParty['expired'])
            ->take(self::SIDEBAR_PARTIES)
            ->values();

        $defaultPartyId = $searchableParties->first()['id'] ?? null;
        $resolvedPartyId = $currentPartyId ?? $defaultPartyId;

        return [
            'currentPartyId' => $resolvedPartyId,
            'parties' => $parties,
            'searchableParties' => $searchableParties,
        ];
    }
}
