interface PhotoTileProps {
    id: number;
    url: string;
    /** Null while the thumbnail job has not run yet — a placeholder is shown instead. */
    thumbnailUrl: string | null;
    width: number | null;
    height: number | null;
    /** Load eagerly instead of lazily — reserved for the first, above-the-fold rows. */
    priority?: boolean;
    selectMode?: boolean;
    selected?: boolean;
    /** Taken off the guests' gallery. Only a host is ever shown such a tile. */
    hidden?: boolean;
    /** Show the host's masking control. */
    canEdit?: boolean;
    /** A reprint is queued for the booth. */
    printPending?: boolean;
}

function EyeOffIcon({ size }: { size: number }) {
    return (
        <svg width={size} height={size} viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <path d="M2.5 2.5l11 11" />
            <path d="M6.3 3.4A6.6 6.6 0 0 1 8 3.2c3.4 0 5.7 2.6 6.4 4.8-.3.9-.9 1.9-1.8 2.8M4.2 4.6C2.9 5.6 2 7 1.6 8c.7 2.2 3 4.8 6.4 4.8 1 0 1.9-.2 2.7-.6" />
            <path d="M6.5 6.6a2.1 2.1 0 0 0 2.9 2.9" />
        </svg>
    );
}

function EyeIcon({ size }: { size: number }) {
    return (
        <svg width={size} height={size} viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <path d="M1.6 8C2.3 5.8 4.6 3.2 8 3.2s5.7 2.6 6.4 4.8c-.7 2.2-3 4.8-6.4 4.8S2.3 10.2 1.6 8z" />
            <circle cx="8" cy="8" r="2.1" />
        </svg>
    );
}

export function PrinterIcon({ size }: { size: number }) {
    return (
        <svg width={size} height={size} viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.4" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
            <path d="M4.5 5.5V2.5h7v3" />
            <rect x="2" y="5.5" width="12" height="5.5" rx="1" />
            <path d="M4.5 9.5h7v4h-7z" />
        </svg>
    );
}

export default function PhotoTile({
    id,
    url,
    thumbnailUrl,
    width,
    height,
    priority = false,
    selectMode = false,
    selected = false,
    hidden = false,
    canEdit = false,
    printPending = false,
}: PhotoTileProps) {
    const hideLabel = hidden ? 'Remettre en ligne' : 'Masquer pour les invités';

    return (
        <a
            href={url}
            data-id={id}
            data-pswp-src={url}
            data-pswp-width={width ?? undefined}
            data-pswp-height={height ?? undefined}
            className="group relative block aspect-square cursor-pointer overflow-hidden rounded-[2px] bg-placeholder"
        >
            {thumbnailUrl ? (
                <img
                    src={thumbnailUrl}
                    alt={`Photo ${id}`}
                    className={`block h-full w-full object-cover transition-all duration-500 group-hover:scale-[1.035] ${
                        hidden ? '[filter:grayscale(1)_brightness(0.34)_contrast(0.9)]' : ''
                    }`}
                    loading={priority ? 'eager' : 'lazy'}
                    decoding="async"
                    fetchPriority={priority ? 'high' : 'low'}
                />
            ) : (
                <div
                    className="h-full w-full animate-shimmer bg-placeholder"
                    role="img"
                    aria-label={`Photo ${id} en cours de préparation`}
                    data-picture-placeholder
                />
            )}

            {hidden && (
                <span
                    data-picture-hidden
                    className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center gap-[7px] text-[11px] tracking-[0.18em] text-cream uppercase shadow-[inset_0_0_0_1px_rgba(253,252,250,0.28)]"
                >
                    <EyeOffIcon size={22} />
                    Masquée
                </span>
            )}

            {canEdit && !selectMode && (
                <button
                    type="button"
                    data-hide-button={id}
                    data-hidden={hidden}
                    title={hideLabel}
                    aria-label={hideLabel}
                    className={`absolute top-2 left-2 flex h-[26px] w-[26px] items-center justify-center rounded-full border p-0 text-cream transition-all duration-150 hover:bg-ink/60 hover:opacity-100 ${
                        hidden ? 'border-ink bg-ink opacity-100' : 'border-cream/70 bg-ink/35 opacity-70'
                    }`}
                >
                    {hidden ? <EyeOffIcon size={13} /> : <EyeIcon size={13} />}
                </button>
            )}

            {printPending &&
                (canEdit && !selectMode ? (
                    <button
                        type="button"
                        data-print-pending
                        data-cancel-print={id}
                        title="Annuler l'impression"
                        aria-label="Annuler l'impression"
                        className="absolute top-2 left-10 flex h-[26px] w-[26px] items-center justify-center rounded-full border border-dashed border-cream/85 bg-ink/35 p-0 text-cream transition-all duration-150 hover:bg-ink/60"
                    >
                        <PrinterIcon size={13} />
                    </button>
                ) : (
                    <span
                        data-print-pending
                        title="Impression en attente"
                        aria-label="Impression en attente"
                        role="img"
                        className="pointer-events-none absolute top-2 left-2 flex h-[26px] w-[26px] items-center justify-center rounded-full border border-dashed border-cream/85 bg-ink/35 text-cream"
                    >
                        <PrinterIcon size={13} />
                    </span>
                ))}

            <span
                className={`pointer-events-none absolute inset-0 transition-all duration-200 ${
                    selected ? 'bg-ink/30 shadow-[inset_0_0_0_2px_var(--color-ink)]' : ''
                }`}
            />

            <span
                className={`pointer-events-none absolute top-2 right-2 h-[22px] w-[22px] items-center justify-center rounded-full border transition-colors duration-150 ${
                    selectMode ? 'flex' : 'hidden'
                } ${selected ? 'border-ink bg-ink' : 'border-cream/75 bg-ink/20'}`}
            >
                {selected && (
                    <svg width="12" height="12" viewBox="0 0 12 12" fill="none" stroke="var(--color-cream)" strokeWidth="1.7" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                        <path d="M2 6.4 4.6 9 10 3.2" />
                    </svg>
                )}
            </span>
        </a>
    );
}
