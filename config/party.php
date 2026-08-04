<?php

use App\Services\PartySources\NotionPartySource;

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
    */

    'sources' => [
        'notion' => NotionPartySource::class,
    ],

];
