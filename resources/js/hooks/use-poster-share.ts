import { useCallback, useState } from 'react';

export type ShareStatus = 'idle' | 'preparing' | 'sharing';

export interface ShareProgress {
    done: number;
    total: number;
}

export interface SharePoster {
    id: number;
    name: string;
    url: string;
}

export interface PosterShare {
    status: ShareStatus;
    progress: ShareProgress;
    share: (posters: SharePoster[]) => void;
}

/** Posters fetched at once, so the server never stacks more than two renders. */
const CONCURRENCY = 2;

const NO_PROGRESS: ShareProgress = { done: 0, total: 0 };

const DOWNLOAD_ERROR = 'Le téléchargement des affiches a échoué. Réessaie dans un instant.';

/**
 * Whether this browser can share files. The probe file matters: several
 * browsers expose navigator.share without accepting files at all.
 */
export function detectShareSupport(): boolean {
    if (
        typeof navigator === 'undefined' ||
        typeof navigator.share !== 'function' ||
        typeof navigator.canShare !== 'function'
    ) {
        return false;
    }

    try {
        return navigator.canShare({
            files: [new File([new Uint8Array(1)], 'affiche.png', { type: 'image/png' })],
        });
    } catch {
        return false;
    }
}

/**
 * Native sharing of a batch of posters.
 *
 * The posters are generated on demand by the server, so they are downloaded
 * two at a time before being handed to the share sheet in one call. Support is
 * probed by the caller when the share is asked for, not when the page mounts:
 * the answer is only needed then, and reading it late keeps the button from
 * depending on how early the browser exposes the API.
 */
export default function usePosterShare(onError: (message: string) => void): PosterShare {
    const [status, setStatus] = useState<ShareStatus>('idle');
    const [progress, setProgress] = useState<ShareProgress>(NO_PROGRESS);

    const download = useCallback(async (posters: SharePoster[]): Promise<File[]> => {
        const files: File[] = new Array(posters.length);
        let next = 0;
        let done = 0;

        const worker = async (): Promise<void> => {
            while (next < posters.length) {
                const index = next++;
                const response = await fetch(posters[index].url);

                if (!response.ok) {
                    throw new Error(`Failed to download the poster of party ${posters[index].id}`);
                }

                const blob = await response.blob();

                files[index] = new File([blob], `${posters[index].name}-affiche.png`, {
                    type: 'image/png',
                });

                done += 1;
                setProgress({ done, total: posters.length });
            }
        };

        await Promise.all(Array.from({ length: Math.min(CONCURRENCY, posters.length) }, worker));

        return files;
    }, []);

    const share = useCallback(
        (posters: SharePoster[]) => {
            setStatus('preparing');
            setProgress({ done: 0, total: posters.length });

            void download(posters)
                .then(async (files) => {
                    setStatus('sharing');

                    try {
                        await navigator.share({ files });
                    } catch (error) {
                        // A dismissed share sheet is not a failure.
                        if (!(error instanceof DOMException && error.name === 'AbortError')) {
                            throw error;
                        }
                    }

                    setStatus('idle');
                    setProgress(NO_PROGRESS);
                })
                .catch(() => {
                    setStatus('idle');
                    setProgress(NO_PROGRESS);
                    onError(DOWNLOAD_ERROR);
                });
        },
        [download, onError],
    );

    return { status, progress, share };
}
