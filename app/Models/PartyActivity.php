<?php

namespace App\Models;

use App\Enums\PartyActivityType;
use Database\Factories\PartyActivityFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PartyActivity extends Model
{
    /** @use HasFactory<PartyActivityFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'type',
        'properties',
    ];

    protected function casts(): array
    {
        return [
            'type' => PartyActivityType::class,
            'properties' => 'array',
        ];
    }

    public function party(): BelongsTo
    {
        return $this->belongsTo(Party::class);
    }

    public function description(): string
    {
        return $this->type->description($this->properties);
    }
}
