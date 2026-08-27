import { FormEvent, useState } from "react"
import { Link, useForm } from "@inertiajs/react"

import { AdminLayout } from "@/components/admin-layout"
import { Button } from "@/components/ui/button"
import { Checkbox } from "@/components/ui/checkbox"
import { Input } from "@/components/ui/input"
import { Label } from "@/components/ui/label"
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select"
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
    party: {
        id: number
        name: string
        user_id: number | null
        expire_at: string | null
        display_name: string | null
        description: string | null
        banner_url: string | null
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
        expire_at: toDateTimeLocalValue(party.expire_at),
        display_name: party.display_name ?? "",
        description: party.description ?? "",
        banner: null as File | null,
        remove_banner: false,
    })

    const [bannerPreview, setBannerPreview] = useState<string | null>(null)

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault()
        form.transform((data) => ({
            ...data,
            _method: "put",
            expire_at: fromDateTimeLocalValue(data.expire_at),
        }))
        // Inertia switches to FormData as soon as a file is present, and
        // multipart cannot carry a PUT, hence the spoofed method above.
        form.post(`/admin/parties/${encodeURIComponent(party.name)}`)
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
                    <Label htmlFor="display_name">Display name (optional)</Label>
                    <Input
                        id="display_name"
                        value={form.data.display_name}
                        onChange={(event) => form.setData("display_name", event.target.value)}
                    />
                    <p className="text-xs text-muted-foreground">
                        Title shown on the public gallery. Defaults to the party name.
                    </p>
                    {form.errors.display_name && (
                        <p className="text-xs text-red-500">{form.errors.display_name}</p>
                    )}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="description">Description (optional)</Label>
                    <Input
                        id="description"
                        value={form.data.description}
                        onChange={(event) => form.setData("description", event.target.value)}
                    />
                    <p className="text-xs text-muted-foreground">
                        One line below the title, such as the date and the venue.
                    </p>
                    {form.errors.description && (
                        <p className="text-xs text-red-500">{form.errors.description}</p>
                    )}
                </div>

                <div className="space-y-2">
                    <Label htmlFor="banner">Banner (optional)</Label>

                    {(bannerPreview ?? party.banner_url) && (
                        <img
                            id="banner-preview"
                            src={bannerPreview ?? party.banner_url ?? undefined}
                            alt="Current banner"
                            className="h-32 w-full rounded-lg border object-cover"
                        />
                    )}

                    <Input
                        id="banner"
                        type="file"
                        accept="image/*"
                        onChange={(event) => {
                            const file = event.target.files?.[0] ?? null
                            form.setData("banner", file)
                            setBannerPreview(file === null ? null : URL.createObjectURL(file))
                        }}
                    />
                    <p className="text-xs text-muted-foreground">
                        Leave empty to keep the current banner. Without one, the gallery shows its
                        striped placeholder.
                    </p>
                    {form.errors.banner && <p className="text-xs text-red-500">{form.errors.banner}</p>}

                    {party.banner_url && (
                        <div className="flex items-center gap-2 pt-1">
                            <Checkbox
                                id="remove_banner"
                                checked={form.data.remove_banner}
                                onCheckedChange={(checked) => form.setData("remove_banner", checked === true)}
                            />
                            <Label htmlFor="remove_banner" className="text-sm font-normal">
                                Remove the current banner
                            </Label>
                        </div>
                    )}
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

                <div className="space-y-2">
                    <Label htmlFor="expire_at">Expiry date (optional)</Label>
                    <Input
                        id="expire_at"
                        type="datetime-local"
                        value={form.data.expire_at}
                        onChange={(event) => form.setData("expire_at", event.target.value)}
                    />
                    <p className="text-xs text-muted-foreground">
                        Leave empty to keep the gallery available indefinitely.
                    </p>
                    {form.errors.expire_at && <p className="text-xs text-red-500">{form.errors.expire_at}</p>}
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
