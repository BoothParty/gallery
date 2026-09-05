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
}: PhotoTileProps) {
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
                    className="block h-full w-full object-cover transition-transform duration-500 group-hover:scale-[1.035]"
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
