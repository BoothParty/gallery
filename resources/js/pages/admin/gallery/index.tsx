import { useCallback, useRef, useState } from "react"
import { useForm } from "@inertiajs/react"
import { UploadIcon } from "lucide-react"

import { AdminLayout, type SidebarParty } from "@/components/admin-layout"
import { GalleryDeleteDialog } from "@/components/gallery/gallery-delete-dialog"
import { GalleryGrid } from "@/components/gallery/gallery-grid"
import type { GalleryPicture } from "@/components/gallery/gallery-grid"
import { GalleryPagination } from "@/components/gallery/gallery-pagination"
import { GalleryToolbar } from "@/components/gallery/gallery-toolbar"
import { useGalleryPhotoSwipe } from "@/components/gallery/use-gallery-photoswipe"
import { Button } from "@/components/ui/button"
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"

type PaginationLink = {
    url: string | null
    label: string
    active: boolean
}

type PaginatedPictures = {
    current_page: number
    data: GalleryPicture[]
    from: number | null
    last_page: number
    links: PaginationLink[]
    next_page_url: string | null
    per_page: number
    prev_page_url: string | null
    to: number | null
    total: number
}

type PageProps = {
    currentPartyId: number | null
    parties: SidebarParty[]
    searchableParties: SidebarParty[]
    party: { id: number; name: string }
    pictures: PaginatedPictures
    perPage: number
}

export default function GalleryIndex({
    currentPartyId,
    parties,
    searchableParties,
    party,
    pictures,
    perPage,
}: PageProps) {
    const [selected, setSelected] = useState<Set<number>>(new Set())
    const [deleteDialogOpen, setDeleteDialogOpen] = useState(false)
    const [uploadDialogOpen, setUploadDialogOpen] = useState(false)
    const fileInputRef = useRef<HTMLInputElement>(null)
    const deleteForm = useForm<{ ids: number[] }>({ ids: [] })
    const uploadForm = useForm<{ photos: File[] }>({ photos: [] })

    const allSelected =
        pictures.data.length > 0 && pictures.data.every((p) => selected.has(p.id))
    const someSelected = selected.size > 0

    const toggleAll = useCallback(() => {
        setSelected(allSelected ? new Set() : new Set(pictures.data.map((p) => p.id)))
    }, [allSelected, pictures.data])

    const togglePicture = useCallback((id: number) => {
        setSelected((prev) => {
            const next = new Set(prev)
            next.has(id) ? next.delete(id) : next.add(id)
            return next
        })
    }, [])

    const openPhotoSwipe = useGalleryPhotoSwipe(pictures.data)

    const handleBulkDelete = useCallback(() => {
        /*
         * setData schedules a state update, so submitting in the same tick sent
         * the previous (empty) selection and the request was rejected. transform
         * runs at submit time instead, with the selection as it is right now.
         */
        deleteForm.transform(() => ({ ids: Array.from(selected) }))
        deleteForm.delete(`/${encodeURIComponent(party.name)}/dashboard/pictures`, {
            onSuccess: () => {
                setSelected(new Set())
                setDeleteDialogOpen(false)
            },
        })
    }, [selected, party.name, deleteForm])

    const closeUploadDialog = useCallback(() => {
        uploadForm.reset()
        uploadForm.clearErrors()
        if (fileInputRef.current) {
            fileInputRef.current.value = ""
        }
        setUploadDialogOpen(false)
    }, [uploadForm])

    const handleUpload = useCallback(() => {
        uploadForm.post(`/${encodeURIComponent(party.name)}/dashboard/pictures`, {
            forceFormData: true,
            onSuccess: closeUploadDialog,
        })
    }, [party.name, uploadForm, closeUploadDialog])

    return (
        <AdminLayout
            title={`Administration / ${party.name} / Pictures`}
            sidebar={{ currentPartyId, parties, searchableParties }}
        >
            <GalleryToolbar
                totalOnPage={pictures.data.length}
                totalAll={pictures.total}
                selectedCount={selected.size}
                allSelected={allSelected}
                someSelected={someSelected}
                onToggleAll={toggleAll}
                onBulkDelete={() => setDeleteDialogOpen(true)}
                isDeleting={deleteForm.processing}
                toolbarAction={
                    <Button size="sm" onClick={() => setUploadDialogOpen(true)}>
                        <UploadIcon className="mr-1.5 h-3.5 w-3.5" />
                        Upload pictures
                    </Button>
                }
            />

            <GalleryGrid
                pictures={pictures.data}
                selected={selected}
                onToggle={togglePicture}
                onOpen={openPhotoSwipe}
            />

            <GalleryPagination
                currentPage={pictures.current_page}
                lastPage={pictures.last_page}
                from={pictures.from}
                to={pictures.to}
                total={pictures.total}
                perPage={perPage}
                partyName={party.name}
            />

            <GalleryDeleteDialog
                open={deleteDialogOpen}
                onOpenChange={setDeleteDialogOpen}
                count={selected.size}
                onConfirm={handleBulkDelete}
                isDeleting={deleteForm.processing}
            />

            <Dialog
                open={uploadDialogOpen}
                onOpenChange={(open) => {
                    if (!open) {
                        closeUploadDialog()

                        return
                    }
                    setUploadDialogOpen(open)
                }}
            >
                <DialogContent className="max-w-md">
                    <DialogHeader>
                        <DialogTitle>Upload pictures</DialogTitle>
                        <DialogDescription>
                            Select one or more images to add to this party.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={(e) => { e.preventDefault(); handleUpload() }} className="mt-2 space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="upload-photos">Images</Label>
                            <Input
                                id="upload-photos"
                                ref={fileInputRef}
                                type="file"
                                accept="image/*"
                                multiple
                                required
                                onChange={(e) =>
                                    uploadForm.setData("photos", Array.from(e.target.files ?? []))
                                }
                            />
                            {(uploadForm.errors.photos ||
                                (uploadForm.errors as Record<string, string>)["photos.*"]) && (
                                    <p className="text-xs text-destructive">
                                        {uploadForm.errors.photos ||
                                            (uploadForm.errors as Record<string, string>)["photos.*"]}
                                    </p>
                                )}
                        </div>

                        {uploadForm.progress && (
                            <progress
                                value={uploadForm.progress.percentage}
                                max={100}
                                className="h-1.5 w-full rounded-full"
                            >
                                {uploadForm.progress.percentage}%
                            </progress>
                        )}

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={closeUploadDialog}
                                disabled={uploadForm.processing}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={uploadForm.processing || uploadForm.data.photos.length === 0}
                            >
                                {uploadForm.processing ? "Uploading…" : "Upload"}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    )
}
