<?php

use App\Services\PartySources\NotionPartySource;
use App\Services\PartySources\StubPartySource;

return [

    /*
    |--------------------------------------------------------------------------
    | Party Domain
    |--------------------------------------------------------------------------
    |
    | Base domain the party names are published under. The admin uses it as the
    | suffix displayed next to the party name inputs, the name being the
    | subdomain part.
    |
    */

    'domain' => env('PARTY_DOMAIN', 'boothparty.fr'),

    /*
    |--------------------------------------------------------------------------
    | Party Sources
    |--------------------------------------------------------------------------
    |
    | Providers the admin can pull upcoming events from when creating several
    | parties at once. Each class must implement the PartySource contract; add
    | an entry here to enable another provider.
    |
    | Notion is only enabled once a token is configured. The stub source serves
    | hard coded events, for local development and tests without network access.
    |
    */

    'sources' => array_filter([
        'notion' => filled(env('NOTION_API_TOKEN')) ? NotionPartySource::class : null,
        'stub' => env('PARTY_SOURCE_STUB', false) ? StubPartySource::class : null,
    ]),

    /*
    |--------------------------------------------------------------------------
    | Party Sources Cache
    |--------------------------------------------------------------------------
    |
    | Seconds the upcoming events are cached for. Inertia reloads the deferred
    | prop on every admin parties page view, so the cache is what keeps the
    | providers from being called over and over.
    |
    */

    'cache_ttl' => (int) env('PARTY_SOURCES_CACHE_TTL', 600),

    /*
    |--------------------------------------------------------------------------
    | Selection Download
    |--------------------------------------------------------------------------
    |
    | Ceiling on how many pictures a visitor may bundle into a single ZIP. The
    | archive is streamed rather than buffered, so the limit exists to protect
    | the S3 egress and the visitor's own connection, not the server memory.
    |
    */

    'download' => [
        'max_pictures' => (int) env('PARTY_DOWNLOAD_MAX_PICTURES', 300),
    ],

    /*
    |--------------------------------------------------------------------------
    | Gallery Auto-Refresh
    |--------------------------------------------------------------------------
    |
    | Cadence, in seconds, at which an open gallery polls for changes. The
    | gallery slows down as the party quiets: `hot` while a change is fresher
    | than `warm_after`, `warm` up to `cold_after`, `cold` beyond. An empty
    | gallery stays hot, it being the case the whole feature exists for.
    |
    | `cache_ttl` is only a guard. The aggregate the poll reads is invalidated
    | by picture writes, so the TTL exists to bound the damage of a future
    | write that skips the model events, not to keep the value fresh.
    |
    */

    'refresh' => [
        'hot' => (int) env('PARTY_REFRESH_HOT', 10),
        'warm' => (int) env('PARTY_REFRESH_WARM', 30),
        'cold' => (int) env('PARTY_REFRESH_COLD', 60),
        'warm_after' => (int) env('PARTY_REFRESH_WARM_AFTER', 120),
        'cold_after' => (int) env('PARTY_REFRESH_COLD_AFTER', 300),
        'cache_ttl' => (int) env('PARTY_REFRESH_CACHE_TTL', 600),
    ],

];
