<?php

namespace App\Models;

use App\Services\SignedStorageUrl;
use Database\Factories\AudioMessageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class AudioMessage extends Model
{
    /** @use HasFactory<AudioMessageFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'party_id',
        'path',
        'title',
        'duration_seconds',
        'recorded_at',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'recorded_at' => 'datetime',
            'duration_seconds' => 'integer',
            'position' => 'integer',
        ];
    }

    protected static function booting(): void
    {
        static::deleted(function (AudioMessage $message): void {
            Storage::disk('s3')->delete($message->path);
        });
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    /**
     * What the client sees in their list. Naming a message is theirs to do and
     * always optional, so an unnamed one falls back to its order of passage.
     */
    public function displayTitle(): string
    {
        return $this->title ?: 'Message '.str_pad((string) $this->position, 2, '0', STR_PAD_LEFT);
    }

    public function url(): string
    {
        return SignedStorageUrl::for($this->path);
    }
}
