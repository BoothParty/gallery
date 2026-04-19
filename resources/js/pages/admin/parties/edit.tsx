import { FormEvent } from "react"
import { Link, useForm } from "@inertiajs/react"

import { AdminLayout } from "@/components/admin-layout"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select"

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
    party: {
        id: number
        name: string
        user_id: number | null
    }
}

export default function PartiesEdit({
    currentPartyId,
    parties,
    searchableParties,
    users,
    party,
}: PageProps) {
    const form = useForm({
        name: party.name,
        user_id: party.user_id as number | null,
    })

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault()
        form.put(`/admin/parties/${encodeURIComponent(party.name)}`)
    }

    return (
        <AdminLayout
            title={`Administration / Parties / ${party.name}`}
            sidebar={{ currentPartyId, parties, searchableParties }}
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
                    <Label htmlFor="owner">Owner (optional)</Label>
                    <Select
                        value={form.data.user_id === null ? "none" : String(form.data.user_id)}
                        onValueChange={(value) => {
                            form.setData("user_id", value === "none" ? null : Number(value))
                        }}
                    >
                        <SelectTrigger id="owner" className="h-10 w-full">
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

                <div className="flex items-center gap-2">
                    <Button type="submit" disabled={form.processing}>Update party</Button>
                    <Button type="button" variant="outline" asChild>
                        <Link href="/admin/parties">Back</Link>
                    </Button>
                </div>
            </form>
        </AdminLayout>
    )
}
