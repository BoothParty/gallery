import { FormEvent, useState } from "react"
import { useForm } from "@inertiajs/react"

import { AdminLayout } from "@/components/admin-layout"
import { DataTable } from "@/components/data-table"
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
        route_key: string
    }[]
    tableData: CrudTableData
}

export default function PartiesIndex({
    currentPartyId,
    parties,
    searchableParties,
    users,
    editableParties,
    tableData,
}: PageProps) {
    const [isCreateModalOpen, setCreateModalOpen] = useState(false)
    const [isDetailsModalOpen, setDetailsModalOpen] = useState(false)
    const [selectedPartyId, setSelectedPartyId] = useState<number | null>(null)
    const [selectedPartyRouteKey, setSelectedPartyRouteKey] = useState<string | null>(null)
    const form = useForm({
        name: "",
        user_id: null as number | null,
        expire_at: "",
    })
    const editForm = useForm({
        name: "",
        user_id: null as number | null,
        expire_at: "",
    })
    const quickDeleteForm = useForm({})

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
        editForm.setData({
            name: editableParty.name,
            user_id: editableParty.user_id,
            expire_at: toDateTimeLocalValue(editableParty.expire_at),
        })
        setDetailsModalOpen(true)
    }

    const submitEdit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault()

        if (!selectedPartyRouteKey || !selectedPartyId) {
            return
        }

        editForm.transform((data) => ({ ...data, expire_at: fromDateTimeLocalValue(data.expire_at) }))
        editForm.put(`/admin/parties/${encodeURIComponent(selectedPartyRouteKey)}`, {
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

    const rowActions: CrudRowAction[] = [
        {
            label: "Edit",
            onClick: openPartyEdit,
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
                    <Button size="sm" onClick={() => setCreateModalOpen(true)}>
                        Create party
                    </Button>
                }
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
                    }
                }}
            >
                <DialogContent className="max-w-xl">
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

                        <DialogFooter>
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
