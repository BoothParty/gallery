/**
 * Hard-coded until the party gains its own title, date, location and cover
 * columns. Wiring real data later means passing these three props from the
 * controller instead of falling back to the defaults.
 */
const DEFAULT_TITLE = 'Camille & Antoine';
const DEFAULT_SUBTITLE = 'Samedi 12 septembre 2026 · Domaine des Ormes';

interface GalleryHeroProps {
    title?: string;
    subtitle?: string;
}

export default function GalleryHero({ title = DEFAULT_TITLE, subtitle = DEFAULT_SUBTITLE }: GalleryHeroProps) {
    return (
        <div
            data-gallery-hero
            className="relative flex h-[62vh] max-h-[560px] min-h-[340px] items-end overflow-hidden bg-placeholder"
            style={{
                backgroundImage:
                    'repeating-linear-gradient(135deg, rgba(23,21,18,.055) 0 2px, rgba(23,21,18,0) 2px 11px)',
            }}
        >
            <span className="absolute top-4 left-5 font-mono text-[11px] tracking-[0.14em] text-ink/40 uppercase sm:top-7 sm:left-16">
                photo de couverture
            </span>

            <div
                className="absolute inset-0"
                style={{
                    background:
                        'linear-gradient(to top, rgba(20,18,15,.62) 0%, rgba(20,18,15,.18) 45%, rgba(20,18,15,0) 75%)',
                }}
            />

            <div className="relative flex w-full flex-col items-center gap-3.5 px-5 pb-7 text-center sm:px-10 lg:px-16 lg:pb-12">
                <h1 className="font-serif text-[clamp(34px,7vw,68px)] leading-none tracking-tight text-cream text-pretty">
                    {title}
                </h1>
                <div className="text-xs tracking-[0.18em] text-cream/80 uppercase">{subtitle}</div>
            </div>
        </div>
    );
}
