import { DragEvent, useEffect, useRef, useState } from "react"

import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import { Label } from "@/components/ui/label"
import {
    analyseFolder,
    collectFromDataTransfer,
    collectFromInput,
    type DroppedFile,
    type FolderAnalysis,
} from "@/lib/folder-drop"
import { runUploadQueue, SessionExpiredError } from "@/lib/upload-queue"

type FolderUploadProps = {
    /** Endpoint the pictures go to, creating the transfer first when needed. Resolved once. */
    resolveUploadUrl: () => Promise<string>
    /** Holds the send button back while the surrounding form is incomplete. */
    disabled?: boolean
    onBusyChange: (busy: boolean) => void
    onFinished: () => void
}

type Status = "idle" | "review" | "uploading" | "done" | "expired"

const formatBytes = (bytes: number) => `${(bytes / 1024 / 1024).toFixed(0)} MB`

export function FolderUpload({ resolveUploadUrl, disabled = false, onBusyChange, onFinished }: FolderUploadProps) {
    const [status, setStatus] = useState<Status>("idle")
    const [analysis, setAnalysis] = useState<FolderAnalysis | null>(null)
    const [excluded, setExcluded] = useState<Set<string>>(new Set())
    const [error, setError] = useState<string | null>(null)
    const [total, setTotal] = useState(0)
    const [counts, setCounts] = useState({ created: 0, skipped: 0, bytes: 0 })
    const [failed, setFailed] = useState<DroppedFile[]>([])
    const abortRef = useRef<AbortController | null>(null)
    const uploadUrlRef = useRef<string | null>(null)

    useEffect(() => onBusyChange(status === "uploading"), [status, onBusyChange])

    // Closing the dialog unmounts the component: stop the requests with it.
    useEffect(() => () => abortRef.current?.abort(), [])

    useEffect(() => {
        if (status !== "uploading") {
            return
        }

        const warn = (event: BeforeUnloadEvent) => event.preventDefault()
        window.addEventListener("beforeunload", warn)

        let lock: WakeLockSentinel | null = null
        let released = false
        navigator.wakeLock?.request("screen")
            .then((sentinel) => {
                lock = sentinel
                if (released) {
                    void sentinel.release()
                }
            })
            .catch(() => {})

        return () => {
            released = true
            window.removeEventListener("beforeunload", warn)
            void lock?.release()
        }
    }, [status])

    const load = (files: DroppedFile[]) => {
        setError(null)
        setAnalysis(analyseFolder(files))
        setExcluded(new Set())
        setStatus("review")
    }

    const onDrop = (event: DragEvent<HTMLDivElement>) => {
        event.preventDefault()
        collectFromDataTransfer(event.dataTransfer).then(load, (reason: Error) => setError(reason.message))
    }

    const selected = analysis?.groups.filter((group) => !excluded.has(group.folder)).flatMap((group) => group.items) ?? []

    const send = async (items: DroppedFile[]) => {
        setError(null)

        try {
            uploadUrlRef.current ??= await resolveUploadUrl()
        } catch (reason) {
            setError((reason as Error).message)

            return
        }

        const controller = new AbortController()
        const failures: DroppedFile[] = []
        abortRef.current = controller
        setTotal(items.length)
        setCounts({ created: 0, skipped: 0, bytes: 0 })
        setFailed([])
        setStatus("uploading")

        try {
            await runUploadQueue(uploadUrlRef.current, items, controller.signal, (item, outcome) => {
                if (outcome === "failed") {
                    failures.push(item)
                    setFailed([...failures])

                    return
                }

                setCounts((current) => ({
                    ...current,
                    [outcome]: current[outcome] + 1,
                    bytes: current.bytes + (outcome === "created" ? item.file.size : 0),
                }))
            })
            setStatus("done")
        } catch (reason) {
            if (reason instanceof SessionExpiredError) {
                setError(reason.message)
                setStatus("expired")

                return
            }

            throw reason
        } finally {
            onFinished()
        }
    }

    const settled = counts.created + counts.skipped + failed.length

    if (status === "idle") {
        return (
            <div
                onDragOver={(event) => event.preventDefault()}
                onDrop={onDrop}
                className="flex flex-col items-center gap-2 rounded-lg border border-dashed p-6 text-center text-sm"
            >
                <p>Drop the photobooth folder here</p>
                <Label htmlFor="transfer-folder" className="cursor-pointer underline">
                    or choose a folder
                </Label>
                <input
                    id="transfer-folder"
                    type="file"
                    multiple
                    className="sr-only"
                    {...{ webkitdirectory: "" }}
                    onChange={(event) => event.target.files && load(collectFromInput(event.target.files))}
                />
                {error && <p className="text-destructive">{error}</p>}
            </div>
        )
    }

    if (status === "review" && analysis) {
        return (
            <div className="space-y-3 text-sm">
                <ul className="space-y-1">
                    {analysis.groups.map((group) => (
                        <li key={group.folder} className="flex items-center gap-2">
                            <Checkbox
                                id={`transfer-group-${group.folder || "root"}`}
                                checked={!excluded.has(group.folder)}
                                onCheckedChange={(checked) => {
                                    const next = new Set(excluded)
                                    if (checked === true) {
                                        next.delete(group.folder)
                                    } else {
                                        next.add(group.folder)
                                    }
                                    setExcluded(next)
                                }}
                            />
                            <Label htmlFor={`transfer-group-${group.folder || "root"}`} className="font-normal">
                                {group.folder === "" ? "/" : `${group.folder}/`} — {group.items.length} photos
                            </Label>
                        </li>
                    ))}
                </ul>
                {analysis.tooLarge.length > 0 && (
                    <p className="text-destructive">
                        Over 10 MB, not sent: {analysis.tooLarge.map((item) => item.path).join(", ")}
                    </p>
                )}
                {analysis.ignoredCount > 0 && (
                    <p className="text-muted-foreground">{analysis.ignoredCount} non-image files ignored.</p>
                )}
                {error && <p className="text-destructive">{error}</p>}
                <div className="flex justify-end gap-2">
                    <Button type="button" variant="outline" onClick={() => setStatus("idle")}>
                        Change folder
                    </Button>
                    <Button type="button" disabled={disabled || selected.length === 0} onClick={() => void send(selected)}>
                        Send {selected.length} photos
                    </Button>
                </div>
            </div>
        )
    }

    return (
        <div className="space-y-3 text-sm">
            <progress value={settled} max={total} className="w-full" />
            <p>
                {settled} / {total} — {formatBytes(counts.bytes)} sent, {counts.skipped} already online,{" "}
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
                    <Button type="button" variant="outline" onClick={() => abortRef.current?.abort()}>
                        Cancel
                    </Button>
                )}
                {status === "done" && failed.length > 0 && (
                    <Button type="button" onClick={() => void send(failed)}>
                        Retry failed
                    </Button>
                )}
            </div>
        </div>
    )
}
