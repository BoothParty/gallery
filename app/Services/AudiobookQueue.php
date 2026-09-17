<?php

namespace App\Services;

use App\Enums\GreetingStatus;
use App\Models\AudioGreeting;
use App\Models\Party;
use Illuminate\Database\Eloquent\Collection;

/**
 * What the studio still has to do on the audio guestbook, across every party.
 *
 * The team's daily question is "what is waiting for me", not "let us open
 * party X", so the three lists are computed together from one pass rather than
 * discovered party by party.
 */
class AudiobookQueue
{
    /**
     * Parties whose greeting is waiting for a decision.
     *
     * @return list<array<string, mixed>>
     */
    public function awaitingValidation(): array
    {
        return $this->parties()
            ->filter(fn (Party $party): bool => $party->greeting?->status === GreetingStatus::Pending)
            ->map(fn (Party $party): array => $this->row($party))
            ->values()
            ->all();
    }

    /**
     * Parties whose event has passed and whose guests' messages have not been
     * uploaded yet.
     *
     * @return list<array<string, mixed>>
     */
    public function awaitingUpload(): array
    {
        return $this->parties()
            ->filter(fn (Party $party): bool => $party->event_at !== null
                && $party->event_at->isPast()
                && $party->audio_messages_count === 0)
            ->map(fn (Party $party): array => $this->row($party))
            ->values()
            ->all();
    }

    /**
     * Parties whose event is still ahead and whose greeting is not validated.
     *
     * Informative only: the client is the one running late, and the J-7
     * reminder goes out on its own.
     *
     * @return list<array<string, mixed>>
     */
    public function withoutGreeting(): array
    {
        return $this->parties()
            ->filter(fn (Party $party): bool => $party->event_at !== null
                && $party->event_at->isFuture()
                && $party->greeting?->status !== GreetingStatus::Validated
                && $party->greeting?->status !== GreetingStatus::Pending)
            ->map(fn (Party $party): array => $this->row($party))
            ->values()
            ->all();
    }

    /**
     * What the sidebar badge counts: the two lists that ask something of the
     * studio. "Without a greeting" is deliberately left out — nobody at the
     * studio can act on it.
     */
    public function pendingCount(): int
    {
        return count($this->awaitingValidation()) + count($this->awaitingUpload());
    }

    /**
     * Every party carrying the module, with its greeting and message count.
     * Loaded once per request: the three lists and the badge all read it.
     *
     * @return Collection<int, Party>
     */
    private function parties(): Collection
    {
        return once(fn (): Collection => Party::query()
            ->where('has_audio_guestbook', true)
            ->with('greeting')
            ->withCount('audioMessages')
            ->orderBy('event_at')
            ->get());
    }

    /**
     * @return array<string, mixed>
     */
    private function row(Party $party): array
    {
        $greeting = $party->greeting;

        return [
            'id' => $party->id,
            'name' => $party->displayTitle(),
            'route_key' => $party->name,
            'event_at' => $party->event_at?->toIso8601String(),
            'client_email' => $party->client_email,
            'messages_count' => $party->audio_messages_count,
            'greeting' => $greeting instanceof AudioGreeting ? [
                'status' => $greeting->status->value,
                'duration_seconds' => $greeting->duration_seconds,
                'submitted_at' => $greeting->submitted_at?->toIso8601String(),
            ] : null,
        ];
    }
}
