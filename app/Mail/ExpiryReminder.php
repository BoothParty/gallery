<?php

namespace App\Mail;

use App\Models\Party;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ExpiryReminder extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Party $party) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: 'Vos enregistrements expirent dans une semaine');
    }

    public function content(): Content
    {
        return new Content(view: 'emails.expiry-reminder', with: [
            'clientUrl' => $this->party->audiobookUrl(),
            'expiresOn' => $this->party->expire_at?->locale('fr')->isoFormat('D MMMM YYYY') ?? 'bientôt',
        ]);
    }
}
