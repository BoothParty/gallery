<?php

namespace App\Mail;

use App\Models\Party;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class GreetingReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Party $party) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Il reste une semaine pour enregistrer votre annonce');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.greeting-reminder', with: [
            'clientUrl' => $this->party->audiobookUrl(),
        ]);
    }
}
