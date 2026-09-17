<?php

namespace App\Models;

use App\Enums\GreetingStatus;
use App\Services\SignedStorageUrl;
use Database\Factories\AudioGreetingFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AudioGreeting extends Model
{
    /** @use HasFactory<AudioGreetingFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'party_id',
        'original_path',
        'mp3_path',
        'duration_seconds',
        'client_note',
        'status',
        'rejection_reason',
        'submitted_at',
        'validated_at',
    ];

    /** @var array<string, string> */
    protected $attributes = [
        'status' => 'draft',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => GreetingStatus::class,
            'submitted_at' => 'datetime',
            'validated_at' => 'datetime',
            'duration_seconds' => 'integer',
        ];
    }

    protected static function booting(): void
    {
        static::deleted(function (AudioGreeting $greeting): void {
            Storage::disk('s3')->delete(array_filter([
                $greeting->original_path,
                $greeting->mp3_path,
            ]));
        });
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * Signed URL of the converted greeting, or null while the conversion job
     * has not run yet. Callers must not fall back to the original: the browser
     * that recorded it produced WebM or MP4, which the handset cannot read and
     * Safari will not play back reliably.
     */
    public function url(): ?string
    {
        if (! $this->mp3_path) {
            return null;
        }

        return SignedStorageUrl::for($this->mp3_path);
    }
}
