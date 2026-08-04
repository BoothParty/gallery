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

];
