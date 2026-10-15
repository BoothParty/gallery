import { useEffect, useRef, useState } from "react"
import { router } from "@inertiajs/react"

import type { DroppedFile } from "@/lib/folder-drop"
import { runUploadQueue, SessionExpiredError } from "@/lib/upload-queue"

export type UploadStatus = "idle" | "uploading" | "done" | "expired"

export type UploadQueue = ReturnType<typeof useUploadQueue>

/**
 * The page's folder upload, one at a time. It lives above the dialog so the
 * pictures keep going out whatever the dialog shows. The upload URL is
 * resolved once per folder, creating the transfer when needed, and reused to
 * retry the failures.
 */
export function useUploadQueue(onFinished: () => void) {
    const [status, setStatus] = useState<UploadStatus>("idle")
    const [items, setItems] = useState<DroppedFile[]>([])
    const [counts, setCounts] = useState({ created: 0, skipped: 0, bytes: 0 })
    const [failed, setFailed] = useState<DroppedFile[]>([])
    const [error, setError] = useState<string | null>(null)
    const abortRef = useRef<AbortController | null>(null)
    const uploadUrlRef = useRef<string | null>(null)
    const sendingRef = useRef(false)

    // Leaving the page stops the requests with it.
    useEffect(() => () => abortRef.current?.abort(), [])

    useEffect(() => {
        if (status !== "uploading") {
            return
        }

        const warn = (event: BeforeUnloadEvent) => event.preventDefault()
        window.addEventListener("beforeunload", warn)

        // Leaving the page through Inertia unmounts it and stops the queue:
        // ask first. Partial reloads and form submissions stay on the page.
        const removeGuard = router.on("before", (event) => {
            const { method, only } = event.detail.visit

            if (method === "get" && only.length === 0) {
                return window.confirm("Pictures are still uploading. Leave the page and stop the upload?")
            }
        })

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
            removeGuard()
            void lock?.release()
        }
    }, [status])

    const sendNow = async (resolveUploadUrl: () => Promise<string>, batch: DroppedFile[]) => {
        // Switched to uploading before the first await: the send button goes
        // away while the transfer is still being created.
        const controller = new AbortController()
        const failures: DroppedFile[] = []
        abortRef.current = controller
        setError(null)
        setItems(batch)
        setCounts({ created: 0, skipped: 0, bytes: 0 })
        setFailed([])
        setStatus("uploading")

        try {
            uploadUrlRef.current ??= await resolveUploadUrl()
        } catch (reason) {
            setError((reason as Error).message)
            setStatus("idle")

            return
        }

        // Cancelled while the transfer was created.
        if (controller.signal.aborted) {
            setStatus("done")

            return
        }

        try {
            await runUploadQueue(uploadUrlRef.current, batch, controller.signal, (item, outcome) => {
                if (outcome === "failed") {
                    failures.push(item)
                    setFailed([...failures])

                    return
                }

                setCounts((current) => ({
                    ...current,
                    [outcome]: current[outcome] + 1,
                    // Pictures already online count too: the bar tracks the folder, not the network.
                    bytes: current.bytes + item.file.size,
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

    // A ref rather than the status: two clicks dispatched before React
    // re-renders both see the send button, but only the first gets through.
    const send = async (resolveUploadUrl: () => Promise<string>, batch: DroppedFile[]) => {
        if (sendingRef.current) {
            return
        }

        sendingRef.current = true

        try {
            await sendNow(resolveUploadUrl, batch)
        } finally {
            sendingRef.current = false
        }
    }

    return {
        status,
        items,
        counts,
        failed,
        error,
        settled: counts.created + counts.skipped + failed.length,
        start: (resolveUploadUrl: () => Promise<string>, batch: DroppedFile[]) => {
            uploadUrlRef.current = null

            return send(resolveUploadUrl, batch)
        },
        retry: () => send(async () => uploadUrlRef.current ?? "", failed),
        cancel: () => abortRef.current?.abort(),
        reset: () => {
            uploadUrlRef.current = null
            setStatus("idle")
            setItems([])
            setFailed([])
            setError(null)
        },
    }
}
