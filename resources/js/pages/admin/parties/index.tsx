import { FormEvent, useState } from "react"
import { Link, router, useForm } from "@inertiajs/react"
import { ArrowUpRightIcon, DownloadIcon, WandSparklesIcon } from "lucide-react"

import { AdminLayout, type SidebarData } from "@/components/admin-layout"
import { DataTable, dataColumn, type RowAction } from "@/components/data-table"
import { FormField } from "@/components/form-field"
import {
    PartyBulkCreateDialog,
    type PartySourceEvent,
} from "@/components/parties/bulk-create-dialog"
import { Badge } from "@/components/ui/badge"
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
import { fromDateTimeLocalValue, toDateTimeLocalValue } from "@/lib/datetime"

type PartyRow = {
    id: number
    name: string
    display_name: string | null
    description: string | null
    user_id: number | null
    owner: string | null
    pictures_count: number
    expire_at: string | null
    created_at: string | null
    banner_url: string | null
    host_edit_url: string
}

type PageProps = SidebarData & {
    rows: PartyRow[]
    users: { id: number, name: string }[]
    partyDomain: string
    upcomingSourceEvents?: PartySourceEvent[]
}

const columns = [
    dataColumn<PartyRow>("name", "Name"),
    dataColumn<PartyRow>("display_name", "Display name"),
    dataColumn<PartyRow>("owner", "Owner"),
    dataColumn<PartyRow>("pictures_count", "Pictures", {
        cell: (party) => (
            <Badge asChild variant="secondary">
                <Link
                    href={route("party.dashboard.pictures", party.name)}
                    className="flex items-center gap-1"
                >
                    {party.pictures_count} <ArrowUpRightIcon data-icon="inline-end" />
                </Link>
            </Badge>
        ),
    }),
    dataColumn<PartyRow>("expire_at", "Expires", { type: "datetime" }),
    dataColumn<PartyRow>("created_at", "Created", { type: "datetime" }),
]

/**
 * Null opens the dialog in creation mode, a row opens it on that party.
 */
type DialogState = { party: PartyRow | null } | null

