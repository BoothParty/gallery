<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreAudioMessagesRequest;
use App\Mail\MessagesAvailable;
use App\Models\AudioMessage;
use App\Models\Party;
use App\Services\AudiobookMailer;
use App\Services\AudioDuration;
use Carbon\CarbonImmutable;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\UploadedFile;

class AudioMessageController extends Controller
{
    /**
     * Take the recordings off the handset.
     *
     * Files are stored untouched — they already come out as MP3 — and ordered
     * by the moment they were recorded when the browser could report it, by
     * upload order otherwise. Nothing here asks the studio to type anything:
     * naming belongs to the client, who recognises the voices.
     */
    public function store(
        StoreAudioMessagesRequest $request,
        Party $party,
        AudioDuration $audioDuration,
        AudiobookMailer $audiobookMailer,
    ): RedirectResponse {
        /** @var list<UploadedFile> $files */
        $files = $request->file('messages');

        /** @var list<string|null> $recordedAt */
        $recordedAt = $request->validated('recorded_at') ?? [];

        $position = (int) $party->audioMessages()->max('position');

        $uploads = collect($files)
            ->map(fn (UploadedFile $file, int $index): array => [
                'file' => $file,
                'duration' => $audioDuration->forFile($file->getRealPath()),
                'recorded_at' => isset($recordedAt[$index]) && $recordedAt[$index] !== null
                    ? CarbonImmutable::parse($recordedAt[$index])
                    : null,
            ])
            ->sortBy(fn (array $upload, int $index): string => $upload['recorded_at']?->toIso8601String()
                ?? str_pad((string) $index, 6, '0', STR_PAD_LEFT))
            ->values();

        foreach ($uploads as $upload) {
            /** @var UploadedFile $file */
            $file = $upload['file'];

            $party->audioMessages()->create([
                'path' => $file->store('parties/'.$party->id.'/audio', 's3'),
                'duration_seconds' => $upload['duration'],
                'recorded_at' => $upload['recorded_at'],
                'position' => ++$position,
            ]);
        }

        $audiobookMailer->toClient($party->fresh(), new MessagesAvailable($party->fresh()));

        return back();
    }

    /**
     * Drop one message. Useful when the handset caught a hang-up or silence.
     */
    public function destroy(Party $party, AudioMessage $message): RedirectResponse
    {
        abort_unless($message->party_id === $party->id, 404);

        $message->delete();

        return back();
    }
}
