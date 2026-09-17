<?php

namespace App\Mail;

use App\Models\Party;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The link that opens the client's private space.
 *
 * Never sent automatically: the studio decides when a client is ready to
 * receive it.
 */
class AudiobookReady extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Party $party) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: "Votre livre d'or audio est prêt");
    }

    public function content(): Content
    {
        return new Content(view: 'emails.audiobook-ready', with: [
            'clientUrl' => $this->party->audiobookUrl(),
        ]);
    }
}
