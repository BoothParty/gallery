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
import type { CrudRow, CrudRowAction, CrudTableData } from "@/components/data-table/types"

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
    tableData: CrudTableData
}

export default function UsersIndex({
    currentPartyId,
    parties,
    searchableParties,
    tableData,
}: PageProps) {
    const [isCreateModalOpen, setCreateModalOpen] = useState(false)
    const [isDetailsModalOpen, setDetailsModalOpen] = useState(false)
    const [selectedUserId, setSelectedUserId] = useState<number | null>(null)

    const form = useForm({
        name: "",
        email: "",
        password: "",
        password_confirmation: "",
    })

    const editForm = useForm({
        name: "",
        email: "",
        password: "",
        password_confirmation: "",
    })

    const quickDeleteForm = useForm({})

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault()
        form.post("/admin/users", {
            onSuccess: () => {
                form.reset()
                setCreateModalOpen(false)
            },
        })
    }

    const openUserDetails = (row: CrudRow) => {
        const rowId = row.id

        if (typeof rowId !== "number" && typeof rowId !== "string") {
            return
        }

        const parsedId = Number(rowId)

        if (!Number.isFinite(parsedId)) {
            return
        }

        setSelectedUserId(parsedId)
        editForm.setData({
            name: typeof row.name === "string" ? row.name : "",
            email: typeof row.email === "string" ? row.email : "",
            password: "",
            password_confirmation: "",
        })
        setDetailsModalOpen(true)
    }

    const submitEdit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault()

        if (!selectedUserId) {
            return
        }

        editForm.put(`/admin/users/${selectedUserId}`, {
            onSuccess: () => {
                editForm.reset("password", "password_confirmation")
                setDetailsModalOpen(false)
            },
        })
    }

    const deleteSelectedUser = () => {
        if (!selectedUserId) {
            return
        }

        editForm.delete(`/admin/users/${selectedUserId}`, {
            onSuccess: () => {
                setDetailsModalOpen(false)
                setSelectedUserId(null)
            },
        })
    }

    const deleteUserFromRow = (row: CrudRow) => {
        const rowId = row.id

        if (typeof rowId !== "number" && typeof rowId !== "string") {
            return
        }

        const parsedId = Number(rowId)

        if (!Number.isFinite(parsedId)) {
            return
        }

        if (!window.confirm("Delete this user?")) {
            return
        }

        quickDeleteForm.delete(`/admin/users/${parsedId}`)
    }

    const rowActions: CrudRowAction[] = [
        {
            label: "Edit",
            onClick: openUserDetails,
        },
        {
            label: "Delete",
            variant: "destructive",
            onClick: deleteUserFromRow,
        },
    ]

    return (
        <AdminLayout
            title="Administration / Users"
            sidebar={{ currentPartyId, parties, searchableParties }}
        >
            <DataTable
                tableData={tableData}
                onRowClick={openUserDetails}
                rowActions={rowActions}
                toolbarAction={
                    <Button size="sm" onClick={() => setCreateModalOpen(true)}>
                        Create user
                    </Button>
                }
            />

            <Dialog open={isCreateModalOpen} onOpenChange={setCreateModalOpen}>
                <DialogContent className="max-w-xl">
                    <DialogHeader>
                        <DialogTitle>Create user</DialogTitle>
                        <DialogDescription>
                            Add a new user to the administration.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submit} className="mt-4 space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="create-user-name">Name</Label>
                            <Input
                                id="create-user-name"
                                value={form.data.name}
                                onChange={(event) => form.setData("name", event.target.value)}
                                required
                            />
                            {form.errors.name && <p className="text-xs text-red-500">{form.errors.name}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="create-user-email">Email</Label>
                            <Input
                                id="create-user-email"
                                type="email"
                                value={form.data.email}
                                onChange={(event) => form.setData("email", event.target.value)}
                                required
                            />
                            {form.errors.email && <p className="text-xs text-red-500">{form.errors.email}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="create-user-password">Password</Label>
                            <Input
                                id="create-user-password"
                                type="password"
                                value={form.data.password}
                                onChange={(event) => form.setData("password", event.target.value)}
                                required
                            />
                            {form.errors.password && <p className="text-xs text-red-500">{form.errors.password}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="create-user-password-confirmation">Confirm password</Label>
                            <Input
                                id="create-user-password-confirmation"
                                type="password"
                                value={form.data.password_confirmation}
                                onChange={(event) => form.setData("password_confirmation", event.target.value)}
                                required
                            />
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
                                Save user
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
                        setSelectedUserId(null)
                    }
                }}
            >
                <DialogContent className="max-w-xl">
                    <DialogHeader>
                        <DialogTitle>User details</DialogTitle>
                        <DialogDescription>
                            Update or delete this user.
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submitEdit} className="mt-4 space-y-4">
                        <div className="space-y-2">
                            <Label htmlFor="edit-user-name">Name</Label>
                            <Input
                                id="edit-user-name"
                                value={editForm.data.name}
                                onChange={(event) => editForm.setData("name", event.target.value)}
                                required
                            />
                            {editForm.errors.name && <p className="text-xs text-red-500">{editForm.errors.name}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="edit-user-email">Email</Label>
                            <Input
                                id="edit-user-email"
                                type="email"
                                value={editForm.data.email}
                                onChange={(event) => editForm.setData("email", event.target.value)}
                                required
                            />
                            {editForm.errors.email && <p className="text-xs text-red-500">{editForm.errors.email}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="edit-user-password">New password (optional)</Label>
                            <Input
                                id="edit-user-password"
                                type="password"
                                value={editForm.data.password}
                                onChange={(event) => editForm.setData("password", event.target.value)}
                            />
                            {editForm.errors.password && <p className="text-xs text-red-500">{editForm.errors.password}</p>}
                        </div>

                        <div className="space-y-2">
                            <Label htmlFor="edit-user-password-confirmation">Confirm new password</Label>
                            <Input
                                id="edit-user-password-confirmation"
                                type="password"
                                value={editForm.data.password_confirmation}
                                onChange={(event) => editForm.setData("password_confirmation", event.target.value)}
                            />
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
                            <Button type="submit" disabled={editForm.processing || !selectedUserId}>
                                Update user
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    )
}
