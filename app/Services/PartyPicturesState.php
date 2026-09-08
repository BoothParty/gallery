<?php

namespace App\Services;

use App\Models\Party;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;

class PartyPicturesState
{
    /**
     * Guard TTL for the memoised aggregate, in seconds.
     *
     * Not a freshness knob: picture writes invalidate the entry. It exists so
     * that a future write skipping the model events leaves the gallery stale
     * for a moment rather than for good.
     */
    private const CACHE_TTL = 600;

    /**
     * The signal an open gallery polls to know whether its content moved.
     *
     * `count` catches an arrival and a deletion, `last_change` catches an
     * arrival and a thumbnail finishing its job. Neither is enough on its
     * own: a deletion leaves `MAX(updated_at)` where it was, and a deletion
     * followed by an arrival leaves the count where it was.
     *
     * An expired party always reads as empty. The page shows nothing in that
     * case, and a client left open across the expiry date must not start
     * announcing pictures it will never display. Pictures the host has hidden
     * are left out for the same reason.
     *
     * @return array{count: int, last_change: string|null}
     */
    public function for(Party $party): array
    {
        if ($party->hidePictures()) {
            return ['count' => 0, 'last_change' => null];
        }

        return Cache::remember(
            self::key($party->id),
            self::CACHE_TTL,
            fn (): array => $this->compute($party),
        );
    }

    /**
     * Drop the memoised aggregate. Called from the `Picture` model events, so
     * that the cache expires on writes rather than on a clock.
     */
    public function forget(int $partyId): void
    {
        Cache::forget(self::key($partyId));
    }

    private static function key(int $partyId): string
    {
        return "party:{$partyId}:pictures-state";
    }

    /**
     * @return array{count: int, last_change: string|null}
     */
    private function compute(Party $party): array
    {
        $state = $party->pictures()
            ->visible()
            ->selectRaw('count(*) as pictures_count, max(updated_at) as last_change')
            ->first();

        $lastChange = $state?->last_change;

        return [
            'count' => (int) ($state?->pictures_count ?? 0),
            'last_change' => $lastChange === null ? null : Carbon::parse($lastChange)->toIso8601String(),
        ];
    }
}