export default function PartiesIndex({
    rows,
    users,
    partyDomain,
    upcomingSourceEvents,
    ...sidebar
}: PageProps) {
    const [dialog, setDialog] = useState<DialogState>(null)
    const [isBulkCreateModalOpen, setBulkCreateModalOpen] = useState(false)
    const [bannerPreview, setBannerPreview] = useState<string | null>(null)
    const [hostLinkCopied, setHostLinkCopied] = useState(false)
    const form = useForm({
        name: "",
        user_id: null as number | null,
        expire_at: "",
        display_name: "",
        description: "",
        banner: null as File | null,
        remove_banner: false,
    })
    const hostTokenForm = useForm({})
    const editedParty = dialog?.party ?? null
    const idPrefix = editedParty ? "edit-party" : "create-party"

    const openDialog = (party: PartyRow | null) => {
        form.clearErrors()
        form.setData({
            name: party?.name ?? "",
            user_id: party?.user_id ?? null,
            expire_at: toDateTimeLocalValue(party?.expire_at),
            display_name: party?.display_name ?? "",
            description: party?.description ?? "",
            banner: null,
            remove_banner: false,
        })
        setBannerPreview(null)
        setHostLinkCopied(false)
        setDialog({ party })
    }

    const submit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault()

        const options = { onSuccess: () => setDialog(null) }

        if (!editedParty) {
            form.transform(({ name, user_id, expire_at }) => ({
                name,
                user_id,
                expire_at: fromDateTimeLocalValue(expire_at),
            }))
            form.post(route("admin.parties.store"), options)

            return
        }

        // Inertia switches to FormData as soon as a file is present, and
        // multipart cannot carry a PUT, hence the spoofed method.
        form.transform((data) => ({
            ...data,
            _method: "put",
            expire_at: fromDateTimeLocalValue(data.expire_at),
        }))
        form.post(route("admin.parties.update", editedParty.name), options)
    }

    const deleteParty = (party: PartyRow) => {
        if (window.confirm("Delete this party?")) {
            router.delete(route("admin.parties.destroy", party.name))
        }
    }

    const regenerateHostLink = (party: PartyRow) => {
        if (window.confirm("Regenerate the link? The one already sent will stop working.")) {
            hostTokenForm.post(route("admin.parties.host-token", party.name), { onSuccess: () => setDialog(null) })
        }
    }

    const rowActions: RowAction<PartyRow>[] = [
        { label: "Edit", onClick: openDialog },
        {
            label: "Download poster",
            onClick: (party) => {
                window.location.href = route("admin.parties.poster", party.name)
            },
        },
        { label: "Delete", variant: "destructive", onClick: deleteParty },
    ]

    const bannerUrl = bannerPreview ?? editedParty?.banner_url

    return (
        <AdminLayout
            title="Administration / Parties"
            sidebar={sidebar}
            description="Events with an online gallery. Guests browse and download the photobooth pictures from the party link."
            actions={
                <>
                    <Button variant="outline" onClick={() => setBulkCreateModalOpen(true)}>
                        <WandSparklesIcon className="size-4" />
                        Create from events
                    </Button>
                    <Button onClick={() => openDialog(null)}>Create party</Button>
                </>
            }
        >
            <DataTable
                data={rows}
                columns={columns}
                emptyMessage="No parties found."
                onRowClick={openDialog}
                rowActions={rowActions}
            />

            <PartyBulkCreateDialog
                open={isBulkCreateModalOpen}
                onOpenChange={setBulkCreateModalOpen}
                partyDomain={partyDomain}
                upcomingSourceEvents={upcomingSourceEvents}
            />

            <Dialog open={dialog !== null} onOpenChange={(open) => !open && setDialog(null)}>
                <DialogContent className="max-h-[85vh] max-w-xl overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>{editedParty ? "Party details" : "Create party"}</DialogTitle>
                        <DialogDescription>
                            {editedParty ? "Update this party." : "Add a new party to the administration."}
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

                        {editedParty && (
                            <>
                                <FormField
                                    id="edit-party-display-name"
                                    label="Display name (optional)"
                                    hint="Title shown on the public gallery. Defaults to the party name."
                                    error={form.errors.display_name}
                                >
                                    <Input
                                        id="edit-party-display-name"
                                        value={form.data.display_name}
                                        onChange={(event) => form.setData("display_name", event.target.value)}
                                    />
                                </FormField>

                                <FormField
                                    id="edit-party-description"
                                    label="Description (optional)"
                                    hint="One line below the title, such as the date and the venue."
                                    error={form.errors.description}
                                >
                                    <Input
                                        id="edit-party-description"
                                        value={form.data.description}
                                        onChange={(event) => form.setData("description", event.target.value)}
                                    />
                                </FormField>

                                <FormField
                                    id="edit-party-banner"
                                    label="Banner (optional)"
                                    hint="Leave empty to keep the current banner. Without one, the gallery shows its striped placeholder."
                                    error={form.errors.banner}
                                >
                                    {bannerUrl && (
                                        <img
                                            id="edit-party-banner-preview"
                                            src={bannerUrl}
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
                                            form.setData("banner", file)
                                            setBannerPreview(file === null ? null : URL.createObjectURL(file))
                                        }}
                                    />
                                </FormField>

                                {editedParty.banner_url && (
                                    <div className="flex items-center gap-2">
                                        <Checkbox
                                            id="edit-party-remove-banner"
                                            checked={form.data.remove_banner}
                                            onCheckedChange={(checked) => form.setData("remove_banner", checked === true)}
                                        />
                                        <Label htmlFor="edit-party-remove-banner" className="text-sm font-normal">
                                            Remove the current banner
                                        </Label>
                                    </div>
                                )}
                            </>
                        )}

                        <FormField id={`${idPrefix}-owner`} label="Owner (optional)" error={form.errors.user_id}>
                            <Select
                                value={form.data.user_id === null ? "none" : String(form.data.user_id)}
                                onValueChange={(value) => form.setData("user_id", value === "none" ? null : Number(value))}
                            >
                                <SelectTrigger id={`${idPrefix}-owner`} className="h-10 w-full">
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
                        </FormField>

                        <FormField
                            id={`${idPrefix}-expire-at`}
                            label="Expiry date (optional)"
                            hint="Leave empty to keep the gallery available indefinitely."
                            error={form.errors.expire_at}
                        >
                            <Input
                                id={`${idPrefix}-expire-at`}
                                type="datetime-local"
                                value={form.data.expire_at}
                                onChange={(event) => form.setData("expire_at", event.target.value)}
                            />
                        </FormField>

                        {editedParty && (
                            <FormField
                                id="edit-party-host-link"
                                label="Host edit link"
                                hint="Send this to the host so they can edit the gallery title, description and cover without an account."
                            >
                                <div className="flex gap-2">
                                    <Input
                                        id="edit-party-host-link"
                                        readOnly
                                        value={editedParty.host_edit_url}
                                        onFocus={(event) => event.target.select()}
                                    />
                                    <Button
                                        type="button"
                                        variant="outline"
                                        onClick={() => {
                                            void navigator.clipboard.writeText(editedParty.host_edit_url)
                                            setHostLinkCopied(true)
                                        }}
                                    >
                                        {hostLinkCopied ? "Copied" : "Copy"}
                                    </Button>
                                </div>
                                <Button
                                    type="button"
                                    variant="ghost"
                                    className="h-auto px-0 text-xs text-destructive hover:bg-transparent"
                                    disabled={hostTokenForm.processing}
                                    onClick={() => regenerateHostLink(editedParty)}
                                >
                                    Regenerate the link
                                </Button>
                            </FormField>
                        )}

                        <DialogFooter>
                            {editedParty && (
                                <Button asChild type="button" variant="outline" className="sm:mr-auto">
                                    <a id="download-party-poster" href={route("admin.parties.poster", editedParty.name)} download>
                                        <DownloadIcon className="size-4" />
                                        Download poster
                                    </a>
                                </Button>
                            )}
                            <Button
                                type="button"
                                variant="outline"
                                onClick={() => setDialog(null)}
                                disabled={form.processing}
                            >
                                Cancel
                            </Button>
                            <Button type="submit" disabled={form.processing}>
                                {editedParty ? "Update party" : "Save party"}
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    )
}
