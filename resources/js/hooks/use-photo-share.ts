import { useCallback, useRef, useState } from 'react';

import track from '@/lib/analytics';

export type ShareStatus = 'idle' | 'preparing' | 'ready' | 'sharing';

export interface ShareProgress {
    done: number;
    total: number;
}

export interface SharePhoto {
    id: number;
    url: string;
}

export interface PhotoShare {
    supported: boolean;
    status: ShareStatus;
    progress: ShareProgress;
    refreshSupport: () => void;
    share: (photos: SharePhoto[]) => void;
}

/** Requests kept in flight at once, so a mobile link is not saturated. */
const CONCURRENCY = 3;

const NO_PROGRESS: ShareProgress = { done: 0, total: 0 };

const DOWNLOAD_ERROR = 'Le téléchargement des photos a échoué. Réessaie dans un instant.';

/**
 * Whether this browser can share files. The probe file matters: several
 * browsers expose navigator.share without accepting files at all.
 */
export function detectShareSupport(): boolean {
    if (typeof navigator === 'undefined' || typeof navigator.share !== 'function' || typeof navigator.canShare !== 'function') {
        return false;
    }

    try {
        return navigator.canShare({
            files: [new File([new Uint8Array(1)], 'photo.jpg', { type: 'image/jpeg' })],
        });
    } catch {
        return false;
    }
}

/**
 * Native sharing of a picture selection.
 *
 * The files are downloaded first, then handed to the share sheet. A share call
 * needs a transient user activation, which the download may outlive; when the
 * browser refuses for that reason the files stay in memory so the next tap
 * shares them without downloading anything again.
 */
export default function usePhotoShare(onError: (message: string) => void): PhotoShare {
    const [supported, setSupported] = useState<boolean>(() => detectShareSupport());
    const [status, setStatus] = useState<ShareStatus>('idle');
    const [progress, setProgress] = useState<ShareProgress>(NO_PROGRESS);

    const filesRef = useRef<File[]>([]);
    const idsRef = useRef<number[]>([]);

    const refreshSupport = useCallback(() => setSupported(detectShareSupport()), []);

    const forget = useCallback(() => {
        filesRef.current = [];
        idsRef.current = [];
        setProgress(NO_PROGRESS);
    }, []);

    const openShareSheet = useCallback(async () => {
        setStatus('sharing');

        try {
            await navigator.share({ files: filesRef.current });

            track('share', { count: filesRef.current.length });

            forget();
            setStatus('idle');
        } catch (error) {
            if (error instanceof DOMException && error.name === 'AbortError') {
                forget();
                setStatus('idle');

                return;
            }

            setStatus('ready');
        }
    }, [forget]);

    const download = useCallback(async (photos: SharePhoto[]): Promise<File[]> => {
        const files: File[] = new Array(photos.length);
        let next = 0;
        let done = 0;

        const worker = async (): Promise<void> => {
            while (next < photos.length) {
                const index = next++;
                const response = await fetch(photos[index].url);

                if (!response.ok) {
                    throw new Error(`Failed to download picture ${photos[index].id}`);
                }

                const blob = await response.blob();

                files[index] = new File([blob], `photo-${index + 1}.jpg`, { type: 'image/jpeg' });

                done += 1;
                setProgress({ done, total: photos.length });
            }
        };

        await Promise.all(Array.from({ length: Math.min(CONCURRENCY, photos.length) }, worker));

        return files;
    }, []);

    const share = useCallback(
        (photos: SharePhoto[]) => {
            const ids = photos.map((photo) => photo.id);
            const alreadyDownloaded =
                status === 'ready' &&
                filesRef.current.length === ids.length &&
                idsRef.current.every((id, index) => id === ids[index]);

            if (alreadyDownloaded) {
                void openShareSheet();

                return;
            }

            setStatus('preparing');
            setProgress({ done: 0, total: photos.length });

            void download(photos)
                .then((files) => {
                    filesRef.current = files;
                    idsRef.current = ids;

                    return openShareSheet();
                })
                .catch(() => {
                    forget();
                    setStatus('idle');
                    onError(DOWNLOAD_ERROR);
                });
        },
        [download, forget, onError, openShareSheet, status],
    );

    return { supported, status, progress, refreshSupport, share };
}
