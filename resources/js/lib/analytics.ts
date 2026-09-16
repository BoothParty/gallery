declare global {
    interface Window {
        umami?: {
            track: (event: string, data?: Record<string, unknown>) => void;
        };
    }
}

/**
 * Record a gallery event, when the visitor is being tracked at all.
 *
 * The tracker is only served to guests, and only once Umami is configured, so
 * every call site has to tolerate its absence rather than test for it.
 */
export default function track(event: string, data?: Record<string, unknown>): void {
    window.umami?.track(event, data);
}
