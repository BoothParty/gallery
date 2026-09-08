<?php

namespace App\Jobs;

use App\Models\Picture;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;
use Throwable;

class CreatePictureThumbnail implements ShouldQueue
{
    use Queueable;

    /**
     * Largest edge, in pixels, of a generated thumbnail.
     *
     * Grid cells top out at 240 CSS px, so this still covers 2x DPR screens
     * while staying ~3x lighter than a 800px thumbnail.
     */
    public const MAX_EDGE = 480;

    /**
     * Retry delays, in seconds.
     *
     * The failures worth retrying here are S3 hiccups, which an immediate
     * retry would only run into again.
     *
     * @var list<int>
     */
    public $backoff = [10, 60];

    /**
     * Create a new job instance.
     */
    public function __construct(public Picture $picture) {}

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $disk = Storage::disk('s3');

        if (! $disk->exists($this->picture->path)) {
            return;
        }

        $fileName = pathinfo($this->picture->path, PATHINFO_FILENAME);
        $thumbnailPath = "pictures/{$this->picture->party_id}/{$fileName}_thumb.jpg";

        $image = (new ImageManager(new Driver))->read($disk->get($this->picture->path));
        $image->scaleDown(width: self::MAX_EDGE, height: self::MAX_EDGE);

        $disk->put($thumbnailPath, $image->toJpeg(quality: 80));

        $this->picture->update(['thumbnail_path' => $thumbnailPath]);
    }

    /**
     * A picture whose thumbnail never arrives keeps rendering its placeholder
     * in the grid, quietly and for good. Log it so that state has a cause.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('Thumbnail generation failed', [
            'picture_id' => $this->picture->id,
            'party_id' => $this->picture->party_id,
            'exception' => $exception,
        ]);
    }
}
