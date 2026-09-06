import type { PointerEvent as ReactPointerEvent, RefObject, SyntheticEvent } from 'react';

import type { CoverCrop } from '@/hooks/use-cover-crop';

interface CoverCropperProps {
    src: string | null;
    crop: CoverCrop;
    frameRef: RefObject<HTMLDivElement | null>;
    onImageLoad: (event: SyntheticEvent<HTMLImageElement>) => void;
    onPointerDown: (event: ReactPointerEvent<HTMLDivElement>) => void;
    onZoomChange: (zoom: number) => void;
    onReset: () => void;
    onPick: (file: File) => void;
    onRemove: () => void;
}

/**
 * The 16:7 frame is indicative, not exact: the hero is 62vh tall and its ratio
 * follows the viewport. The "Zone visible" label says as much.
 */
export default function CoverCropper({
    src,
    crop,
    frameRef,
    onImageLoad,
    onPointerDown,
    onZoomChange,
    onReset,
    onPick,
    onRemove,
}: CoverCropperProps) {
    return (
        <div className="flex flex-col gap-3">
            <div className="flex flex-wrap items-baseline justify-between gap-3">
                <span className="text-[11px] tracking-[0.16em] text-muted uppercase">Photo de couverture</span>
                <span className="text-xs text-subtle">Glissez l'image pour cadrer</span>
            </div>

            <div
                ref={frameRef}
                data-cover-frame
                onPointerDown={onPointerDown}
                className="relative aspect-[16/7] touch-none overflow-hidden rounded-[3px] bg-placeholder select-none"
                style={{ cursor: src === null ? 'default' : 'grab' }}
            >
                {src !== null && (
                    <img
                        src={src}
                        alt=""
                        onLoad={onImageLoad}
                        draggable={false}
                        className="pointer-events-none absolute inset-0 h-full w-full object-cover"
                        style={{
                            objectPosition: `${crop.x}% ${crop.y}%`,
                            transform: `scale(${crop.zoom})`,
                        }}
                    />
                )}

                <div className="pointer-events-none absolute inset-0 shadow-[inset_0_0_0_1px_rgba(253,252,250,0.5)]">
                    <div className="absolute inset-0 mx-[33.33%] border-x border-cream/20" />
                    <div className="absolute inset-0 my-[33.33%] border-y border-cream/20" />
                </div>

                {src === null && (
                    <div className="pointer-events-none absolute inset-0 flex items-center justify-center text-xs tracking-[0.14em] text-ink/40 uppercase">
                        Aucune photo de couverture
                    </div>
                )}

                <div className="pointer-events-none absolute bottom-2.5 left-2.5 rounded-full bg-shade/50 px-2.5 py-1 text-[10px] tracking-[0.1em] text-cream uppercase">
                    Zone visible
                </div>
            </div>

            <div className="flex items-center gap-3.5">
                <span className="text-xs whitespace-nowrap text-muted">Zoom</span>
                <input
                    type="range"
                    min={1}
                    max={3}
                    step={0.02}
                    value={crop.zoom}
                    disabled={src === null}
                    onChange={(event) => onZoomChange(Number(event.target.value))}
                    className="flex-1 accent-ink"
                    aria-label="Zoom de la couverture"
                />
                <button
                    type="button"
                    onClick={onReset}
                    disabled={src === null}
                    className="cursor-pointer rounded-full border border-ink/15 px-3 py-1.5 text-xs whitespace-nowrap text-ink transition-colors hover:border-ink disabled:cursor-default disabled:opacity-40"
                >
                    Recadrer
                </button>
            </div>

            <div className="flex flex-wrap gap-2 pt-0.5">
                <label className="cursor-pointer rounded-full border border-ink/20 px-4 py-2.5 text-[13px] whitespace-nowrap transition-colors hover:border-ink">
                    {src === null ? 'Importer une photo' : 'Remplacer la photo'}
                    <input
                        type="file"
                        accept="image/*"
                        className="sr-only"
                        onChange={(event) => {
                            const file = event.target.files?.[0];

                            if (file) {
                                onPick(file);
                            }

                            event.target.value = '';
                        }}
                    />
                </label>

                {src !== null && (
                    <button
                        type="button"
                        data-cover-remove
                        onClick={onRemove}
                        className="cursor-pointer rounded-full border border-ink/20 px-4 py-2.5 text-[13px] whitespace-nowrap text-muted transition-colors hover:border-ink hover:text-ink"
                    >
                        Retirer la photo
                    </button>
                )}
            </div>
        </div>
    );
}
