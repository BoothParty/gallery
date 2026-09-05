import { useCallback, useRef, useState, type PointerEvent as ReactPointerEvent, type SyntheticEvent } from 'react';

export interface CoverCrop {
    /** Horizontal `object-position`, in percent. */
    x: number;
    /** Vertical `object-position`, in percent. */
    y: number;
    /** Applied on top of the `cover` fit, 1 to 3. */
    zoom: number;
}

export const DEFAULT_CROP: CoverCrop = { x: 50, y: 50, zoom: 1 };

interface NaturalSize {
    width: number;
    height: number;
}

const clamp = (value: number, min: number, max: number) => Math.min(max, Math.max(min, value));

/**
 * Drive the cover framing from a pointer drag over a fixed frame.
 *
 * The image is laid out `cover`, then scaled by the zoom. The overflow in each
 * axis is what the drag actually pans across, so a drag of `dx` pixels is worth
 * `-dx / overflow * 100` percentage points — which makes the image follow the
 * pointer exactly, and makes the 0–100 clamp land precisely on the edges of the
 * picture. An axis with no overflow cannot pan and stays centred.
 */
export default function useCoverCrop(initial: CoverCrop) {
    const [crop, setCrop] = useState<CoverCrop>(initial);
    const frameRef = useRef<HTMLDivElement | null>(null);
    const naturalRef = useRef<NaturalSize | null>(null);

    const overflow = useCallback((zoom: number) => {
        const frame = frameRef.current;
        const natural = naturalRef.current;

        if (!frame || !natural || natural.width === 0 || natural.height === 0) {
            return { x: 0, y: 0 };
        }

        const { width, height } = frame.getBoundingClientRect();
        const cover = Math.max(width / natural.width, height / natural.height);
        const renderedWidth = natural.width * cover * zoom;
        const renderedHeight = natural.height * cover * zoom;

        return {
            x: Math.max(0, renderedWidth - width),
            y: Math.max(0, renderedHeight - height),
        };
    }, []);

    const onImageLoad = useCallback((event: SyntheticEvent<HTMLImageElement>) => {
        naturalRef.current = {
            width: event.currentTarget.naturalWidth,
            height: event.currentTarget.naturalHeight,
        };
    }, []);

    const onPointerDown = useCallback(
        (event: ReactPointerEvent<HTMLDivElement>) => {
            if (naturalRef.current === null) {
                return;
            }

            const element = event.currentTarget;
            const startX = event.clientX;
            const startY = event.clientY;
            const start = crop;
            const span = overflow(crop.zoom);

            if (span.x === 0 && span.y === 0) {
                return;
            }

            element.setPointerCapture(event.pointerId);

            const move = (moveEvent: PointerEvent) => {
                const dx = moveEvent.clientX - startX;
                const dy = moveEvent.clientY - startY;

                setCrop({
                    ...start,
                    x: span.x === 0 ? 50 : clamp(start.x - (dx / span.x) * 100, 0, 100),
                    y: span.y === 0 ? 50 : clamp(start.y - (dy / span.y) * 100, 0, 100),
                });
            };

            const up = () => {
                element.releasePointerCapture(event.pointerId);
                element.removeEventListener('pointermove', move);
                element.removeEventListener('pointerup', up);
                element.removeEventListener('pointercancel', up);
            };

            element.addEventListener('pointermove', move);
            element.addEventListener('pointerup', up);
            element.addEventListener('pointercancel', up);
        },
        [crop, overflow],
    );

    const setZoom = useCallback((zoom: number) => {
        setCrop((current) => ({ ...current, zoom }));
    }, []);

    const reset = useCallback(() => {
        setCrop(DEFAULT_CROP);
    }, []);

    return { crop, setCrop, setZoom, reset, frameRef, onImageLoad, onPointerDown };
}
