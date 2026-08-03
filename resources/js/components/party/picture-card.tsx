interface PictureCardProps {
    url: string;
    /** Null while the thumbnail job has not run yet — a placeholder is shown instead. */
    thumbnailUrl: string | null;
    id: number;
    width: number | null;
    height: number | null;
    /** Load eagerly instead of lazily — reserved for the first, above-the-fold rows. */
    priority?: boolean;
}

export default function PictureCard({ url, thumbnailUrl, id, width, height, priority = false }: PictureCardProps) {
    return (
        <a href={url} target="_blank" rel="noreferrer" data-pswp-src={url} data-pswp-width={width ?? undefined} data-pswp-height={height ?? undefined}>
            <div className="group w-36 overflow-hidden sm:w-60">
                {thumbnailUrl ? (
                    <img
                        src={thumbnailUrl}
                        alt={`Photo ${id}`}
                        className="w-36 object-cover transition-all duration-300 group-hover:scale-110 sm:w-60"
                        loading={priority ? 'eager' : 'lazy'}
                        decoding="async"
                        fetchPriority={priority ? 'high' : 'low'}
                    />
                ) : (
                    <div
                        className="flex aspect-square w-36 animate-pulse items-center justify-center bg-gray-700 sm:w-60"
                        role="img"
                        aria-label={`Photo ${id} en cours de préparation`}
                        data-picture-placeholder
                    />
                )}
            </div>
        </a>
    );
}
