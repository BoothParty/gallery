<?php

namespace App\Services;

use App\Models\Party;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class AudiobookMailer
{
    /**
     * Send a message to the client of a party, if we have an address for them.
     *
     * A party without a client address is not an error: the studio may not
     * have it yet, and failing the action that triggered the mail — validating
     * a greeting, uploading the messages — would be a far worse outcome than a
     * mail nobody receives.
     */
    public function toClient(Party $party, Mailable $mailable): bool
    {
        if (blank($party->client_email)) {
            Log::info('Audio guestbook mail skipped: the party has no client address.', [
                'party_id' => $party->id,
                'mailable' => $mailable::class,
            ]);

            return false;
        }

        Mail::to($party->client_email)->queue($mailable);

        return true;
    }

    /**
     * Send a message to the studio mailbox, when one is configured.
     */
    public function toStudio(Mailable $mailable): bool
    {
        $address = config('party.studio_email');

        if (blank($address)) {
            return false;
        }

        Mail::to($address)->queue($mailable);

        return true;
    }
}
