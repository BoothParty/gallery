<?php

namespace App\Services;

use App\Enums\PartyActivityType;
use App\Http\Resources\PictureResource;
use App\Models\Party;
use App\Models\PartyActivity;

class PartyDashboard
{
    /**
     * How many pictures the "latest pictures" card shows.
     */
    private const LATEST_PICTURES = 24;

    /**
     * How many entries the activity feed shows.
     */
    private const LATEST_ACTIVITIES = 5;

    /**
     * Everything the party dashboard cards render. Hidden pictures are
     * included: the dashboard is the host's view, not the guests'.
     *
     * @return array{
     *     party: array{description: string|null, expire_at: string|null},
     *     pictureStats: array{total: int, lastHour: int},
     *     downloadStats: array{total: int, pictures: int},
     *     latestPictures: list<array<string, mixed>>,
     *     activity: list<array{description: string, at: string}>,
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
            'downloadStats' => $this->downloadStats($party),
            'latestPictures' => PictureResource::collection(
                $party->pictures()->latest()->latest('id')->limit(self::LATEST_PICTURES)->get()
            )->resolve(),
            'activity' => $party->activities()
                ->whereIn('type', array_filter(PartyActivityType::cases(), fn (PartyActivityType $type): bool => $type->isListed()))
                ->latest('updated_at')
                ->latest('id')
                ->limit(self::LATEST_ACTIVITIES)
                ->get()
                ->map(fn (PartyActivity $activity): array => [
                    'description' => $activity->description(),
                    'at' => $activity->updated_at->toIso8601String(),
                ])
                ->all(),
        ];
    }

    /**
     * Every download is a ZIP, whatever its size: the card counts the
     * downloads and, below, the pictures they carried.
     *
     * @return array{total: int, pictures: int}
     */
    private function downloadStats(Party $party): array
    {
        $downloads = $party->activities()->where('type', PartyActivityType::PicturesDownloaded);

        return [
            'total' => $downloads->clone()->count(),
            'pictures' => (int) $downloads->sum('properties->count'),
        ];
    }
}
