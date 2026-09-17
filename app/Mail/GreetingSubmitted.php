<?php

namespace App\Mail;

use App\Models\Party;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * The only one addressed to the studio rather than the client.
 */
class GreetingSubmitted extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Party $party) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: $this->party->displayTitle().' : annonce à valider');
    }

    public function content(): Content
    {
        $greeting = $this->party->greeting()->first();
        $seconds = $greeting?->duration_seconds;

        return new Content(view: 'emails.greeting-submitted', with: [
            'partyTitle' => $this->party->displayTitle(),
            'studioUrl' => route('admin.audiobook.show', $this->party->id),
            'clientNote' => $greeting?->client_note,
            'duration' => $seconds === null
                ? null
                : floor($seconds / 60).' min '.str_pad((string) ($seconds % 60), 2, '0', STR_PAD_LEFT),
        ]);
    }
}
