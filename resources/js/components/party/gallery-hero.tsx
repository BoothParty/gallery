export interface HeroPosition {
    x: number;
    y: number;
}

interface GalleryHeroProps {
    title: string;
    subtitle: string | null;
    bannerUrl: string | null;
    /** Percentages, as `object-position` reads them. */
    position: HeroPosition;
    /** 1 to 3, applied on top of the `cover` fit. */
    zoom: number;
    canEdit: boolean;
    onEdit: () => void;
}

export default function GalleryHero({
    title,
    subtitle,
    bannerUrl,
    position,
    zoom,
    canEdit,
    onEdit,
}: GalleryHeroProps) {
    return (
        <div
            data-gallery-hero
            className="relative flex h-[62vh] max-h-[560px] min-h-[340px] items-end overflow-hidden bg-placeholder"
            style={{
                backgroundImage:
                    'repeating-linear-gradient(135deg, color-mix(in srgb, var(--color-ink) 5.5%, transparent) 0 2px, transparent 2px 11px)',
            }}
        >
            {bannerUrl && (
                <img
                    data-gallery-banner
                    src={bannerUrl}
                    alt=""
                    fetchPriority="high"
                    className="absolute inset-0 h-full w-full object-cover"
                    style={{
                        objectPosition: `${position.x}% ${position.y}%`,
                        transform: `scale(${zoom})`,
                    }}
                />
            )}

            <div
                className="absolute inset-0"
                style={{
                    background:
                        'linear-gradient(to top, color-mix(in srgb, var(--color-shade) 62%, transparent) 0%, color-mix(in srgb, var(--color-shade) 18%, transparent) 45%, transparent 75%)',
                }}
            />

            {canEdit && (
                <button
                    data-gallery-edit
                    type="button"
                    onClick={onEdit}
                    className="absolute top-3.5 right-3.5 z-20 flex cursor-pointer items-center gap-2 rounded-full border border-cream/40 bg-shade/40 px-4 py-2.5 text-[13px] whitespace-nowrap text-cream backdrop-blur-[10px] transition-colors hover:border-cream/75 hover:bg-shade/65 sm:top-5 sm:right-6 lg:right-10"
                >
                    <svg
                        width="14"
                        height="14"
                        viewBox="0 0 16 16"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="1.4"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        aria-hidden="true"
                    >
                        <path d="M10.6 2.6l2.8 2.8L6 12.8l-3.2.4.4-3.2 7.4-7.4z" />
                    </svg>
                    Modifier la galerie
                </button>
            )}

            <div className="relative flex w-full flex-col items-center gap-3.5 px-5 pb-7 text-center sm:px-10 lg:px-16 lg:pb-12">
                <h1 className="font-serif text-[clamp(34px,7vw,68px)] leading-none tracking-tight text-cream text-pretty">
                    {title}
                </h1>
                {subtitle && <div className="text-xs tracking-[0.18em] text-cream/80 uppercase">{subtitle}</div>}
            </div>
        </div>
    );
}
