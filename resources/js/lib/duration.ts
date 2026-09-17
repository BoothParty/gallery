/**
 * Seconds as the `M:SS` the audio guestbook shows everywhere a length or a
 * playback position appears.
 */
export default function formatDuration(seconds: number): string {
    const total = Math.max(0, Math.round(seconds));

    return `${Math.floor(total / 60)}:${String(total % 60).padStart(2, '0')}`;
}
