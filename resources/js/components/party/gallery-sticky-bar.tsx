interface GalleryStickyBarProps {
    count: number;
    selectMode: boolean;
    allSelected: boolean;
    onEnterSelect: () => void;
    onExitSelect: () => void;
    onSelectAll: () => void;
}

export default function GalleryStickyBar({
    count,
    selectMode,
    allSelected,
    onEnterSelect,
    onExitSelect,
    onSelectAll,
}: GalleryStickyBarProps) {
    return (
        <div className="sticky top-0 z-20 border-b border-line bg-surface/90 backdrop-blur-md">
            <div className="mx-auto flex max-w-[1440px] items-center justify-between gap-4 px-4 py-3.5 sm:px-8 lg:px-12">
                <div className="flex min-w-0 items-baseline gap-2">
                    <span data-gallery-count className="font-serif text-xl">
                        {count}
                    </span>
                    <span className="text-xs tracking-[0.14em] text-muted uppercase">photos</span>
                </div>

                <div className="flex items-center gap-2">
                    {selectMode ? (
                        <>
                            <button
                                type="button"
                                onClick={onSelectAll}
                                className="rounded-full border border-ink/20 px-4 py-2 text-[13px] whitespace-nowrap transition-colors hover:border-ink"
                            >
                                {allSelected ? 'Tout désélectionner' : 'Tout sélectionner'}
                            </button>
                            <button
                                type="button"
                                onClick={onExitSelect}
                                className="rounded-full border border-transparent px-4 py-2 text-[13px] whitespace-nowrap text-muted transition-colors hover:text-ink"
                            >
                                Annuler
                            </button>
                        </>
                    ) : (
                        <button
                            type="button"
                            onClick={onEnterSelect}
                            className="rounded-full border border-ink/20 px-4 py-2 text-[13px] tracking-wide whitespace-nowrap transition-colors hover:border-ink hover:bg-ink/5"
                        >
                            Sélectionner
                        </button>
                    )}
                </div>
            </div>
        </div>
    );
}
