import { useEffect, useMemo } from "react"

import { Button } from "@/components/ui/button"
import type { UploadQueue } from "@/hooks/use-upload-queue"
import { cn, formatBytes } from "@/lib/utils"

const THUMBNAILS = 8

/** The folder going out: its first pictures light up as the queue advances. */
export function UploadProgress({ queue }: { queue: UploadQueue }) {
    const { status, items, counts, failed, error, settled } = queue
    const total = items.length
    const totalBytes = useMemo(() => items.reduce((sum, item) => sum + item.file.size, 0), [items])
    const previews = useMemo(() => items.slice(0, THUMBNAILS).map((item) => URL.createObjectURL(item.file)), [items])
    const finished = status !== "uploading"
    const allSent = finished && failed.length === 0 && settled === total
    const ratio = total ? settled / total : 0

    useEffect(() => () => previews.forEach((url) => URL.revokeObjectURL(url)), [previews])

    return (
        <div className="flex flex-col gap-3 rounded-md border bg-background p-3.5 text-[13px]">
            <div className="grid grid-cols-8 gap-1.25">
                {Array.from({ length: THUMBNAILS }, (_, index) => (
                    <span
                        key={index}
                        className={cn(
                            "aspect-square rounded-[3px] bg-muted bg-cover bg-center transition-opacity duration-300",
                            (index + 1) / THUMBNAILS > ratio + 0.001 && "opacity-35",
                        )}
                        style={previews[index] ? { backgroundImage: `url(${previews[index]})` } : undefined}
                    />
                ))}
            </div>

            <div className="flex items-baseline justify-between gap-3">
                <span className="flex items-center gap-2" data-upload-status={status}>
                    <span className={cn("size-1.75 rounded-full", allSent ? "bg-success" : "bg-foreground/70")} />
                    {allSent
                        ? `${total} photos sent`
                        : finished ? `${counts.created + counts.skipped} / ${total} photos sent` : `Sending ${settled} / ${total} photos`}
                </span>
                <span className="whitespace-nowrap text-muted-foreground tabular-nums">
                    {formatBytes(counts.bytes)} of {formatBytes(totalBytes)}
                </span>
            </div>

            <div className="h-1 overflow-hidden rounded-full bg-foreground/10">
                <div
                    className={cn("h-full transition-[width,background-color] duration-150", allSent ? "bg-success" : "bg-foreground")}
                    style={{ width: `${Math.round(ratio * 100)}%` }}
                />
            </div>

            {(counts.skipped > 0 || failed.length > 0) && (
                <p className="text-xs text-muted-foreground">
                    {counts.skipped} already online · {failed.length} failed
                </p>
            )}
            {error && <p className="text-destructive">{error}</p>}
            {finished && failed.length > 0 && (
                <p className="text-destructive">
                    Failed: {failed.slice(0, 10).map((item) => item.path).join(", ")}
                    {failed.length > 10 && ` and ${failed.length - 10} more`}
                </p>
            )}

            {(status === "uploading" || (status === "done" && failed.length > 0)) && (
                <div className="flex justify-end gap-2">
                    {status === "uploading" && (
                        <Button type="button" size="sm" variant="outline" onClick={queue.cancel}>
                            Cancel
                        </Button>
                    )}
                    {status === "done" && failed.length > 0 && (
                        <Button type="button" size="sm" onClick={() => void queue.retry()}>
                            Retry failed
                        </Button>
                    )}
                </div>
            )}
        </div>
    )
}
