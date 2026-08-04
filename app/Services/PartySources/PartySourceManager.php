<?php

namespace App\Services\PartySources;

use App\Models\Party;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Cache;
use InvalidArgumentException;

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
            ->flatMap(fn (PartySource $source): array => collect($source->events())
                ->reject(fn (PartySourceEvent $event): bool => $event->startsAt !== null && $event->startsAt->isPast())
                ->map(fn (PartySourceEvent $event): array => $event->toArray($source))
                ->all())
            ->sortBy(fn (array $event): string => $event['starts_at'] ?? '9999')
            ->values()
            ->all();
    }

    /**
     * @param  list<array<string, string|null>>  $events
     * @return list<array<string, string|bool|null>>
     */
    private function markImportedEvents(array $events): array
    {
        $importedIds = Party::query()
            ->whereIn('source_event_id', collect($events)->pluck('id'))
            ->pluck('source_event_id')
            ->all();

        return collect($events)
            ->map(fn (array $event): array => [
                ...$event,
                'already_imported' => in_array($event['id'], $importedIds, true),
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
