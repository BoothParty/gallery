import { useEffect, useRef, useState } from "react"
import { FolderUpIcon } from "lucide-react"

import { collectFromDataTransfer, collectFromInput, type CollectedFolder } from "@/lib/folder-drop"
import { cn } from "@/lib/utils"

type PageDropZoneProps = {
    /** Off while the dialog is open, its own drop zone taking the folder then, or while an upload runs. */
    enabled: boolean
    onPick: (collected: CollectedFolder) => void
}

const carriesFiles = (event: DragEvent) => Array.from(event.dataTransfer?.types ?? []).includes("Files")

/**
 * The zone under the heading, and a veil over the whole window while files
 * are dragged over it: a folder dropped anywhere opens the new transfer.
 */
export function PageDropZone({ enabled, onPick }: PageDropZoneProps) {
    const [dragging, setDragging] = useState(false)
    const [error, setError] = useState<string | null>(null)
    const inputRef = useRef<HTMLInputElement>(null)
    // Read through a ref so the window listeners need not be swapped on every render.
    const onPickRef = useRef(onPick)

    useEffect(() => {
        onPickRef.current = onPick
    })

    useEffect(() => {
        if (!enabled) {
            return
        }

        // dragenter and dragleave fire for every child crossed: only the
        // outermost pair means the files entered or left the window.
        let depth = 0

        const enter = (event: DragEvent) => {
            if (carriesFiles(event)) {
                event.preventDefault()
                depth++
                setDragging(true)
            }
        }
        const over = (event: DragEvent) => carriesFiles(event) && event.preventDefault()
        const leave = (event: DragEvent) => {
            if (carriesFiles(event) && --depth <= 0) {
                depth = 0
                setDragging(false)
            }
        }
        const drop = (event: DragEvent) => {
            if (!carriesFiles(event) || !event.dataTransfer) {
                return
            }

            event.preventDefault()
            depth = 0
            setDragging(false)
            setError(null)
            collectFromDataTransfer(event.dataTransfer).then((collected) => onPickRef.current(collected), (reason: Error) => setError(reason.message))
        }

        window.addEventListener("dragenter", enter)
        window.addEventListener("dragover", over)
        window.addEventListener("dragleave", leave)
        window.addEventListener("drop", drop)

        return () => {
            window.removeEventListener("dragenter", enter)
            window.removeEventListener("dragover", over)
            window.removeEventListener("dragleave", leave)
            window.removeEventListener("drop", drop)
            setDragging(false)
        }
    }, [enabled])

    return (
        <>
            <button
                type="button"
                disabled={!enabled}
                onClick={() => inputRef.current?.click()}
                className={cn(
                    "flex w-full items-center gap-4 rounded-lg border-[1.5px] border-dashed border-foreground/20 px-5 py-4.5 text-left transition-colors enabled:hover:border-foreground/45 enabled:hover:bg-card disabled:cursor-not-allowed disabled:opacity-50",
                    dragging && "border-foreground bg-card",
                )}
            >
                <span className="flex size-11 shrink-0 items-center justify-center rounded-full bg-muted">
                    <FolderUpIcon className="size-4.5" />
                </span>
                <span className="flex min-w-0 flex-1 flex-col gap-0.75">
                    <span className="font-heading text-[19px] leading-tight">Drop the photobooth folder here</span>
                    <span className="text-[13px] text-pretty text-muted-foreground">
                        The new transfer opens with it, ready to be named. You can also{" "}
                        <span className="text-foreground underline underline-offset-3">choose a folder</span>.
                    </span>
                    {error && <span className="text-[13px] text-destructive">{error}</span>}
                </span>
            </button>
            <input
                ref={inputRef}
                id="transfer-page-folder"
                type="file"
                multiple
                className="sr-only"
                tabIndex={-1}
                {...{ webkitdirectory: "" }}
                onChange={(event) => {
                    if (event.target.files?.length) {
                        onPick(collectFromInput(event.target.files))
                    }
                    event.target.value = ""
                }}
            />

            <div
                aria-hidden={!dragging}
                data-dragging={dragging}
                className={cn(
                    "pointer-events-none fixed inset-0 z-70 flex items-center justify-center bg-[rgb(20_18_15/0.72)] opacity-0 backdrop-blur-[3px] transition-opacity duration-150",
                    dragging && "opacity-100",
                )}
            >
                <div className="flex flex-col items-center gap-2 rounded-[10px] border-[1.5px] border-dashed border-white/60 px-15 py-11.5 text-center text-[#fdfcfa]">
                    <span className="font-heading text-[34px] leading-tight">Drop to create a transfer</span>
                    <span className="text-[13px] text-white/65">Name it, and the upload starts</span>
                </div>
            </div>
        </>
    )
}
