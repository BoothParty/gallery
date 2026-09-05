<?php

namespace App\Services;

use App\Models\Party;
use DateTimeInterface;
use Illuminate\Support\Facades\URL;

/**
 * Builds the signed links of a poster batch.
 *
 * A batch is nothing but a list of party identifiers carried by a signed URL,
 * so no state is stored: the signature is what makes the list trustworthy.
 */
class PosterBatchLink
{
    /**
     * How long a batch link stays valid, in hours.
     */
    public const LIFETIME_IN_HOURS = 6;

    /**
     * The posters of a batch, in the order of the requested identifiers.
     *
     * The files expire with the page that lists them, so that no poster dies
     * in the middle of a sharing session.
     *
     * @param  array<int, int>  $partyIds
     * @return array<int, array{id: int, name: string, url: string}>
     */
    public function posters(array $partyIds, DateTimeInterface $expiresAt): array
    {
        $parties = Party::query()
            ->whereIn('id', $partyIds)
            ->get(['id', 'name'])
            ->keyBy('id');

        return collect($partyIds)
            ->map(fn (int $partyId): ?Party => $parties->get($partyId))
            ->filter()
            ->map(fn (Party $party): array => [
                'id' => $party->id,
                'name' => $party->name,
                'url' => URL::temporarySignedRoute('posters.file', $expiresAt, ['party' => $party->id]),
            ])
            ->values()
            ->all();
    }

    /**
     * The identifiers carried by a batch link, as a comma separated list.
     *
     * @return array<int, int>
     */
    public function parseIds(?string $value): array
    {
        return collect(explode(',', (string) $value))
            ->map(fn (string $id): int => (int) trim($id))
            ->filter(fn (int $id): bool => $id > 0)
            ->values()
            ->all();
    }
}
