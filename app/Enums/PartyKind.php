<?php

namespace App\Enums;

/**
 * What a party is for. An event is published under its own subdomain with a
 * poster; a transfer only delivers a photobooth's pictures after the fact,
 * behind an unguessable link.
 */
enum PartyKind: string
{
    case Event = 'event';
    case Transfer = 'transfer';
}
