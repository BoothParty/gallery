const TIPS = [
    {
        title: 'Une minute suffit',
        body: 'Les messages les plus écoutés durent entre 30 et 60 secondes.',
    },
    {
        title: 'Dites quoi faire',
        body: "« Laissez-nous un mot après le bip, on l'écoutera au petit-déjeuner. »",
    },
    {
        title: 'À deux voix',
        body: "Alternez vos deux voix, c'est ce qui fait sourire les invités.",
    },
];

interface AudiobookTipsProps {
    onReopenOnboarding: () => void;
}

/**
 * The three things worth knowing before pressing record, kept below the fold
 * so they inform without instructing.
 */
export default function AudiobookTips({ onReopenOnboarding }: AudiobookTipsProps) {
    return (
        <section className="flex flex-col gap-3 pt-1">
            <div className="flex flex-wrap items-baseline justify-between gap-2.5">
                <span className="text-[11px] tracking-[0.16em] text-muted uppercase">Trois repères</span>
                <button
                    type="button"
                    onClick={onReopenOnboarding}
                    data-reopen-onboarding
                    className="cursor-pointer text-[13px] text-subtle transition-colors hover:text-ink"
                >
                    Revoir le déroulement
                </button>
            </div>
            <div className="grid gap-[18px_26px] sm:grid-cols-[repeat(auto-fit,minmax(min(100%,200px),1fr))]">
                {TIPS.map((tip) => (
                    <div key={tip.title} className="flex flex-col gap-1.5 border-t border-line pt-3">
                        <span className="font-serif text-[19px]">{tip.title}</span>
                        <span className="text-[13px] leading-[1.55] text-pretty text-muted">{tip.body}</span>
                    </div>
                ))}
            </div>
        </section>
    );
}
