interface NewPicturesPillProps {
    /** Pictures gained since the last refresh; zero or negative when it shrank. */
    delta: number;
    onClick: () => void;
}

/**
 * Offered instead of an automatic refresh when the visitor is busy — scrolled
 * down, selecting, or looking at a picture full screen. Tapping it takes them
 * back to the top, where the new pictures are.
 */
export default function NewPicturesPill({ delta, onClick }: NewPicturesPillProps) {
    const label = delta === 1 ? '1 nouvelle photo' : delta > 1 ? `${delta} nouvelles photos` : 'Galerie mise à jour';

    return (
        <button
            type="button"
            data-gallery-pill
            onClick={onClick}
            className="fixed top-16 left-1/2 z-30 flex -translate-x-1/2 cursor-pointer items-center gap-2 rounded-full bg-ink px-5 py-2.5 text-[13px] whitespace-nowrap text-cream shadow-[0_10px_30px_rgba(20,18,15,0.3)]"
        >
            <svg
                width="12"
                height="12"
                viewBox="0 0 12 12"
                fill="none"
                stroke="currentColor"
                strokeWidth="1.7"
                strokeLinecap="round"
                strokeLinejoin="round"
                aria-hidden="true"
            >
                <path d="M6 10V2m0 0L2.6 5.4M6 2l3.4 3.4" />
            </svg>
            {label}
        </button>
    );
}
