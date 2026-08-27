import { InfiniteScroll } from '@inertiajs/react';
import { useEffect, useRef } from 'react';

import PhotoTile from '@/components/party/photo-tile';
import type { Picture } from '@/pages/party/gallery';

/** Roughly the first row on a desktop viewport — loaded eagerly, the rest lazily. */
const EAGER_PICTURES_COUNT = 6;

const GRID_CLASSES =
    'grid gap-1.5 sm:gap-2 lg:gap-3 [grid-template-columns:repeat(auto-fill,minmax(min(46%,210px),1fr))]';

interface GalleryGridProps {
    pictures: Picture[];
    selectMode: boolean;
    isSelected: (id: number) => boolean;
    onSelect: (id: number, anchor: HTMLElement) => void;
}

function Skeletons() {
    return (
        <div data-gallery-skeletons className={`${GRID_CLASSES} mt-1.5 sm:mt-2 lg:mt-3`}>
            {[0, 150, 300, 450].map((delay) => (
                <div
                    key={delay}
                    className="aspect-square animate-shimmer rounded-[2px] bg-placeholder"
                    style={{ animationDelay: `${delay}ms` }}
                />
            ))}
        </div>
    );
}

export default function GalleryGrid({ pictures, selectMode, isSelected, onSelect }: GalleryGridProps) {
    const gridRef = useRef<HTMLDivElement>(null);
    const selectModeRef = useRef(selectMode);
    selectModeRef.current = selectMode;

    /**
     * PhotoSwipe listens on the same anchors. Capturing the click one phase
     * earlier lets select mode swallow it before the lightbox ever sees it.
     */
    useEffect(() => {
        const grid = gridRef.current;

        if (grid === null) {
            return;
        }

        const onClickCapture = (event: MouseEvent) => {
            if (!selectModeRef.current) {
                return;
            }

            event.preventDefault();
            event.stopImmediatePropagation();

            const anchor = (event.target as HTMLElement).closest<HTMLElement>('a[data-id]');

            if (anchor === null) {
                return;
            }

            onSelect(Number(anchor.dataset.id), anchor);
        };

        grid.addEventListener('click', onClickCapture, true);

        return () => grid.removeEventListener('click', onClickCapture, true);
    }, [onSelect]);

    return (
        <InfiniteScroll
            data="pictures"
            onlyNext
            preserveUrl
            itemsElement="#gallery"
            next={({ hasNext, loading }) => {
                if (loading) {
                    return <Skeletons />;
                }

                if (hasNext) {
                    return null;
                }

                return (
                    <div className="pt-14 pb-2 text-center text-xs tracking-[0.16em] text-subtle uppercase">
                        Fin de la galerie
                    </div>
                );
            }}
        >
            <div id="gallery" ref={gridRef} className={`pswp-gallery ${GRID_CLASSES}`}>
                {pictures.map((picture, index) => (
                    <PhotoTile
                        key={picture.id}
                        id={picture.id}
                        url={picture.url}
                        thumbnailUrl={picture.thumbnail_url}
                        width={picture.width}
                        height={picture.height}
                        priority={index < EAGER_PICTURES_COUNT}
                        selectMode={selectMode}
                        selected={isSelected(picture.id)}
                    />
                ))}
            </div>
        </InfiniteScroll>
    );
}
