<?php

namespace App\Http\Controllers;

use App\Models\Party;
use App\Models\Picture;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class PictureFileController extends Controller
{
    /**
     * Stream a picture from the application's own origin.
     *
     * The Web Share API needs real File objects, which means reading the bytes
     * in JavaScript. Pictures live on S3, another origin, where a canvas would
     * be tainted and a fetch response opaque, so the bytes have to come back
     * through the application.
     */
    public function __invoke(Party $party, Picture $picture): StreamedResponse
    {
        abort_if($party->hidePictures(), 403);

        $stream = Storage::disk('s3')->readStream($picture->path);

        abort_if($stream === null, 404);

        return response()->stream(
            function () use ($stream): void {
                set_time_limit(0);

                fpassthru($stream);
                fclose($stream);
            },
            200,
            [
                'Content-Type' => 'image/jpeg',
                'Content-Disposition' => 'inline; filename="photo-'.$picture->id.'.jpg"',
                'Cache-Control' => (string) config('filesystems.disks.s3.options.CacheControl'),
                'X-Accel-Buffering' => 'no',
            ],
        );
    }
}
