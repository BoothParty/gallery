<?php

namespace App\Services;

use App\Models\Party;
use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Reads one party's audience back from Umami.
 *
 * A single Umami website covers every party: each gallery having its own
 * subdomain, the hostname is the dimension that tells them apart, which saves
 * creating and syncing a website per evening.
 *
 * Every method answers `null` rather than throwing when Umami is unconfigured
 * or unreachable: analytics are a side panel, they must never take the
 * dashboard down with them.
 */
class UmamiStats
{
    /**
     * Beyond this many hours the hourly curve stops being readable, and the
     * window is a run of days rather than one evening.
     */
    private const HOURLY_WINDOW_HOURS = 72;

    /**
     * How many rows each breakdown keeps. The dashboard shows a short list,
     * the full one lives in Umami.
     */
    private const BREAKDOWN_ROWS = 5;

    /**
     * The gallery's audience, or null when Umami has nothing to say.
     *
     * @return array{
     *     hostname: string,
     *     dashboard_url: string,
     *     unit: string,
     *     visitors: int,
     *     visits: int,
     *     pageviews: int,
     *     average_seconds: int,
     *     events: array<string, int>,
     *     series: list<array{label: string, value: int}>,
     *     referrers: list<array{label: string, value: int}>,
     *     devices: list<array{label: string, value: int}>,
     * }|null
     */
    public function for(Party $party): ?array
    {
        if (! $this->isConfigured()) {
            return null;
        }

        /*
         * A null is not cached, so a broken Umami is retried on the next view
         * instead of being remembered for the whole TTL.
         */
        return Cache::remember(
            'umami.stats.'.$party->id,
            config('services.umami.cache_ttl'),
            fn (): ?array => $this->fetch($party),
        );
    }

    public function isConfigured(): bool
    {
        return filled(config('services.umami.url'))
            && filled(config('services.umami.website_id'))
            && filled(config('services.umami.username'))
            && filled(config('services.umami.password'));
    }

    private function fetch(Party $party): ?array
    {
        $start = $party->created_at ?? $party->freshTimestamp();
        $end = $party->expire_at !== null && $party->expire_at->isPast() ? $party->expire_at : now();

        $unit = $this->unit($start, $end);

        $window = [
            'startAt' => $start->getTimestampMs(),
            'endAt' => $end->getTimestampMs(),
            'hostname' => $party->publicHost(),
        ];

        try {
            $stats = $this->get('stats', $window);
            $series = $this->get('pageviews', [...$window, 'unit' => $unit, 'timezone' => config('app.timezone')]);
            $events = $this->get('metrics', [...$window, 'type' => 'event']);
            $referrers = $this->get('metrics', [...$window, 'type' => 'referrer']);
            $devices = $this->get('metrics', [...$window, 'type' => 'device']);
        } catch (ConnectionException $exception) {
            Log::warning('Umami is unreachable: '.$exception->getMessage());

            return null;
        }

        if ($stats === null) {
            return null;
        }

        $visits = (int) data_get($stats, 'visits.value', 0);

        return [
            'hostname' => $party->publicHost(),
            'dashboard_url' => rtrim((string) config('services.umami.url'), '/').'/websites/'.config('services.umami.website_id'),
            'unit' => $unit,
            'visitors' => (int) data_get($stats, 'visitors.value', 0),
            'visits' => $visits,
            'pageviews' => (int) data_get($stats, 'pageviews.value', 0),
            'average_seconds' => $visits === 0 ? 0 : intdiv((int) data_get($stats, 'totaltime.value', 0), $visits),
            'events' => $this->rows($events)
                ->mapWithKeys(fn (array $row): array => [(string) $row['x'] => (int) $row['y']])
                ->all(),
            'series' => $this->series($series),
            'referrers' => $this->breakdown($referrers),
            'devices' => $this->breakdown($devices),
        ];
    }

    /**
     * @param  array<string, mixed>  $query
     * @return array<array-key, mixed>|null
     */
    private function get(string $path, array $query): ?array
    {
        $response = $this->request()->get(
            rtrim((string) config('services.umami.url'), '/').'/api/websites/'.config('services.umami.website_id').'/'.$path,
            $query,
        );

        if ($response->failed()) {
            Log::warning('Umami answered '.$response->status().' for '.$path);

            return null;
        }

        return $response->json();
    }

    /**
     * An authenticated client. The token is worth a login round trip on the
     * first read of the hour, not on every panel.
     */
    private function request(): PendingRequest
    {
        $token = Cache::remember('umami.token', now()->addHours(6), function (): ?string {
            $response = Http::timeout((int) config('services.umami.timeout'))
                ->post(rtrim((string) config('services.umami.url'), '/').'/api/auth/login', [
                    'username' => config('services.umami.username'),
                    'password' => config('services.umami.password'),
                ]);

            return $response->json('token');
        });

        return Http::withToken((string) $token)->timeout((int) config('services.umami.timeout'));
    }

    /**
     * `hour` for a single evening, `day` for a window nobody would read hour by
     * hour.
     */
    private function unit(CarbonInterface $start, CarbonInterface $end): string
    {
        return $start->diffInHours($end) > self::HOURLY_WINDOW_HOURS ? 'day' : 'hour';
    }

    /**
     * The visits curve, labelled in the app timezone. Umami answers one point
     * per unit with no gaps, so the labels carry the whole x axis.
     *
     * @param  array<array-key, mixed>|null  $payload
     * @return list<array{label: string, value: int}>
     */
    private function series(?array $payload): array
    {
        $sessions = data_get($payload, 'sessions');

        return $this->rows(is_array($sessions) ? $sessions : null)
            ->map(fn (array $point): array => [
                'label' => (string) $point['x'],
                'value' => (int) $point['y'],
            ])
            ->all();
    }

    /**
     * The rows of a metrics answer, an unexpected shape being dropped rather
     * than crashing the panel it feeds.
     *
     * @param  array<array-key, mixed>|null  $payload
     * @return Collection<int, array<string, mixed>>
     */
    private function rows(?array $payload): Collection
    {
        return collect($payload ?? [])
            ->filter(fn (mixed $row): bool => is_array($row)
                && array_key_exists('x', $row)
                && array_key_exists('y', $row))
            ->values();
    }

    /**
     * @param  array<array-key, mixed>|null  $payload
     * @return list<array{label: string, value: int}>
     */
    private function breakdown(?array $payload): array
    {
        return $this->rows($payload)
            ->sortByDesc('y')
            ->take(self::BREAKDOWN_ROWS)
            ->map(fn (array $row): array => [
                'label' => filled($row['x']) ? (string) $row['x'] : 'Direct',
                'value' => (int) $row['y'],
            ])
            ->values()
            ->all();
    }
}
