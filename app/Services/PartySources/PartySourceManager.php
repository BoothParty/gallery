<?php

namespace App\Services\PartySources;

use Illuminate\Contracts\Container\Container;
use InvalidArgumentException;

class PartySourceManager
{
    public function __construct(
        private readonly Container $container,
    ) {}

    /**
     * Upcoming events of every enabled source, oldest first; undated events come
     * last. Shaped for the admin parties page.
     *
     * @return list<array<string, string|null>>
     */
    public function upcomingEvents(): array
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
