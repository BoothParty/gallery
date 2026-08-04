<?php

namespace App\Services\PartySources;

interface PartySource
{
    /**
     * Stable identifier of the source, matching its key in config('party.sources').
     */
    public function key(): string;

    /**
     * Human readable name of the source, displayed in the admin.
     */
    public function label(): string;

    /**
     * Events the source knows about, upcoming ones included; filtering and
     * ordering are handled by the PartySourceManager.
     *
     * @return list<PartySourceEvent>
     */
    public function events(): array;
}
