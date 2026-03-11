<?php

namespace App\Models;

use Database\Factories\PartyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Party extends Model
{
    /** @use HasFactory<PartyFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'user_id',
        'expire_at',
    ];

    protected function casts(): array
    {
        return [
            'expire_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function pictures(): HasMany
    {
        return $this->hasMany(Picture::class);
    }
}
