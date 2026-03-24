<?php

namespace App\Models;

use Database\Factories\PictureFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Picture extends Model
{
    /** @use HasFactory<PictureFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'party_id',
        'path',
        'thumbnail_path',
        'width',
        'height',
        'unique_code',
    ];

    protected static function booting(): void
    {
        static::creating(function (Picture $picture): void {
            $picture->path ??= 'pictures/'.Str::uuid().'.jpg';
        });

        static::deleted(function (Picture $picture): void {
            $paths = array_filter([$picture->path, $picture->thumbnail_path]);
            Storage::disk('s3')->delete($paths);
        });
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * Get the full URL to the picture on S3/Minio.
     */
    public function url(): string
    {
        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('s3');

        return $disk->url($this->path);
    }

    /**
     * Get the full URL to the thumbnail on S3/Minio.
     */
    public function thumbnailUrl(): string
    {
        if (! $this->thumbnail_path) {
            return $this->url();
        }

        /** @var FilesystemAdapter $disk */
        $disk = Storage::disk('s3');

        return $disk->url($this->thumbnail_path);
    }
}
