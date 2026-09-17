<?php

namespace App\Services;

use Illuminate\Support\Facades\Process;

class AudioDuration
{
    /**
     * Seconds ffprobe is given before a file is considered unreadable.
     */
    private const TIMEOUT = 30;

    /**
     * Length of a local audio file, in whole seconds.
     *
     * Returns zero rather than throwing when ffprobe cannot read the file: a
     * message whose length is unknown still plays, and refusing a whole upload
     * over one unreadable header would cost the studio far more.
     */
    public function forFile(string $path): int
    {
        $result = Process::timeout(self::TIMEOUT)->run([
            'ffprobe', '-v', 'error', '-show_entries', 'format=duration', '-of', 'default=nw=1:nk=1', $path,
        ]);

        if (! $result->successful()) {
            return 0;
        }

        $seconds = (float) trim($result->output());

        return $seconds > 0 ? (int) round($seconds) : 0;
    }
}
