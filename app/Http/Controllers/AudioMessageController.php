<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAudioMessageRequest;
use App\Models\AudioMessage;
use App\Models\Party;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;
use ZipStream\CompressionMethod;
use ZipStream\ZipStream;

class AudioMessageController extends Controller
{
    /**
     * Let the client name a message.
     *
     * The studio never sees these names: it does not know whose voice is whose,
     * and the client does.
     */
    public function update(
        UpdateAudioMessageRequest $request,
        Party $party,
        AudioMessage $message,
    ): RedirectResponse {
        abort_unless($message->party_id === $party->id, 404);

        $message->update(['title' => $request->validated('title') ?: null]);

        return back();
    }

    /**
     * Stream every message as one ZIP.
     *
     * Built on the fly from the S3 originals, like the gallery's own archive,
     * so neither a temporary file nor the whole set ever lands in memory.
     */
    public function download(Party $party): StreamedResponse
    {
        $messages = $party->audioMessages()->get();

        abort_if($messages->isEmpty(), 404);

        $filename = Str::slug($party->displayTitle()).'-messages.zip';

        return response()->stream(
            function () use ($messages, $filename): void {
                /*
                 * A long set can take longer to stream than php-fpm's default
                 * max_execution_time, which would truncate the archive
                 * mid-flight without surfacing an error.
                 */
                set_time_limit(0);

                /** @var FilesystemAdapter $disk */
                $disk = Storage::disk('s3');

                $zip = new ZipStream(
                    outputName: $filename,
                    sendHttpHeaders: false,
                    defaultCompressionMethod: CompressionMethod::STORE,
                );

                $messages->each(function (AudioMessage $message) use ($zip, $disk): void {
                    $stream = $disk->readStream($message->path);

                    if ($stream === null) {
                        return;
                    }

                    $zip->addFileFromStream(
                        str_pad((string) $message->position, 2, '0', STR_PAD_LEFT).'-'.Str::slug($message->displayTitle()).'.mp3',
                        $stream,
                    );

                    fclose($stream);
                });

                $zip->finish();
            },
            200,
            [
                'Content-Type' => 'application/zip',
                'Content-Disposition' => 'attachment; filename="'.$filename.'"',
                'X-Accel-Buffering' => 'no',
            ],
        );
    }
}
