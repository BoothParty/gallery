<?php

namespace App\Jobs;

use App\Models\AudioGreeting;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;
use Throwable;

class ConvertGreetingToMp3 implements ShouldQueue
{
    use Queueable;

    /**
     * Bitrate of the produced file.
     *
     * A greeting is one voice on a telephone handset: 96 kbps mono is already
     * beyond what the earpiece can render, and keeps a ninety-second message
     * near a megabyte.
     */
    public const BITRATE = '96k';

    /**
     * Seconds ffmpeg is given before the conversion is considered stuck. A
     * ninety-second take converts in well under a second; this is a ceiling
     * against a hung process, not a budget.
     */
    public const TIMEOUT = 120;

    /**
     * Retry delays, in seconds. What is worth retrying here is an S3 hiccup,
     * which an immediate retry would only run into again.
     *
     * @var list<int>
     */
    public $backoff = [10, 60];

    public function __construct(public AudioGreeting $greeting) {}

    /**
     * Turn the browser's recording into the MP3 the handset plays.
     *
     * ffmpeg works on files, not streams, so the take makes a round trip
     * through the local disk. Both temporary files are removed whatever
     * happens, including when the conversion throws.
     */
    public function handle(): void
    {
        $disk = Storage::disk('s3');

        if (! $disk->exists($this->greeting->original_path)) {
            return;
        }

        $source = tempnam(sys_get_temp_dir(), 'greeting-');
        $target = $source.'.mp3';

        try {
            file_put_contents($source, $disk->get($this->greeting->original_path));

            Process::timeout(self::TIMEOUT)
                ->run(['ffmpeg', '-y', '-i', $source, '-vn', '-ac', '1', '-b:a', self::BITRATE, $target])
                ->throw();

            $mp3Path = preg_replace('/\.[^.]+$/', '', $this->greeting->original_path).'.mp3';

            $disk->put($mp3Path, file_get_contents($target));

            $this->greeting->update([
                'mp3_path' => $mp3Path,
                'duration_seconds' => $this->durationOf($target),
            ]);
        } finally {
            @unlink($source);
            @unlink($target);
        }
    }

    /**
     * Length of the converted file, in whole seconds, or null when ffprobe
     * cannot read it — a greeting without a duration still plays, so this
     * never fails the conversion.
     */
    private function durationOf(string $path): ?int
    {
        $result = Process::timeout(self::TIMEOUT)->run([
            'ffprobe', '-v', 'error', '-show_entries', 'format=duration', '-of', 'default=nw=1:nk=1', $path,
        ]);

        if (! $result->successful()) {
            return null;
        }

        $seconds = (float) trim($result->output());

        return $seconds > 0 ? (int) round($seconds) : null;
    }

    /**
     * A greeting that never converts stays unplayable on the handset while the
     * studio believes it holds a message. Log it so that state has a cause.
     */
    public function failed(?Throwable $exception): void
    {
        Log::error('Greeting conversion failed', [
            'greeting_id' => $this->greeting->id,
            'party_id' => $this->greeting->party_id,
            'exception' => $exception,
        ]);
    }
}
