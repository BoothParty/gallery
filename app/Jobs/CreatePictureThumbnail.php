<?php

namespace App\Jobs;

use App\Models\Picture;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Drivers\Gd\Driver;
use Intervention\Image\ImageManager;

class CreatePictureThumbnail implements ShouldQueue
{
    use Queueable;

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
        $image->scaleDown(width: 800, height: 800);

        $disk->put($thumbnailPath, $image->toJpeg(quality: 80));

        $this->picture->update(['thumbnail_path' => $thumbnailPath]);
    }
}
