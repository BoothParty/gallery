import type { DroppedFile } from "@/lib/folder-drop"

/**
 * Sends a folder's pictures one request each, a few at a time. Network errors,
 * 5xx and 429 are retried with a growing delay; any other refusal fails the
 * picture alone, except an expired session, which stops the whole queue since
 * every following request would be refused the same way.
 */

export type UploadOutcome = "created" | "skipped" | "failed"

export class SessionExpiredError extends Error {
    constructor() {
        super("Session expired, log in again then drop the folder again.")
    }
}

const CONCURRENCY = 3
const RETRY_DELAYS_MS = [1_000, 3_000, 9_000]

/**
 * Laravel refreshes the XSRF-TOKEN cookie on every response, so it is read
 * per request rather than once at page load.
 */
export function xsrfHeaders(): Record<string, string> {
    const match = document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)

    return {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
        "X-XSRF-TOKEN": match ? decodeURIComponent(match[1]) : "",
    }
}

const sleep = (ms: number, signal: AbortSignal) =>
    new Promise<void>((resolve, reject) => {
        const timer = setTimeout(resolve, ms)
        signal.addEventListener("abort", () => {
            clearTimeout(timer)
            reject(signal.reason)
        }, { once: true })
    })

const isRetryable = (status: number) => status === 429 || status >= 500

async function upload(url: string, item: DroppedFile, signal: AbortSignal): Promise<"created" | "skipped"> {
    for (let attempt = 0; ; attempt++) {
        const body = new FormData()
        body.append("photos[]", item.file)
        body.append("paths[]", item.path)

        let response: Response | null = null

        try {
            response = await fetch(url, { method: "POST", body, signal, headers: xsrfHeaders() })
        } catch (error) {
            if (signal.aborted) {
                throw error
            }
        }

        if (response?.ok) {
            const { created } = (await response.json()) as { created: number }

            return created > 0 ? "created" : "skipped"
        }

        if (response && (response.status === 401 || response.status === 419)) {
            throw new SessionExpiredError()
        }

        if ((response && !isRetryable(response.status)) || attempt >= RETRY_DELAYS_MS.length) {
            throw new Error(response ? `HTTP ${response.status}` : "Network error")
        }

        await sleep(RETRY_DELAYS_MS[attempt], signal)
    }
}

export async function runUploadQueue(
    url: string,
    items: DroppedFile[],
    signal: AbortSignal,
    onSettled: (item: DroppedFile, outcome: UploadOutcome) => void,
): Promise<void> {
    let next = 0
    let sessionExpired = false

    const worker = async () => {
        while (next < items.length && !signal.aborted && !sessionExpired) {
            const item = items[next++]

            try {
                onSettled(item, await upload(url, item, signal))
            } catch (error) {
                if (signal.aborted) {
                    return
                }

                if (error instanceof SessionExpiredError) {
                    sessionExpired = true

                    return
                }

                onSettled(item, "failed")
            }
        }
    }

    await Promise.all(Array.from({ length: CONCURRENCY }, worker))

    if (sessionExpired) {
        throw new SessionExpiredError()
    }
}
