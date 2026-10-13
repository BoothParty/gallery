<?php

namespace App\Services;

use App\Http\Resources\PictureResource;
use App\Models\Party;

class PartyDashboard
{
    /**
     * How many pictures the "latest pictures" card shows.
     */
    private const LATEST_PICTURES = 24;

    /**
     * Everything the party dashboard cards render. Hidden pictures are
     * included: the dashboard is the host's view, not the guests'.
     *
     * @return array{
     *     party: array{description: string|null, expire_at: string|null},
     *     pictureStats: array{total: int, lastHour: int},
     *     latestPictures: list<array<string, mixed>>,
     * }
     */
    public function for(Party $party): array
    {
        return [
            'party' => [
                'description' => $party->description,
                'expire_at' => $party->expire_at?->toIso8601String(),
            ],
            'pictureStats' => [
                'total' => $party->pictures()->count(),
                'lastHour' => $party->pictures()->where('created_at', '>=', now()->subHour())->count(),
            ],
            'latestPictures' => PictureResource::collection(
                $party->pictures()->latest()->latest('id')->limit(self::LATEST_PICTURES)->get()
            )->resolve(),
        ];
    }
}
