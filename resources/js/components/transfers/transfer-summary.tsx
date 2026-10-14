import { cn, formatBytes } from "@/lib/utils"

type TransferSummaryProps = {
    name: string | null
    picturesCount: number
    picturesSize: number
    thumbnailUrls: string[]
    expired: boolean
}

/** First cell of a transfer row: a 2 × 2 mosaic of its pictures, its name and its weight. */
export function TransferSummary({ name, picturesCount, picturesSize, thumbnailUrls, expired }: TransferSummaryProps) {
    return (
        <div className="flex max-w-90 min-w-0 items-center gap-3.25">
            <div className="grid size-10 shrink-0 grid-cols-2 gap-0.5 overflow-hidden rounded-[5px]">
                {Array.from({ length: 4 }, (_, index) => (
                    <span
                        key={index}
                        className={cn("bg-muted bg-cover bg-center", expired && "opacity-60 grayscale")}
                        style={thumbnailUrls[index] ? { backgroundImage: `url(${thumbnailUrls[index]})` } : undefined}
                    />
                ))}
            </div>
            <div className="flex min-w-0 flex-col gap-0.75">
                <span className="truncate font-heading text-lg leading-tight">{name}</span>
                <span className="text-xs text-muted-foreground tabular-nums">
                    {picturesCount} {picturesCount === 1 ? "photo" : "photos"}
                    {picturesSize > 0 && ` · ${formatBytes(picturesSize)}`}
                </span>
            </div>
        </div>
    )
}
