<?php

namespace App\Models;

use App\Enums\PartyActivityType;
use App\Enums\PartyKind;
use App\Services\SignedStorageUrl;
use Database\Factories\PartyFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class Party extends Model
{
    /** @use HasFactory<PartyFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'kind',
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

    /**
     * Mirror the DB defaults so a freshly created instance frames its banner
     * centred and unzoomed rather than reporting nulls to whatever renders it
     * before the row is read back.
     *
     * @var array<string, int|float>
     */
    protected $attributes = [
        'kind' => 'event',
        'banner_position_x' => 50,
        'banner_position_y' => 50,
        'banner_zoom' => 1,
    ];

    protected function casts(): array
    {
        return [
            'kind' => PartyKind::class,
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
     * What an admin table row sums up: the picture count, their total weight
     * and the first four thumbnails, loaded with a limit per party.
     *
     * @param  Builder<Party>  $query
     */
    public function scopeWithPicturesSummary(Builder $query): void
    {
        $query->withCount('pictures')
            ->withSum('pictures', 'size')
            ->with(['pictures' => fn (HasMany $pictures) => $pictures->whereNotNull('thumbnail_path')->oldest('id')->limit(4)]);
    }

    /**
     * @return array{pictures_count: int, pictures_size: int, thumbnail_urls: list<string|null>}
     */
    public function picturesSummary(): array
    {
        return [
            'pictures_count' => $this->pictures_count,
            'pictures_size' => (int) $this->pictures_sum_size,
            'thumbnail_urls' => $this->pictures->map->thumbnailUrl()->values()->all(),
        ];
    }

    public function printJobs(): HasManyThrough
    {
        return $this->hasManyThrough(PrintJob::class, Picture::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(PartyActivity::class);
    }

    /**
     * Log what happened to the party for its dashboard feed. A repeat of the
     * latest entry of the same type within its fold window adds its count to
     * that entry instead of starting a new one.
     *
     * ponytail: read-then-write, two concurrent booth uploads can lose one
     * count. Fine for a feed; lock the row if the counts ever feed billing.
     *
     * @param  array{count?: int}  $properties
     */
    public function recordActivity(PartyActivityType $type, array $properties = []): PartyActivity
    {
        $window = $type->foldWithin();

        $latest = $window === null ? null : $this->activities()
            ->where('type', $type)
            ->where('updated_at', '>=', now()->sub($window))
            ->latest('updated_at')
            ->first();

        if ($latest === null) {
            return $this->activities()->create(['type' => $type, 'properties' => $properties]);
        }

        if (isset($properties['count'])) {
            $latest->properties = ['count' => ($latest->properties['count'] ?? 0) + $properties['count']];
        }

        $latest->touch();

        return $latest;
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

    public function isTransfer(): bool
    {
        return $this->kind === PartyKind::Transfer;
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
     * A transfer has no subdomain: its gallery lives under the app's own
     * domain, behind the uuid it is named by.
     */
    public function publicUrl(): string
    {
        if ($this->isTransfer()) {
            return route('transfers.show', $this);
        }

        return 'https://'.$this->publicHost();
    }

    /**
     * URL the poster's QR code encodes. The marker is what tells a scan of the
     * printed poster apart from a link an invited guest passed on, the two
     * being the only ways into a gallery.
     */
    public function posterUrl(): string
    {
        return $this->publicUrl().'/?s=poster';
    }

    public function getRouteKeyName(): string
    {
        return 'name';
    }
}
