<?php

namespace App\Services\PartySources;

use App\Models\Party;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;
use Throwable;

class PartySourceManager
{
    private const CACHE_KEY = 'party-sources.upcoming-events';

    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * Upcoming events of every enabled source, oldest first; undated events come
     * last. Shaped for the admin parties page.
     *
     * The providers are cached, but whether an event was already imported is
     * resolved on every call so a freshly created party shows up right away.
     *
     * @return list<array<string, string|bool|null>>
     */
    public function upcomingEvents(bool $fresh = false): array
    {
        if ($fresh) {
            $this->forget();
        }

        $events = Cache::remember(
            self::CACHE_KEY,
            now()->addSeconds((int) config('party.cache_ttl')),
            fn (): array => $this->collectEvents(),
        );

        return $this->markImportedEvents($events);
    }

    public function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * @return list<array<string, string|null>>
     */
    private function collectEvents(): array
    {
        return collect($this->sources())
            ->flatMap(fn (PartySource $source): array => $this->eventsOf($source))
            ->sortBy(fn (array $event): string => $event['starts_at'] ?? '9999')
            ->values()
            ->all();
    }

    /**
     * The upcoming events of one source, or none of them when it cannot be
     * reached.
     *
     * A remote source is allowed to be down: it feeds a suggestion list on the
     * admin parties page, so degrading to an empty list keeps the page usable
     * where letting the failure through would take the whole page down with it.
     *
     * @return list<array<string, string|null>>
     */
    private function eventsOf(PartySource $source): array
    {
        try {
            return collect($source->events())
                ->reject(fn (PartySourceEvent $event): bool => $event->startsAt !== null && $event->startsAt->isPast())
                ->map(fn (PartySourceEvent $event): array => $event->toArray($source))
                ->all();
        } catch (Throwable $exception) {
            Log::error('Party source unreachable', [
                'source' => $source->key(),
                'exception' => $exception,
            ]);

            return [];
        }
    }

    /**
     * Events already turned into a party carry that party's own name, so the
     * admin reads the address the gallery really lives at rather than the name
     * the dialog would have suggested.
     *
     * @param  list<array<string, string|null>>  $events
     * @return list<array<string, string|bool|null>>
     */
    private function markImportedEvents(array $events): array
    {
        $importedNames = Party::query()
            ->whereIn('source_event_id', collect($events)->pluck('id'))
            ->pluck('name', 'source_event_id');

        return collect($events)
            ->map(fn (array $event): array => [
                ...$event,
                'party_name' => $importedNames->get($event['id']),
                'already_imported' => $importedNames->has($event['id']),
            ])
            ->all();
    }

    /**
     * @return list<PartySource>
     */
    public function sources(): array
    {
        $sourceClasses = config('party.sources');

        if (! is_array($sourceClasses)) {
            return [];
        }

        return collect($sourceClasses)
            ->map(function (mixed $sourceClass, string $key): PartySource {
                if (! is_string($sourceClass) || ! is_subclass_of($sourceClass, PartySource::class)) {
                    throw new InvalidArgumentException(
                        "The [{$key}] party source must implement ".PartySource::class.'.'
                    );
                }

                return $this->container->make($sourceClass);
            })
            ->values()
            ->all();
    }
}
