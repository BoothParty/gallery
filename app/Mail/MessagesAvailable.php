<?php

namespace App\Mail;

use App\Models\Party;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class MessagesAvailable extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Party $party) {}

    public function envelope(): Envelope
    {
        $count = $this->party->audioMessages()->count();

        return new Envelope(subject: $count > 1
            ? "Vos {$count} messages sont en ligne"
            : 'Votre message est en ligne');
    }

    public function content(): Content
    {
        $messages = $this->party->audioMessages()->get();
        $total = (int) $messages->sum('duration_seconds');

        return new Content(view: 'emails.messages-available', with: [
            'clientUrl' => $this->party->audiobookUrl(),
            'count' => $messages->count(),
            'total' => floor($total / 60).' min '.str_pad((string) ($total % 60), 2, '0', STR_PAD_LEFT),
        ]);
    }
}
