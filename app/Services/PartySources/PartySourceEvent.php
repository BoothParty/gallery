<?php

namespace App\Services\PartySources;

use Carbon\CarbonImmutable;

class PartySourceEvent
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?CarbonImmutable $startsAt = null,
        public readonly ?string $galleryName = null,
        public readonly ?string $clientEmail = null,
        public readonly bool $hasGallery = true,
        public readonly bool $hasAudioGuestbook = false,
    ) {}

    /**
     * @return array{
     *     id: string,
     *     name: string,
     *     starts_at: string|null,
     *     gallery_name: string|null,
     *     client_email: string|null,
     *     has_gallery: bool,
     *     has_audio_guestbook: bool,
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
            'gallery_name' => $this->galleryName,
            'client_email' => $this->clientEmail,
            'has_gallery' => $this->hasGallery,
            'has_audio_guestbook' => $this->hasAudioGuestbook,
            'source_key' => $source->key(),
            'source_label' => $source->label(),
        ];
    }
}
