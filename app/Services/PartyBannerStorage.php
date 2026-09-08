<?php

namespace App\Services;

use App\Models\Party;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class PartyBannerStorage
{
    /**
     * Widest a stored banner may be, in pixels.
     *
     * The gallery hero spans 1440 CSS px at most, so this still covers 2x DPR
     * screens without keeping the multi-megabyte original a phone produces.
     */
    public const MAX_WIDTH = 2000;

    /**
     * Resize the uploaded file, store it and point the party at it, dropping
     * whatever banner it had before.
     *
     * The file name carries a UUID so a replacement always changes the URL;
     * reusing the path would leave browsers and CDNs serving the old image.
     */
    public function store(Party $party, UploadedFile $file): void
    {
        $image = (new ImageManager(new Driver))->read($file->getPathname());
        $image->scaleDown(width: self::MAX_WIDTH);

        $path = "banners/{$party->id}/".Str::uuid().'.jpg';

        Storage::disk('s3')->put($path, $image->toJpeg(quality: 82));

        $previousPath = $party->banner_path;

        $party->forceFill(['banner_path' => $path])->save();

        $this->deleteFile($previousPath);
    }

    /**
     * Drop the party's banner, sending the gallery hero back to its striped
     * placeholder.
     */
    public function remove(Party $party): void
    {
        $this->deleteFile($party->banner_path);

        $party->forceFill(['banner_path' => null])->save();
    }

    private function deleteFile(?string $path): void
    {
        if ($path) {
            Storage::disk('s3')->delete($path);
        }
    }
}
