<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Picture extends Model
{
    /** @use HasFactory<\Database\Factories\PictureFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'party_id',
        'path',
        'original_name',
        'mime_type',
        'size',
    ];

    /**
     * Get the party that owns the picture.
     */
    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }
}
