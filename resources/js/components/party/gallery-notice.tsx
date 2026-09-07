import type { ReactNode } from 'react';

interface GalleryNoticeProps {
    heading: string;
    children: ReactNode;
}

/**
 * Stands in for the grid when there is nothing to show — an empty or an
 * expired gallery. Keeps the `#gallery` anchor so the lightbox and the
 * infinite scroll always find the same element.
 */
export default function GalleryNotice({ heading, children }: GalleryNoticeProps) {
    return (
        <div
            id="gallery"
            data-gallery-notice
            className="flex flex-col items-center gap-4 px-5 py-14 text-center sm:py-20 lg:py-32"
        >
            <p className="font-serif text-[clamp(24px,3.4vw,34px)] leading-tight">{heading}</p>
            <p className="max-w-[44ch] text-[15px] leading-relaxed text-muted text-pretty">{children}</p>
        </div>
    );
}
