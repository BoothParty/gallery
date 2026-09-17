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
use Illuminate\Database\Eloquent\Relations\HasOne;
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

    /**
     * Names a party may not take: `/{party}` is a catch-all prefix, so a party
     * called `audiobook` or `pictures` would shadow the literal routes of the
     * uuid-addressed surfaces.
     *
     * @var list<string>
     */
    public const RESERVED_NAMES = ['audiobook', 'pictures'];

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
        'gallery_uuid',
        'client_uuid',
        'has_gallery',
        'has_audio_guestbook',
        'client_email',
        'event_at',
        'greeting_reminder_sent_at',
        'expiry_reminder_sent_at',
    ];

    /**
     * Mirror the DB defaults so a freshly created instance frames its banner
     * centred and unzoomed rather than reporting nulls to whatever renders it
     * before the row is read back.
     *
     * @var array<string, int|float|bool>
     */
    protected $attributes = [
        'banner_position_x' => 50,
        'banner_position_y' => 50,
        'banner_zoom' => 1,
        'has_gallery' => true,
        'has_audio_guestbook' => false,
    ];

    protected function casts(): array
    {
        return [
            'expire_at' => 'datetime',
            'banner_position_x' => 'float',
            'banner_position_y' => 'float',
            'banner_zoom' => 'float',
            'event_at' => 'datetime',
            'greeting_reminder_sent_at' => 'datetime',
            'expiry_reminder_sent_at' => 'datetime',
            'has_gallery' => 'boolean',
            'has_audio_guestbook' => 'boolean',
        ];
    }

    protected static function booting(): void
    {
        static::creating(function (Party $party): void {
            $party->host_token ??= Str::random(48);
            $party->gallery_uuid ??= (string) Str::uuid();
            $party->client_uuid ??= (string) Str::uuid();
        });

        /**
         * The audio rows cascade at the database level, so their paths have to
         * be read while they still exist: by the time `deleted` fires they are
         * gone and their files would be stranded on the storage.
         */
        static::deleting(function (Party $party): void {
            $party->purgeAudioFiles();
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

    public function greeting(): HasOne
    {
        return $this->hasOne(AudioGreeting::class);
    }

    /**
     * The guests' messages, in the order they were recorded on the handset.
     */
    public function audioMessages(): HasMany
    {
        return $this->hasMany(AudioMessage::class)->orderBy('position');
    }

    /**
     * Remove every audio file this party owns from the storage.
     *
     * Both audio tables cascade on delete at the database level, which never
     * fires the models' own `deleted` hooks; without this the rows would go
     * and their files would stay on S3 forever.
     */
    private function purgeAudioFiles(): void
    {
        $paths = $this->audioMessages()->pluck('path')->all();

        $greeting = $this->greeting()->first();

        if ($greeting instanceof AudioGreeting) {
            $paths[] = $greeting->original_path;
            $paths[] = $greeting->mp3_path;
        }

        $paths = array_filter($paths);

        if ($paths !== []) {
            Storage::disk('s3')->delete($paths);
        }
    }

    /**
     * Title the gallery is published under, the display name falling back to
     * the subdomain, and that to a generic line for a party addressed by uuid
     * whose client never named their event.
     */
    public function displayTitle(): string
    {
        return $this->display_name ?: $this->name ?: 'Votre événement';
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
     * the shape of that link. Null for a party addressed by uuid, which has no
     * gallery to edit.
     */
    public function hostEditUrl(): ?string
    {
        if (! $this->hasSubdomain()) {
            return null;
        }

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
     * Link the client opens to record their greeting and collect their guests'
     * messages. The only place that knows the shape of that link.
     */
    public function audiobookUrl(): string
    {
        return route('audiobook.show', $this->client_uuid);
    }

    /**
     * Whether this party is published on a subdomain of its own. Only the
     * events sold as a published gallery are; the rest are addressed by uuid.
     */
    public function hasSubdomain(): bool
    {
        return filled($this->name);
    }

    /**
     * Host the party's gallery is published under, the name being the
     * subdomain part. This is what gets printed on the party's poster.
     */
    public function publicHost(): ?string
    {
        if (! $this->hasSubdomain()) {
            return null;
        }

        return $this->name.'.'.config('party.domain');
    }

    /**
     * Absolute URL of the party's gallery, as encoded in the poster QR code.
     */
    public function publicUrl(): ?string
    {
        if (! $this->hasSubdomain()) {
            return null;
        }

        return 'https://'.$this->publicHost();
    }

    /**
     * URL the poster's QR code encodes. The marker is what tells a scan of the
     * printed poster apart from a link an invited guest passed on, the two
     * being the only ways into a gallery.
     */
    public function posterUrl(): ?string
    {
        if (! $this->hasSubdomain()) {
            return null;
        }

        return $this->publicUrl().'/?s=poster';
    }

    public function getRouteKeyName(): string
    {
        return 'name';
    }
}
