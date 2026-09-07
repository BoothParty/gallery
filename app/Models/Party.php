<?php

namespace App\Models;

use App\Attributes\CrudCountColumn;
use App\Attributes\CrudRelationColumn;
use App\Attributes\CrudResource;
use App\Services\SignedStorageUrl;
use Database\Factories\PartyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

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
        'display_name',
        'description',
        'banner_position_x',
        'banner_position_y',
        'banner_zoom',
        'user_id',
        'expire_at',
        'source_key',
        'source_event_id',
    ];

    protected function casts(): array
    {
        return [
            'expire_at' => 'datetime',
            'banner_position_x' => 'float',
            'banner_position_y' => 'float',
            'banner_zoom' => 'float',
        ];
    }

    protected static function booting(): void
    {
        static::creating(function (Party $party): void {
            $party->host_token ??= Str::random(48);
        });

        static::deleted(function (Party $party): void {
            if ($party->banner_path) {
                Storage::disk('s3')->delete($party->banner_path);
            }
        });
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
     * Title the gallery is published under, the name serving as a fallback for
     * a party whose host never set a display name.
     */
    public function displayTitle(): string
    {
        return $this->display_name ?: $this->name;
    }

    /**
     * Get the full URL to the banner on S3/Minio, or null when the party has
     * none, in which case the gallery hero renders its striped placeholder.
     */
    public function bannerUrl(): ?string
    {
        if (! $this->banner_path) {
            return null;
        }

        return SignedStorageUrl::for($this->banner_path);
    }

    /**
     * Link the host uses to edit their own gallery. The only place that knows
     * the shape of that link.
     */
    public function hostEditUrl(): string
    {
        return $this->publicUrl().'/?token='.$this->host_token;
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
