<?php

namespace App\Http\Controllers;

use App\Enums\PartyActivityType;
use App\Http\Requests\DownloadPicturesRequest;
use App\Models\Party;
use App\Models\Picture;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipStream\CompressionMethod;
use ZipStream\ZipStream;

class PictureDownloadController extends Controller
{
    /**
     * Stream the selected pictures as a ZIP archive.
     *
     * The archive is built on the fly from the S3 originals so neither a
     * temporary file nor the whole selection ever lands in memory, whatever
     * the size of the selection.
     */
    public function __invoke(DownloadPicturesRequest $request, Party $party): StreamedResponse
    {
        /** @var list<int> $ids */
        $ids = $request->validated('ids');

        $pictures = $party->pictures()
            ->whereIn('id', $ids)
            ->latest()
            ->get();

        $filename = Str::slug($party->name).'-photos.zip';

        $party->recordActivity(PartyActivityType::PicturesDownloaded, ['count' => $pictures->count()]);

        return response()->stream(
            function () use ($pictures, $filename): void {
                /**
                 * A large selection can take longer to stream than php-fpm's
                 * default max_execution_time, which would otherwise truncate
                 * the archive mid-flight without surfacing an error.
                 */
                set_time_limit(0);

                /** @var FilesystemAdapter $disk */
                $disk = Storage::disk('s3');

                $zip = new ZipStream(
                    outputName: $filename,
                    sendHttpHeaders: false,
                    defaultCompressionMethod: CompressionMethod::STORE,
                );

                $pictures->values()->each(function (Picture $picture, int $index) use ($zip, $disk): void {
                    $stream = $disk->readStream($picture->path);

                    if ($stream === null) {
                        return;
                    }

                    $zip->addFileFromStream('photo-'.($index + 1).'.jpg', $stream);

                    fclose($stream);
                });

                $zip->finish();
            },
            200,
            [
                'Content-Type' => 'application/zip',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'X-Accel-Buffering' => 'no',
            ],
        );
    }
}
