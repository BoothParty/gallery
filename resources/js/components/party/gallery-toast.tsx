interface GalleryToastProps {
    message: string | null;
    /** Lifted above the selection bar while it is open. */
    raised: boolean;
}

export default function GalleryToast({ message, raised }: GalleryToastProps) {
    return (
        <div
            data-gallery-toast
            role="status"
            className="pointer-events-none fixed left-1/2 z-40 rounded-full bg-ink px-5 py-2.5 text-[13px] whitespace-nowrap text-cream transition-all duration-250"
            style={{
                bottom: raised ? '96px' : '28px',
                opacity: message === null ? 0 : 1,
                transform: `translateX(-50%) translateY(${message === null ? '12px' : '0'})`,
            }}
        >
            {message ?? ''}
        </div>
    );
}
