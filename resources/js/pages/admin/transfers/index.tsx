import { useCallback, useMemo, useState } from "react"
import { router } from "@inertiajs/react"

import { AdminLayout, type SidebarData } from "@/components/admin-layout"
import { DataTable, dataColumn, type RowAction } from "@/components/data-table"
import { FormField } from "@/components/form-field"
import { ExpiryBadge, StateBadge } from "@/components/expiry-badges"
import { FolderUpload } from "@/components/transfers/folder-upload"
import { Button } from "@/components/ui/button"
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { fromDateTimeLocalValue, withOnlineState } from "@/lib/datetime"
import { xsrfHeaders } from "@/lib/upload-queue"

type TransferRow = {
    id: number
    name: string
    display_name: string | null
    pictures_count: number
    expire_at: string | null
    created_at: string | null
    public_url: string
    /** Derived from expire_at on the page, so the table can filter on it. */
    state: "Online" | "Expired"
}

type PageProps = SidebarData & { rows: Omit<TransferRow, "state">[] }

function CopyLinkButton({ url }: { url: string }) {
    const [copied, setCopied] = useState(false)

    return (
        <Button
            size="sm"
            variant="outline"
            onClick={(event) => {
                event.stopPropagation()
                void navigator.clipboard.writeText(url)
                setCopied(true)
            }}
        >
            {copied ? "Copied" : "Copy link"}
        </Button>
    )
}

const columns = [
    dataColumn<TransferRow>("display_name", "Name"),
    dataColumn<TransferRow>("state", "State", { cell: (transfer) => <StateBadge state={transfer.state} /> }),
    dataColumn<TransferRow>("pictures_count", "Pictures"),
    dataColumn<TransferRow>("created_at", "Created", { type: "datetime" }),
    dataColumn<TransferRow>("expire_at", "Expires", { cell: (transfer) => <ExpiryBadge expireAt={transfer.expire_at} /> }),
    dataColumn<TransferRow>("public_url", "Link", { cell: (transfer) => <CopyLinkButton url={transfer.public_url} /> }),
]

/** "new" opens the creation dialog, a row opens the completion of that transfer. */
type DialogState = { transfer: TransferRow | null } | null

export default function TransfersIndex({ rows, ...sidebar }: PageProps) {
    const [dialog, setDialog] = useState<DialogState>(null)
    const [busy, setBusy] = useState(false)
    const [name, setName] = useState("")
    const [expireAt, setExpireAt] = useState("")
    const [createdUrl, setCreatedUrl] = useState<string | null>(null)
    const transfer = dialog?.transfer ?? null
    const tableRows = useMemo((): TransferRow[] => withOnlineState(rows), [rows])

    const open = (state: DialogState) => {
        setName("")
        setExpireAt("")
        setCreatedUrl(null)
        setDialog(state)
    }

    const createTransfer = async (): Promise<string> => {
        const response = await fetch(route("admin.transfers.store"), {
            method: "POST",
            headers: { ...xsrfHeaders(), "Content-Type": "application/json" },
            body: JSON.stringify({ display_name: name, expire_at: fromDateTimeLocalValue(expireAt) }),
        })
        const body = await response.json().catch(() => null)

        if (!response.ok) {
            throw new Error(body?.message ?? `HTTP ${response.status}`)
        }

        setCreatedUrl(body.public_url)

        return body.upload_url
    }

    const reloadRows = useCallback(() => router.reload({ only: ["rows"] }), [])

    const rowActions: RowAction<TransferRow>[] = [
        { label: "Open gallery", onClick: (row) => window.open(row.public_url, "_blank", "noopener") },
        { label: "Complete", onClick: (row) => open({ transfer: row }) },
        {
            label: "Delete",
            variant: "destructive",
            onClick: (row) => {
                if (window.confirm("Delete this transfer and its pictures?")) {
                    router.delete(route("admin.transfers.destroy", row.name))
                }
            },
        },
    ]

    return (
        <AdminLayout
            title="Administration / Transfers"
            sidebar={sidebar}
            description="Photo deliveries for clients without an online gallery. The client gets a plain download link."
            actions={<Button onClick={() => open({ transfer: null })}>New transfer</Button>}
        >
            <DataTable
                data={tableRows}
                columns={columns}
                emptyMessage="No transfers yet."
                rowActions={rowActions}
            />

            <Dialog open={dialog !== null} onOpenChange={(isOpen) => !isOpen && !busy && setDialog(null)}>
                <DialogContent className="max-h-[85vh] max-w-xl overflow-y-auto">
                    <DialogHeader>
                        <DialogTitle>{transfer ? `Complete ${transfer.display_name}` : "New transfer"}</DialogTitle>
                        <DialogDescription>
                            {transfer
                                ? "Drop the same folder again: pictures already online are skipped."
                                : "Name the transfer, drop the photobooth folder, then send the link to the client."}
                        </DialogDescription>
                    </DialogHeader>

                    {!transfer && (
                        <div className="space-y-4">
                            <FormField id="transfer-name" label="Name">
                                <Input
                                    id="transfer-name"
                                    value={name}
                                    disabled={createdUrl !== null}
                                    onChange={(event) => setName(event.target.value)}
                                    required
                                />
                            </FormField>
                            <FormField
                                id="transfer-expire-at"
                                label="Expiry date (optional)"
                                hint="Leave empty to keep the gallery available indefinitely."
                            >
                                <Input
                                    id="transfer-expire-at"
                                    type="datetime-local"
                                    value={expireAt}
                                    disabled={createdUrl !== null}
                                    onChange={(event) => setExpireAt(event.target.value)}
                                />
                            </FormField>
                        </div>
                    )}

                    <FolderUpload
                        key={transfer?.id ?? "new"}
                        resolveUploadUrl={
                            transfer
                                ? async () => route("party.dashboard.pictures.store", transfer.name)
                                : createTransfer
                        }
                        disabled={!transfer && name.trim() === ""}
                        onBusyChange={setBusy}
                        onFinished={reloadRows}
                    />

                    {createdUrl && !busy && (
                        <FormField id="transfer-link" label="Link to send">
                            <div className="flex gap-2">
                                <Input id="transfer-link" readOnly value={createdUrl} onFocus={(event) => event.target.select()} />
                                <CopyLinkButton url={createdUrl} />
                            </div>
                        </FormField>
                    )}
                </DialogContent>
            </Dialog>
        </AdminLayout>
    )
}
