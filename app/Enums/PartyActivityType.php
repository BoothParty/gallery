<?php

namespace App\Enums;

use Carbon\CarbonInterval;

enum PartyActivityType: string
{
    case PicturesImported = 'pictures_imported';
    case PicturesUploaded = 'pictures_uploaded';
    case PicturesHidden = 'pictures_hidden';
    case PicturesShown = 'pictures_shown';
    case PicturesDownloaded = 'pictures_downloaded';
    case MetadataUpdated = 'metadata_updated';
    case PrintsRequested = 'prints_requested';
    case PicturesPrinted = 'pictures_printed';

    /**
     * How close two occurrences must be to read as one entry in the feed.
     *
     * The booth sends one request per picture and a host hides pictures one
     * tap at a time: without folding, a single session would flood the feed.
     * Downloads are never folded since the download stats count them.
     */
    public function foldWithin(): ?CarbonInterval
    {
        return match ($this) {
            self::PicturesDownloaded => null,
            default => CarbonInterval::minutes(15),
        };
    }

    /**
     * Whether the dashboard feed lists this activity. Downloads are recorded
     * for the stats card only: guests download far too often for a feed.
     */
    public function isListed(): bool
    {
        return $this !== self::PicturesDownloaded;
    }

    /**
     * @param  array{count?: int}  $properties
     */
    public function description(array $properties): string
    {
        $count = $properties['count'] ?? 0;
        $pictures = $count.' '.($count > 1 ? 'photos' : 'photo');
        $plural = $count > 1 ? 's' : '';

        return match ($this) {
            self::PicturesImported => "{$pictures} importée{$plural} depuis la borne",
            self::PicturesUploaded => "{$pictures} ajoutée{$plural} depuis l'administration",
            self::PicturesHidden => "{$pictures} masquée{$plural}",
            self::PicturesShown => "{$pictures} remise{$plural} en ligne",
            self::PicturesDownloaded => "{$pictures} téléchargée{$plural}",
            self::PrintsRequested => "{$pictures} envoyée{$plural} à l'impression",
            self::PicturesPrinted => "{$pictures} réimprimée{$plural}",
            self::MetadataUpdated => 'Présentation de la galerie modifiée',
        };
    }
}
