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

    /**
     * Mirror the DB defaults so a freshly created instance frames its banner
     * centred and unzoomed rather than reporting nulls to whatever renders it
     * before the row is read back.
     *
     * @var array<string, int|float>
     */
    protected $attributes = [
        'banner_position_x' => 50,
        'banner_position_y' => 50,
        'banner_zoom' => 1,
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
     * Validation the name carries because it is published as a subdomain: one
     * DNS label, so lowercase letters, digits and inner hyphens only, and 63
     * characters at most.
     *
     * The gallery is addressed by this name; a value a browser cannot resolve
     * would be a party nobody can reach.
     *
     * @return list<string>
     */
    public static function nameRules(): array
    {
        return ['string', 'max:63', 'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/'];
    }

    /**
     * The message the name rules deserve, none of them saying by themselves
     * that the name is an address.
     */
    public static function nameRuleMessage(): string
    {
        return 'The party name is published as a subdomain: lowercase letters, digits and hyphens only.';
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
     *
     * The scheme follows the application's own: a local install serves its
     * galleries over http, and a link the host cannot open is worse than an
     * ugly one.
     */
    public function publicUrl(): string
    {
        $scheme = str_starts_with((string) config('app.url'), 'https://') ? 'https' : 'http';

        return $scheme.'://'.$this->publicHost();
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
