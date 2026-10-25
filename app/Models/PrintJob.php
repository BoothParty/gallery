<?php

namespace App\Models;

use Database\Factories\PrintJobFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PrintJob extends Model
{
    /** @use HasFactory<PrintJobFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'printed_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'printed_at' => 'datetime',
        ];
    }

    /**
     * Restrict a query to the jobs the booth has not printed yet.
     *
     * @param  Builder<PrintJob>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('printed_at');
    }

    public function picture(): BelongsTo
    {
        return $this->belongsTo(Picture::class);
    }
}
