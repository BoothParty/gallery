import { FormEvent, useState } from "react"
import { useForm } from "@inertiajs/react"
import { DownloadIcon, WandSparklesIcon } from "lucide-react"

import { AdminLayout } from "@/components/admin-layout"
import { DataTable } from "@/components/data-table"
import {
    PartyBulkCreateDialog,
    type PartySourceEvent,
} from "@/components/parties/bulk-create-dialog"
import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
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
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select"
import type { CrudRow, CrudRowAction, CrudTableData } from "@/components/data-table/types"
import { fromDateTimeLocalValue, toDateTimeLocalValue } from "@/lib/datetime"

type PageProps = {
    currentPartyId: number | null
    parties: {
        id: number
        name: string
        expired: boolean
    }[]
    searchableParties: {
        id: number
        name: string
        expired: boolean
    }[]
    users: {
        id: number
        name: string
    }[]
    editableParties: {
        id: number
        name: string
        user_id: number | null
        expire_at: string | null
        display_name: string | null
        description: string | null
        banner_url: string | null
        host_edit_url: string
        route_key: string
    }[]
    tableData: CrudTableData
    partyDomain: string
    upcomingSourceEvents?: PartySourceEvent[]
}

export default function PartiesIndex({
    currentPartyId,
    parties,
    searchableParties,
    users,
    editableParties,
    tableData,
    partyDomain,
    upcomingSourceEvents,
}: PageProps) {
    const [isCreateModalOpen, setCreateModalOpen] = useState(false)
    const [isBulkCreateModalOpen, setBulkCreateModalOpen] = useState(false)
    const [isDetailsModalOpen, setDetailsModalOpen] = useState(false)
    const [selectedPartyId, setSelectedPartyId] = useState<number | null>(null)
    const [selectedPartyRouteKey, setSelectedPartyRouteKey] = useState<string | null>(null)
    const [selectedPartyBannerUrl, setSelectedPartyBannerUrl] = useState<string | null>(null)
    const [bannerPreview, setBannerPreview] = useState<string | null>(null)
    const [selectedPartyHostEditUrl, setSelectedPartyHostEditUrl] = useState<string | null>(null)
    const [hostLinkCopied, setHostLinkCopied] = useState(false)
    const form = useForm({
        name: "",
        user_id: null as number | null,
        expire_at: "",
    })
    const editForm = useForm({
        name: "",
        user_id: null as number | null,
        expire_at: "",
        display_name: "",
        description: "",
        banner: null as File | null,
        remove_banner: false,
    })
    const quickDeleteForm = useForm({})
    const hostTokenForm = useForm({})

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault()

        form.transform((data) => ({ ...data, expire_at: fromDateTimeLocalValue(data.expire_at) }))
        form.post("/admin/parties", {
            onSuccess: () => {
                form.reset()
                setCreateModalOpen(false)
            },
        })
    }

    const resolvePartyRouteKey = (row: CrudRow): string | null => {
        if (typeof row.name !== "string" || row.name.trim().length === 0) {
            return null
        }

        return encodeURIComponent(row.name)
    }

    const openPartyDetails = (row: CrudRow) => {
        const rowId = row.id

        if (typeof rowId !== "number" && typeof rowId !== "string") {
            return
        }

        const parsedId = Number(rowId)

        if (!Number.isFinite(parsedId)) {
            return
        }

        const editableParty = editableParties.find((party) => party.id === parsedId)

        if (!editableParty) {
            return
        }

        setSelectedPartyId(parsedId)
        setSelectedPartyRouteKey(editableParty.route_key)
        setSelectedPartyBannerUrl(editableParty.banner_url)
        setSelectedPartyHostEditUrl(editableParty.host_edit_url)
        setHostLinkCopied(false)
        setBannerPreview(null)
        editForm.setData({
            name: editableParty.name,
            user_id: editableParty.user_id,
            expire_at: toDateTimeLocalValue(editableParty.expire_at),
            display_name: editableParty.display_name ?? "",
            description: editableParty.description ?? "",
            banner: null,
            remove_banner: false,
        })
        setDetailsModalOpen(true)
    }

    const submitEdit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault()

        if (!selectedPartyRouteKey || !selectedPartyId) {
            return
        }

        editForm.transform((data) => ({
            ...data,
            _method: "put",
            expire_at: fromDateTimeLocalValue(data.expire_at),
        }))
        // Inertia switches to FormData as soon as a file is present, and
        // multipart cannot carry a PUT, hence the spoofed method above.
        editForm.post(`/admin/parties/${encodeURIComponent(selectedPartyRouteKey)}`, {
            onSuccess: () => {
                setDetailsModalOpen(false)
            },
        })
    }

    const openPartyEdit = (row: CrudRow) => {
        openPartyDetails(row)
    }

    const deletePartyFromRow = (row: CrudRow) => {
        const routeKey = resolvePartyRouteKey(row)

        if (!routeKey) {
            return
        }

        if (!window.confirm("Delete this party?")) {
            return
        }

        quickDeleteForm.delete(`/admin/parties/${routeKey}`)
    }

    const posterHref = (routeKey: string): string =>
        `/admin/parties/${encodeURIComponent(routeKey)}/poster`

    const downloadPosterFromRow = (row: CrudRow) => {
        const routeKey = resolvePartyRouteKey(row)

        if (!routeKey) {
            return
        }

        window.location.href = `/admin/parties/${routeKey}/poster`
    }

    const rowActions: CrudRowAction[] = [
        {
            label: "Edit",
            onClick: openPartyEdit,
        },
        {
            label: "Download poster",
            onClick: downloadPosterFromRow,
        },
        {
            label: "Delete",
            variant: "destructive",
            onClick: deletePartyFromRow,
        },
    ]

    return (
        <AdminLayout
            title="Administration / Parties"
            sidebar={{ currentPartyId, parties, searchableParties }}
        >
            <DataTable
                tableData={tableData}
                onRowClick={openPartyDetails}
                rowActions={rowActions}
                toolbarAction={
                    <>
                        <Button
                            size="sm"
                            variant="outline"
                            onClick={() => setBulkCreateModalOpen(true)}
                        >
                            <WandSparklesIcon className="size-4" />
                            Create from events
                        </Button>
                        <Button size="sm" onClick={() => setCreateModalOpen(true)}>
                            Create party
                        </Button>
                    </>
                }
            />

            <PartyBulkCreateDialog
                open={isBulkCreateModalOpen}
                onOpenChange={setBulkCreateModalOpen}
                partyDomain={partyDomain}
                upcomingSourceEvents={upcomingSourceEvents}
            />

            <Dialog open={isCreateModalOpen} onOpenChange={setCreateModalOpen}>
                <DialogContent className="max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Create party</DialogTitle>
                        <DialogDescription>
                            Add a new party to the administration.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submit} className="mt-4 space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="create-party-name">Name</Label>
                            <Input
                                id="create-party-name"
                                value={form.data.name}
                                onChange={(event) => form.setData("name", event.target.value)}
                                required
                            />
                            {form.errors.name && <p className="text-xs text-red-500">{form.errors.name}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="create-party-owner">Owner (optional)</Label>
                            <Select
                                value={form.data.user_id === null ? "none" : String(form.data.user_id)}
                                onValueChange={(value) => {
                                    form.setData("user_id", value === "none" ? null : Number(value))
                                }}
                            >
                                <SelectTrigger id="create-party-owner" className="h-10 w-full">
                                    <SelectValue placeholder="No owner" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">No owner</SelectItem>
                                    {users.map((user) => (
                                        <SelectItem key={user.id} value={String(user.id)}>
                                            {user.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {form.errors.user_id && <p className="text-xs text-red-500">{form.errors.user_id}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="create-party-expire-at">Expiry date (optional)</Label>
                            <Input
                                id="create-party-expire-at"
                                type="datetime-local"
                                value={form.data.expire_at}
                                onChange={(event) => form.setData("expire_at", event.target.value)}
                            />
                            <p className="text-xs text-muted-foreground">
                                Leave empty to keep the gallery available indefinitely.
                            </p>
                            {form.errors.expire_at && <p className="text-xs text-red-500">{form.errors.expire_at}</p>}
                        </div>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setCreateModalOpen(false)}
                                disabled={form.processing}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                Save party
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>

            <Dialog
                open={isDetailsModalOpen}
                onOpenChange={(open) => {
                    setDetailsModalOpen(open)

                    if (!open) {
                        setSelectedPartyId(null)
                        setSelectedPartyRouteKey(null)
                        setSelectedPartyBannerUrl(null)
                        setSelectedPartyHostEditUrl(null)
                        setHostLinkCopied(false)
                        setBannerPreview(null)
                    }
                }}
            >
                <DialogContent className="max-h-[85vh] max-w-xl overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>Party details</DialogTitle>
                        <DialogDescription>
                            Update this party.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submitEdit} className="mt-4 space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="edit-party-name">Name</Label>
                            <Input
                                id="edit-party-name"
                                value={editForm.data.name}
                                onChange={(event) => editForm.setData("name", event.target.value)}
                                required
                            />
                            {editForm.errors.name && <p className="text-xs text-red-500">{editForm.errors.name}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="edit-party-display-name">Display name (optional)</Label>
                            <Input
                                id="edit-party-display-name"
                                value={editForm.data.display_name}
                                onChange={(event) => editForm.setData("display_name", event.target.value)}
                            />
                            <p className="text-xs text-muted-foreground">
                                Title shown on the public gallery. Defaults to the party name.
                            </p>
                            {editForm.errors.display_name && (
                                <p className="text-xs text-red-500">{editForm.errors.display_name}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="edit-party-description">Description (optional)</Label>
                            <Input
                                id="edit-party-description"
                                value={editForm.data.description}
                                onChange={(event) => editForm.setData("description", event.target.value)}
                            />
                            <p className="text-xs text-muted-foreground">
                                One line below the title, such as the date and the venue.
                            </p>
                            {editForm.errors.description && (
                                <p className="text-xs text-red-500">{editForm.errors.description}</p>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="edit-party-banner">Banner (optional)</Label>

                            {(bannerPreview ?? selectedPartyBannerUrl) && (
                                <img
                                    id="edit-party-banner-preview"
                                    src={bannerPreview ?? selectedPartyBannerUrl ?? undefined}
                                    alt="Current banner"
                                    className="h-32 w-full rounded-lg border object-cover"
                                />
                            )}

                            <Input
                                id="edit-party-banner"
                                type="file"
                                accept="image/*"
                                onChange={(event) => {
                                    const file = event.target.files?.[0] ?? null
                                    editForm.setData("banner", file)
                                    setBannerPreview(file === null ? null : URL.createObjectURL(file))
                                }}
                            />
                            <p className="text-xs text-muted-foreground">
                                Leave empty to keep the current banner. Without one, the gallery shows
                                its striped placeholder.
                            </p>
                            {editForm.errors.banner && (
                                <p className="text-xs text-red-500">{editForm.errors.banner}</p>
                            )}

                            {selectedPartyBannerUrl && (
                                <div className="flex items-center gap-2 pt-1">
                                    <Checkbox
                                        id="edit-party-remove-banner"
                                        checked={editForm.data.remove_banner}
                                        onCheckedChange={(checked) =>
                                            editForm.setData("remove_banner", checked === true)
                                        }
                                    />
                                    <Label htmlFor="edit-party-remove-banner" className="text-sm font-normal">
                                        Remove the current banner
                                    </Label>
                                </div>
                            )}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="edit-party-owner">Owner (optional)</Label>
                            <Select
                                value={editForm.data.user_id === null ? "none" : String(editForm.data.user_id)}
                                onValueChange={(value) => {
                                    editForm.setData("user_id", value === "none" ? null : Number(value))
                                }}
                            >
                                <SelectTrigger id="edit-party-owner" className="h-10 w-full">
                                    <SelectValue placeholder="No owner" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="none">No owner</SelectItem>
                                    {users.map((user) => (
                                        <SelectItem key={user.id} value={String(user.id)}>
                                            {user.name}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            {editForm.errors.user_id && <p className="text-xs text-red-500">{editForm.errors.user_id}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="edit-party-expire-at">Expiry date (optional)</Label>
                            <Input
                                id="edit-party-expire-at"
                                type="datetime-local"
                                value={editForm.data.expire_at}
                                onChange={(event) => editForm.setData("expire_at", event.target.value)}
                            />
                            <p className="text-xs text-muted-foreground">
                                Leave empty to keep the gallery available indefinitely.
                            </p>
                            {editForm.errors.expire_at && <p className="text-xs text-red-500">{editForm.errors.expire_at}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="edit-party-host-link">Host edit link</Label>
                            <div className="flex gap-2">
                                <Input
                                    id="edit-party-host-link"
                                    readOnly
                                    value={selectedPartyHostEditUrl ?? ""}
                                    onFocus={(event) => event.target.select()}
                                />
                                <Button
                                    type="button"
                                    variant="outline"
                                    onClick={() => {
                                        if (selectedPartyHostEditUrl) {
                                            void navigator.clipboard.writeText(selectedPartyHostEditUrl)
                                            setHostLinkCopied(true)
                                        }
                                    }}
                                >
                                    {hostLinkCopied ? "Copied" : "Copy"}
                                </Button>
                            </div>
                            <p className="text-xs text-muted-foreground">
                                Send this to the host so they can edit the gallery title, description and
                                cover without an account.
                            </p>
                            <Button
                                type="button"
                                variant="ghost"
                                className="h-auto px-0 text-xs text-destructive hover:bg-transparent"
                                disabled={hostTokenForm.processing}
                                onClick={() => {
                                    if (!selectedPartyRouteKey) {
                                        return
                                    }

                                    if (!window.confirm("Regenerate the link? The one already sent will stop working.")) {
                                        return
                                    }

                                    hostTokenForm.post(
                                        `/admin/parties/${encodeURIComponent(selectedPartyRouteKey)}/host-token`,
                                        { onSuccess: () => setDetailsModalOpen(false) },
                                    )
                                }}
                            >
                                Regenerate the link
                            </Button>
                        </div>

                        <DialogFooter>
                            {selectedPartyRouteKey && (
                                <Button asChild type="button" variant="outline" className="sm:mr-auto">
                                    <a id="download-party-poster" href={posterHref(selectedPartyRouteKey)} download>
                                        <DownloadIcon className="size-4" />
                                        Download poster
                                    </a>
                                </Button>
                            )}
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setDetailsModalOpen(false)}
                                disabled={editForm.processing}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={editForm.processing || !selectedPartyId || !selectedPartyRouteKey}>
                                Update party
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    )
}
