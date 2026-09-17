<?php

namespace App\Enums;

/**
 * Lifecycle of a client's greeting, from the take they record to the message
 * the studio puts on the handset.
 */
enum GreetingStatus: string
{
    case Draft = 'draft';
    case Pending = 'pending';
    case Validated = 'validated';
    case Rejected = 'rejected';

    /**
     * Whether the studio still has to act on this greeting.
     */
    public function awaitsStudio(): bool
    {
        return $this === self::Pending;
    }
}
