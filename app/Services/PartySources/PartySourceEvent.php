<?php

namespace App\Services\PartySources;

use Carbon\CarbonImmutable;

class PartySourceEvent
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?CarbonImmutable $startsAt = null,
    ) {}

    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     starts_at: string|null,
     *     source_key: string,
     *     source_label: string,
     * }
     */
    public function toArray(PartySource $source): array
    {
        return [
            'id' => $source->key().':'.$this->id,
            'name' => $this->name,
            'starts_at' => $this->startsAt?->toIso8601String(),
            'source_key' => $source->key(),
            'source_label' => $source->label(),
        ];
    }
}
