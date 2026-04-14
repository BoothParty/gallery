import * as React from "react"
import { TrashIcon } from "lucide-react"

import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"

interface GalleryToolbarProps {
    totalOnPage: number
    totalAll: number
    selectedCount: number
    allSelected: boolean
    someSelected: boolean
    onToggleAll: () => void
    onBulkDelete: () => void
    isDeleting: boolean
    toolbarAction?: React.ReactNode
}

export function GalleryToolbar({
    totalOnPage,
    totalAll,
    selectedCount,
    allSelected,
    someSelected,
    onToggleAll,
    onBulkDelete,
    isDeleting,
    toolbarAction,
}: GalleryToolbarProps) {
    return (
        <div className="flex items-center justify-between">
            <div className="flex items-center gap-2">
                <Checkbox
                    checked={allSelected || (someSelected && !allSelected ? "indeterminate" : false)}
                    onCheckedChange={onToggleAll}
                    aria-label="Select all pictures on this page"
                    disabled={totalOnPage === 0}
                />
                <span className="text-sm text-muted-foreground">
                    {someSelected
                        ? `${selectedCount} of ${totalOnPage} selected`
                        : `${totalAll} picture${totalAll !== 1 ? "s" : ""}`}
                </span>
                {someSelected && (
                    <Button
                        variant="destructive"
                        size="sm"
                        onClick={onBulkDelete}
                        disabled={isDeleting}
                    >
                        <TrashIcon className="mr-1.5 h-3.5 w-3.5" />
                        Delete {selectedCount} picture{selectedCount !== 1 ? "s" : ""}
                    </Button>
                )}
            </div>
            {toolbarAction && (
                <div className="flex items-center gap-2">{toolbarAction}</div>
            )}
        </div>
    )
}
