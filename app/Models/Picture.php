<?php

namespace App\Models;

use App\Services\PartyPicturesState;
use App\Services\SignedStorageUrl;
use Database\Factories\PictureFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
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
        'original_name',
        'thumbnail_path',
        'width',
        'height',
        'size',
        'hidden_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'hidden_at' => 'datetime',
        ];
    }

    protected static function booting(): void
    {
        static::creating(function (Picture $picture): void {
            $picture->path ??= 'pictures/'.Str::uuid().'.jpg';
        });

        static::saved(function (Picture $picture): void {
            app(PartyPicturesState::class)->forget($picture->party_id);
        });

        static::deleted(function (Picture $picture): void {
            $paths = array_filter([$picture->path, $picture->thumbnail_path]);
            Storage::disk('s3')->delete($paths);

            app(PartyPicturesState::class)->forget($picture->party_id);
        });
    }

    /**
     * Restrict a query to the pictures guests are allowed to see.
     *
     * A host may hide a picture without deleting it; the gallery, the picture
     * stream and the polled count all go through this scope so that one column
     * governs them. The ZIP reaches the same result upstream, its form request
     * rejecting hidden ids through `PictureBelongsToParty`.
     *
     * @param  Builder<Picture>  $query
     */
    public function scopeVisible(Builder $query): void
    {
        $query->whereNull('hidden_at');
    }

    /**
     * Whether the host has taken this picture off the guests' gallery.
     */
    public function isHidden(): bool
    {
        return $this->hidden_at !== null;
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
        return SignedStorageUrl::for($this->path);
    }

    /**
     * Get the full URL to the thumbnail on S3/Minio.
     *
     * Returns null while the thumbnail job has not run yet. Callers must render
     * a placeholder rather than fall back to the original, which weighs several
     * megabytes and would be downscaled to a grid cell by the browser.
     */
    public function thumbnailUrl(): ?string
    {
        if (! $this->thumbnail_path) {
            return null;
        }

        return SignedStorageUrl::for($this->thumbnail_path);
    }
}
