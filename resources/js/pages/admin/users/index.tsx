import { FormEvent, useState } from "react"
import { router, useForm } from "@inertiajs/react"

import { AdminLayout, type SidebarData } from "@/components/admin-layout"
import { DataTable, dataColumn, type RowAction } from "@/components/data-table"
import { FormField } from "@/components/form-field"
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

type UserRow = {
    id: number
    name: string
    email: string
    parties_count: number
    created_at: string | null
}

type PageProps = SidebarData & {
    rows: UserRow[]
}

const columns = [
    dataColumn<UserRow>("name", "Name"),
    dataColumn<UserRow>("email", "Email"),
    dataColumn<UserRow>("parties_count", "Parties"),
    dataColumn<UserRow>("created_at", "Created", { type: "datetime" }),
]

/**
 * Null opens the dialog in creation mode, a row opens it on that user.
 */
type DialogState = { user: UserRow | null } | null

export default function UsersIndex({ rows, ...sidebar }: PageProps) {
    const [dialog, setDialog] = useState<DialogState>(null)
    const form = useForm({ name: "", email: "" })
    const editedUser = dialog?.user ?? null

    const openDialog = (user: UserRow | null) => {
        form.clearErrors()
        form.setData({ name: user?.name ?? "", email: user?.email ?? "" })
        setDialog({ user })
    }

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault()

        const options = { onSuccess: () => setDialog(null) }

        if (editedUser) {
            form.put(route("admin.users.update", editedUser.id), options)
        } else {
            form.post(route("admin.users.store"), options)
        }
    }

    const deleteUser = (user: UserRow) => {
        if (window.confirm("Delete this user?")) {
            router.delete(route("admin.users.destroy", user.id))
        }
    }

    const rowActions: RowAction<UserRow>[] = [
        { label: "Edit", onClick: openDialog },
        { label: "Delete", variant: "destructive", onClick: deleteUser },
    ]

    const idPrefix = editedUser ? "edit-user" : "create-user"

    return (
        <AdminLayout title="Administration / Users" sidebar={sidebar}>
            <DataTable
                data={rows}
                columns={columns}
                emptyMessage="No users found."
                onRowClick={openDialog}
                rowActions={rowActions}
                toolbarAction={
                    <Button size="sm" onClick={() => openDialog(null)}>
                        Create user
                    </Button>
                }
            />

            <Dialog open={dialog !== null} onOpenChange={(open) => !open && setDialog(null)}>
                <DialogContent className="max-w-xl">
                    <DialogHeader>
                        <DialogTitle>{editedUser ? "User details" : "Create user"}</DialogTitle>
                        <DialogDescription>
                            {editedUser ? "Update this user." : "Add a new user to the administration."}
                        </DialogDescription>
                    </DialogHeader>

                    <form onSubmit={submit} className="mt-4 space-y-4">
                        <FormField id={`${idPrefix}-name`} label="Name" error={form.errors.name}>
                            <Input
                                id={`${idPrefix}-name`}
                                value={form.data.name}
                                onChange={(event) => form.setData("name", event.target.value)}
                                required
                            />
                        </FormField>

                        <FormField id={`${idPrefix}-email`} label="Email" error={form.errors.email}>
                            <Input
                                id={`${idPrefix}-email`}
                                type="email"
                                value={form.data.email}
                                onChange={(event) => form.setData("email", event.target.value)}
                                required
                            />
                        </FormField>

                        <DialogFooter>
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setDialog(null)}
                                disabled={form.processing}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {editedUser ? "Update user" : "Save user"}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    )
}
