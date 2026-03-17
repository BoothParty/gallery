interface PictureCardProps {
    url: string;
    id: number;
}

export default function PictureCard({ url, id }: PictureCardProps) {
    return (
        <a href={url} target="_blank" rel="noreferrer" data-pswp-src={url} data-pswp-width="2400" data-pswp-height="3600">
            <div className="group w-36 overflow-hidden sm:w-60">
                <img
                    src={url}
                    alt={`Photo ${id}`}
                    className="w-36 object-cover transition-all duration-300 group-hover:scale-110 sm:w-60"
                />
            </div>
        </a>
    );
}
