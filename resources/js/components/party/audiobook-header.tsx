interface AudiobookHeaderProps {
    title: string;
    eventLine: string | null;
}

/**
 * The page's masthead: a small private-space eyebrow, the event title in the
 * serif face, and the date when the party carries one.
 */
export default function AudiobookHeader({ title, eventLine }: AudiobookHeaderProps) {
    return (
        <header className="flex flex-col items-center gap-4 border-b border-line px-5 py-13 text-center sm:px-8 lg:px-16 lg:py-26">
            <p className="font-mono text-[10px] tracking-[0.22em] text-subtle uppercase">
                Livre d'or audio · espace privé
            </p>
            <h1 className="max-w-[22ch] font-serif text-[clamp(34px,6.5vw,72px)] leading-[0.98] tracking-[-0.015em] text-pretty">
                {title}
            </h1>
            <div className="h-px w-[34px] bg-ink/20" />
            {eventLine && <p className="text-[13px] tracking-[0.12em] text-muted uppercase">{eventLine}</p>}
        </header>
    );
}
