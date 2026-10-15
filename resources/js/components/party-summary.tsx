import { Link } from "@inertiajs/react"

import { cn, formatBytes } from "@/lib/utils"

type PartySummaryProps = {
    title: string
    /** Shown before the picture count, such as the party's address name. */
    detail?: string
    picturesCount: number
    picturesSize: number
    thumbnailUrls: (string | null)[]
    expired: boolean
    /** Turns the picture count into a link to the pictures screen. */
    picturesHref?: string
}

/**
 * First cell of a party or transfer row: a 2 × 2 mosaic of its pictures, its
 * name and its weight.
 */
export function PartySummary({ title, detail, picturesCount, picturesSize, thumbnailUrls, expired, picturesHref }: PartySummaryProps) {
    const pictures = `${picturesCount} ${picturesCount === 1 ? "photo" : "photos"}`

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
                <span className="truncate font-heading text-lg leading-tight">{title}</span>
                <span className="truncate text-xs text-muted-foreground tabular-nums">
                    {detail && `${detail} · `}
                    {picturesHref ? (
                        <Link href={picturesHref} className="underline-offset-3 hover:text-foreground hover:underline">
                            {pictures}
                        </Link>
                    ) : pictures}
                    {picturesSize > 0 && ` · ${formatBytes(picturesSize)}`}
                </span>
            </div>
        </div>
    )
}
