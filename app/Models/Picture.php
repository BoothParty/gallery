<?php

namespace App\Models;

use Database\Factories\PictureFactory;
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
    ];

    protected static function booting(): void
    {
        static::creating(function (Picture $picture): void {
            $picture->path ??= 'pictures/'.Str::uuid().'.jpg';
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
        return Storage::disk('s3')->url($this->path);
    }
}
