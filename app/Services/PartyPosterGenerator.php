<?php

namespace App\Services;

use App\Models\Party;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\RoundBlockSizeMode;
use Endroid\QrCode\Writer\PngWriter;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Intervention\Image\Typography\FontFactory;
use RuntimeException;

/**
 * Composes a printable poster for a party: the QR code of its gallery inside
 * the template's blank card, and the gallery host inside the template's blank
 * address bar.
 *
 * The coordinates below are measured against resources/poster/template.png and
 * only make sense for that exact file. They keep the QR code clear of the
 * orange star overlapping the card on the left and of the character sitting at
 * its bottom right corner.
 */
class PartyPosterGenerator
{
    private const QR_SIZE = 1050;

    private const QR_X = 2300;

    private const QR_Y = 662;

    private const TEXT_CENTER_X = 1155;

    private const TEXT_CENTER_Y = 2464;

    private const TEXT_BOX_WIDTH = 1500;

    private const TEXT_BOX_HEIGHT = 110;

    public const MAX_FONT_SIZE = 96;

    public const MIN_FONT_SIZE = 24;

    /**
     * Render the party's poster as PNG binary data.
     */
    public function generate(Party $party): string
    {
        $manager = new ImageManager(new Driver);

        $poster = $manager->read($this->templatePath());
        $qrCode = $manager->read($this->qrCode($party->publicUrl()));

        $poster->place(
            $qrCode,
            'top-left',
            self::QR_X + intdiv(self::QR_SIZE - $qrCode->width(), 2),
            self::QR_Y + intdiv(self::QR_SIZE - $qrCode->height(), 2),
        );

        $host = $party->publicHost();
        $fontSize = $this->resolveFontSize($host);

        $poster->text($host, self::TEXT_CENTER_X, self::TEXT_CENTER_Y, function (FontFactory $font) use ($fontSize): void {
            $font->filename($this->fontPath());
            $font->size($fontSize);
            $font->color('#000000');
            $font->align('center');
            $font->valign('middle');
        });

        return (string) $poster->toPng();
    }

    /**
     * Largest font size at which the text still fits the address bar, so that
     * long party names shrink instead of overflowing.
     */
    public function resolveFontSize(string $text): int
    {
        $fontPath = $this->fontPath();

        for ($size = self::MAX_FONT_SIZE; $size > self::MIN_FONT_SIZE; $size--) {
            $box = imagettfbbox($size, 0, $fontPath, $text);

            if ($box === false) {
                break;
            }

            $width = abs($box[2] - $box[0]);
            $height = abs($box[1] - $box[7]);

            if ($width <= self::TEXT_BOX_WIDTH && $height <= self::TEXT_BOX_HEIGHT) {
                return $size;
            }
        }

        return self::MIN_FONT_SIZE;
    }

    private function qrCode(string $url): string
    {
        return (new Builder(
            writer: new PngWriter,
            data: $url,
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: self::QR_SIZE,
            margin: 0,
            roundBlockSizeMode: RoundBlockSizeMode::Shrink,
        ))->build()->getString();
    }

    private function templatePath(): string
    {
        $path = resource_path('poster/template.png');

        if (! is_file($path)) {
            throw new RuntimeException("Poster template is missing at [{$path}].");
        }

        return $path;
    }

    /**
     * The display font shipped with the template, falling back to a font always
     * present in the container so the posters still render without it.
     */
    private function fontPath(): string
    {
        foreach (['poster/font.otf', 'poster/font.ttf'] as $candidate) {
            $path = resource_path($candidate);

            if (is_file($path)) {
                return $path;
            }
        }

        return '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf';
    }
}
