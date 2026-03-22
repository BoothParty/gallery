interface PictureCardProps {
    url: string;
    thumbnailUrl: string;
    id: number;
    width: number | null;
    height: number | null;
}

export default function PictureCard({ url, thumbnailUrl, id, width, height }: PictureCardProps) {
    return (
        <a href={url} target="_blank" rel="noreferrer" data-pswp-src={url} data-pswp-width={width ?? undefined} data-pswp-height={height ?? undefined}>
            <div className="group w-36 overflow-hidden sm:w-60">
                <img
                    src={thumbnailUrl}
                    alt={`Photo ${id}`}
                    className="w-36 object-cover transition-all duration-300 group-hover:scale-110 sm:w-60"
                />
            </div>
        </a>
    );
}
