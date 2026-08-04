<?php

namespace App\Services\PartySources;

use Carbon\CarbonImmutable;

class StubPartySource implements PartySource
{
    public function key(): string
    {
        return 'stub';
    }

    public function label(): string
    {
        return 'Local stub';
    }

    /**
     * Hard coded events, so the admin can be used without any provider
     * configured. Enabled through the PARTY_SOURCE_STUB environment variable.
     *
     * @return list<PartySourceEvent>
     */
    public function events(): array
    {
        $today = CarbonImmutable::today();

        return [
            new PartySourceEvent(
                id: 'stub-domaine-des-tilleuls',
                name: 'Mariage Domaine des Tilleuls',
                startsAt: $today->addDays(12)->setTime(18, 0),
            ),
            new PartySourceEvent(
                id: 'stub-chateau-de-la-motte',
                name: 'Mariage Château de la Motte',
                startsAt: $today->addDays(26)->setTime(17, 30),
            ),
            new PartySourceEvent(
                id: 'stub-soiree-teclib',
                name: 'Soirée Teclib',
                startsAt: $today->addDays(45)->setTime(20, 0),
            ),
            new PartySourceEvent(
                id: 'stub-anniversaire-lucie',
                name: 'Anniversaire Lucie',
                startsAt: $today->subDays(9)->setTime(19, 0),
            ),
        ];
    }
}
