import { useCallback, useEffect, useRef, useState } from 'react';

import type { PicturesState, RefreshConfig } from '@/pages/party/gallery';

/** Ceiling on the back-off after repeated network failures, in milliseconds. */
const MAX_BACKOFF = 60_000;

interface GalleryChangesOptions {
    /** False on an expired gallery, which never moves again. */
    enabled: boolean;
    /** The state the page was rendered from. */
    initial: PicturesState;
    config: RefreshConfig;
}

export interface GalleryChanges {
    /** The gallery moved since the last acknowledged state. */
    changed: boolean;
    /** Pictures gained since then; zero or negative when it shrank instead. */
    delta: number;
    /** Take the latest polled state as the new reference. */
    acknowledge: () => void;
}

/**
 * Poll the party's state endpoint and report whether the gallery moved.
 *
 * The hook owns the cadence and nothing else: it never reloads anything, so
 * the page stays free to decide between refreshing silently and asking first.
 * It slows down as the party quiets, sleeps while the tab is in the
 * background, and polls once immediately on the way back — someone putting
 * their phone down and picking it up again is the common case.
 */
export default function useGalleryChanges(
    partyName: string,
    { enabled, initial, config }: GalleryChangesOptions,
): GalleryChanges {
    const [baseline, setBaseline] = useState(initial);
    const [latest, setLatest] = useState(initial);

    const latestRef = useRef(latest);
    latestRef.current = latest;

    const timer = useRef<ReturnType<typeof setTimeout>>(undefined);
    const failures = useRef(0);

    /*
     * A state handed over by the server replaces both ends of the comparison:
     * the page was just rendered from it, so it cannot be a change waiting to
     * be announced. This is what keeps the host's own masking from raising the
     * "new pictures" pill on their own screen.
     */
    useEffect(() => {
        setBaseline(initial);
        setLatest(initial);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [initial.count, initial.last_change]);

    const { hot, warm, cold, warm_after: warmAfter, cold_after: coldAfter } = config;

    /** Seconds to wait before the next poll, from how long ago the gallery moved. */
    const interval = useCallback(
        (state: PicturesState): number => {
            if (state.count === 0 || state.last_change === null) {
                return hot;
            }

            const age = (Date.now() - new Date(state.last_change).getTime()) / 1000;

            if (age < warmAfter) {
                return hot;
            }

            return age < coldAfter ? warm : cold;
        },
        [cold, coldAfter, hot, warm, warmAfter],
    );

    useEffect(() => {
        if (!enabled) {
            return;
        }

        let cancelled = false;

        const schedule = (delay: number) => {
            timer.current = setTimeout(() => void poll(), delay);
        };

        const poll = async () => {
            if (cancelled || document.hidden) {
                return;
            }

            try {
                const response = await fetch(`/api/parties/${encodeURIComponent(partyName)}/state`, {
                    headers: { Accept: 'application/json' },
                });

                if (!response.ok) {
                    throw new Error(String(response.status));
                }

                const state = (await response.json()) as PicturesState;

                if (cancelled) {
                    return;
                }

                failures.current = 0;
                setLatest(state);
                schedule(interval(state) * 1000);
            } catch {
                /*
                 * A guest has nothing to do with a network error on a
                 * background probe, so it stays silent and only backs off.
                 */
                if (cancelled) {
                    return;
                }

                failures.current += 1;
                schedule(Math.min(interval(latestRef.current) * 1000 * 2 ** failures.current, MAX_BACKOFF));
            }
        };

        const onVisibilityChange = () => {
            clearTimeout(timer.current);

            if (!document.hidden) {
                void poll();
            }
        };

        document.addEventListener('visibilitychange', onVisibilityChange);
        schedule(interval(latestRef.current) * 1000);

        return () => {
            cancelled = true;
            clearTimeout(timer.current);
            document.removeEventListener('visibilitychange', onVisibilityChange);
        };
    }, [enabled, interval, partyName]);

    const acknowledge = useCallback(() => setBaseline(latestRef.current), []);

    return {
        changed: latest.count !== baseline.count || latest.last_change !== baseline.last_change,
        delta: latest.count - baseline.count,
        acknowledge,
    };
}
