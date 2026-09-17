<?php

namespace App\Console\Commands\Audiobook;

use App\Enums\GreetingStatus;
use App\Mail\ExpiryReminder;
use App\Mail\GreetingReminder;
use App\Models\Party;
use App\Services\AudiobookMailer;
use Illuminate\Console\Command;

class SendReminders extends Command
{
    /**
     * Days before a deadline a client is nudged.
     */
    public const LEAD_DAYS = 7;

    protected $signature = 'audiobook:remind';

    protected $description = "Relance les clients dont l'annonce manque ou dont les enregistrements expirent";

    public function handle(AudiobookMailer $audiobookMailer): int
    {
        $greetings = $this->remindMissingGreetings($audiobookMailer);
        $expiries = $this->remindExpiringRecordings($audiobookMailer);

        $this->components->info("Relances envoyées : {$greetings} annonce(s), {$expiries} expiration(s).");

        return self::SUCCESS;
    }

    /**
     * Nudge the clients whose event is a week away and whose greeting is not
     * validated yet.
     *
     * The window reaches back rather than covering a single day: a run that
     * never happened — a machine down, a deploy — would otherwise drop the
     * reminder for good. The stamp is what keeps it sent once.
     */
    private function remindMissingGreetings(AudiobookMailer $audiobookMailer): int
    {
        $sent = 0;

        Party::query()
            ->where('has_audio_guestbook', true)
            ->whereNull('greeting_reminder_sent_at')
            ->whereNotNull('event_at')
            ->whereBetween('event_at', [now(), now()->addDays(self::LEAD_DAYS)])
            ->with('greeting')
            ->each(function (Party $party) use ($audiobookMailer, &$sent): void {
                if ($party->greeting?->status === GreetingStatus::Validated) {
                    return;
                }

                $audiobookMailer->toClient($party, new GreetingReminder($party));
                $party->forceFill(['greeting_reminder_sent_at' => now()])->save();
                $sent++;
            });

        return $sent;
    }

    /**
     * Warn the clients whose recordings come off the shelves in a week, as
     * long as they have something to lose.
     */
    private function remindExpiringRecordings(AudiobookMailer $audiobookMailer): int
    {
        $sent = 0;

        Party::query()
            ->where('has_audio_guestbook', true)
            ->whereNull('expiry_reminder_sent_at')
            ->whereNotNull('expire_at')
            ->whereBetween('expire_at', [now(), now()->addDays(self::LEAD_DAYS)])
            ->has('audioMessages')
            ->each(function (Party $party) use ($audiobookMailer, &$sent): void {
                $audiobookMailer->toClient($party, new ExpiryReminder($party));
                $party->forceFill(['expiry_reminder_sent_at' => now()])->save();
                $sent++;
            });

        return $sent;
    }
}
