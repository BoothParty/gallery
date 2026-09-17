import { FormEvent } from "react"
import { Link, useForm } from "@inertiajs/react"

import { AdminLayout, type SidebarParty } from "@/components/admin-layout"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"

type PageProps = {
    currentPartyId: number | null
    parties: SidebarParty[]
    searchableParties: SidebarParty[]
    audiobookPending: number
    user: {
        id: number
        name: string
        email: string
    }
}

export default function UsersEdit({
    currentPartyId,
    parties,
    searchableParties,
    audiobookPending,
    user,
}: PageProps) {
    const form = useForm({
        name: user.name,
        email: user.email,
    })

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault()
        form.put(`/admin/users/${user.id}`)
    }

    return (
        <AdminLayout
            title={`Administration / Users / #${user.id}`}
            sidebar={{ currentPartyId, parties, searchableParties, audiobookPending }}
        >
            <form onSubmit={submit} className="max-w-xl space-y-4 rounded-xl border bg-muted/30 p-4">
                <div className="space-y-2">
                    <Label htmlFor="name">Name</Label>
                    <Input
                        id="name"
                        value={form.data.name}
                        onChange={(event) => form.setData("name", event.target.value)}
                        required
                    />
                    {form.errors.name && <p className="text-xs text-red-500">{form.errors.name}</p>}
                </div>
                <div className="space-y-2">
                    <Label htmlFor="email">Email</Label>
                    <Input
                        id="email"
                        type="email"
                        value={form.data.email}
                        onChange={(event) => form.setData("email", event.target.value)}
                        required
                    />
                    {form.errors.email && <p className="text-xs text-red-500">{form.errors.email}</p>}
                </div>
                <div className="flex items-center gap-2">
                    <Button type="submit" disabled={form.processing}>Update user</Button>
                    <Button type="button" variant="outline" asChild>
                        <Link href="/admin/users">Back</Link>
                    </Button>
                </div>
            </form>
        </AdminLayout>
    )
}
