import { DragEvent, useState } from "react"

import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import { Label } from "@/components/ui/label"
import {
    collectFromDataTransfer,
    collectFromInput,
    type CollectedFolder,
    type FolderAnalysis,
} from "@/lib/folder-drop"

export type PickedFolder = { root: string | null, analysis: FolderAnalysis }

type FolderPickerProps = {
    folder: PickedFolder | null
    /** Subfolders the user unticked, by path. */
    excluded: Set<string>
    onExcludedChange: (excluded: Set<string>) => void
    onPick: (collected: CollectedFolder) => void
    onClear: () => void
}

/** A drop zone until a folder is picked, then its summary. */
export function FolderPicker({ folder, excluded, onExcludedChange, onPick, onClear }: FolderPickerProps) {
    const [error, setError] = useState<string | null>(null)

    if (!folder) {
        const onDrop = (event: DragEvent<HTMLDivElement>) => {
            event.preventDefault()
            setError(null)
            collectFromDataTransfer(event.dataTransfer).then(onPick, (reason: Error) => setError(reason.message))
        }

        return (
            <div
                onDragOver={(event) => event.preventDefault()}
                onDrop={onDrop}
                className="flex flex-col items-center gap-1 rounded-md border-[1.5px] border-dashed border-foreground/20 bg-background px-4.5 py-6.5 text-center"
            >
                <p className="text-sm">Drop the photobooth folder here</p>
                <Label htmlFor="transfer-folder" className="cursor-pointer text-[13px] font-normal text-[#6b5a44] underline underline-offset-3">
                    or choose a folder
                </Label>
                <input
                    id="transfer-folder"
                    type="file"
                    multiple
                    className="sr-only"
                    {...{ webkitdirectory: "" }}
                    onChange={(event) => event.target.files && onPick(collectFromInput(event.target.files))}
                />
                {error && <p className="text-[13px] text-destructive">{error}</p>}
            </div>
        )
    }

    const { analysis } = folder

    return (
        <div className="flex flex-col gap-3 rounded-md border bg-background p-3.5 text-[13px]">
            <ul className="space-y-1.5">
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
                                onExcludedChange(next)
                            }}
                        />
                        <Label htmlFor={`transfer-group-${group.folder || "root"}`} className="text-[13px] font-normal">
                            {group.folder === "" ? "/" : `${group.folder}/`} — {group.items.length} {group.items.length === 1 ? "photo" : "photos"}
                        </Label>
                    </li>
                ))}
            </ul>
            {analysis.groups.length === 0 && <p className="text-destructive">No JPEG or PNG pictures in this folder.</p>}
            {analysis.tooLarge.length > 0 && (
                <p className="text-destructive">
                    Over 10 MB, not sent: {analysis.tooLarge.map((item) => item.path).join(", ")}
                </p>
            )}
            {analysis.ignoredCount > 0 && (
                <p className="text-muted-foreground">{analysis.ignoredCount} non-image files ignored.</p>
            )}
            <div className="flex items-center justify-between gap-3 text-xs text-muted-foreground">
                <span className="min-w-0 truncate">{folder.root ?? "Picked files"}</span>
                <Button type="button" variant="link" className="h-auto p-0 text-xs text-[#6b5a44]" onClick={onClear}>
                    Change folder
                </Button>
            </div>
        </div>
    )
}
