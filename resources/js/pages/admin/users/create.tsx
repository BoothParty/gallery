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
}

export default function UsersCreate({
    currentPartyId,
    parties,
    searchableParties,
    audiobookPending,
}: PageProps) {
    const form = useForm({
        name: "",
        email: "",
    })

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault()
        form.post("/admin/users")
    }

    return (
        <AdminLayout
            title="Administration / Users / Create"
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
                    <Button type="submit" disabled={form.processing}>Save user</Button>
                    <Button type="button" variant="outline" asChild>
                        <Link href="/admin/users">Cancel</Link>
                    </Button>
                </div>
            </form>
        </AdminLayout>
    )
}
