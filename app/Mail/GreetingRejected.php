<?php

namespace App\Mail;

use App\Models\Party;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GreetingRejected extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Party $party, public string $reason) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Votre annonce est à refaire');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.greeting-rejected', with: [
            'clientUrl' => $this->party->audiobookUrl(),
            'reason' => $this->reason,
        ]);
    }
}
