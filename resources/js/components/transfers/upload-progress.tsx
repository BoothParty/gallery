import { Button } from "@/components/ui/button"
import type { UploadQueue } from "@/hooks/use-upload-queue"

const formatBytes = (bytes: number) => `${(bytes / 1024 / 1024).toFixed(0)} MB`

export function UploadProgress({ queue }: { queue: UploadQueue }) {
    const { status, items, counts, failed, error, settled } = queue

    return (
        <div className="space-y-3 text-sm">
            <progress value={settled} max={items.length} className="w-full" />
            <p>
                {settled} / {items.length} — {formatBytes(counts.bytes)} sent, {counts.skipped} already online,{" "}
                {failed.length} failed
            </p>
            {error && <p className="text-destructive">{error}</p>}
            {status === "done" && failed.length > 0 && (
                <p className="text-destructive">
                    Failed: {failed.slice(0, 10).map((item) => item.path).join(", ")}
                    {failed.length > 10 && ` and ${failed.length - 10} more`}
                </p>
            )}
            <div className="flex justify-end gap-2">
                {status === "uploading" && (
                    <Button type="button" variant="outline" onClick={queue.cancel}>
                        Cancel
                    </Button>
                )}
                {status === "done" && failed.length > 0 && (
                    <Button type="button" onClick={() => void queue.retry()}>
                        Retry failed
                    </Button>
                )}
            </div>
        </div>
    )
}
