<?php

namespace App\Models;

use App\Attributes\CrudCountColumn;
use App\Attributes\CrudRelationColumn;
use App\Attributes\CrudResource;
use Database\Factories\PartyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[CrudResource(
    label: 'Party',
    labelPlural: 'Parties',
)]
#[CrudRelationColumn(
    column: 'user_id',
    model: User::class,
    labelColumn: 'name',
)]
#[CrudCountColumn(
    name: 'pictures_count',
    label: 'Pictures',
    relation: 'pictures',
    linkHref: '/admin/parties',
)]
class Party extends Model
{
    /** @use HasFactory<PartyFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'user_id',
        'expire_at',
        'source_key',
        'source_event_id',
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

    /**
     * Determine whether the party's pictures should be hidden.
     * Pictures are hidden once the party's expiry date has passed.
     */
    public function hidePictures(): bool
    {
        return $this->expire_at !== null && $this->expire_at->isPast();
    }

    /**
     * Host the party's gallery is published under, the name being the
     * subdomain part. This is what gets printed on the party's poster.
     */
    public function publicHost(): string
    {
        return $this->name.'.'.config('party.domain');
    }

    /**
     * Absolute URL of the party's gallery, as encoded in the poster QR code.
     */
    public function publicUrl(): string
    {
        return 'https://'.$this->publicHost();
    }

    public function getRouteKeyName(): string
    {
        return 'name';
    }
}
