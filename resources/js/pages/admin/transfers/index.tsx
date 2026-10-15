import { useCallback, useMemo, useState } from "react"
import { router } from "@inertiajs/react"
import { CheckIcon } from "lucide-react"

import { AdminLayout, type SidebarData } from "@/components/admin-layout"
import { CopyButton } from "@/components/copy-button"
import { DataTable, dataColumn, type RowAction } from "@/components/data-table"
import { FormField } from "@/components/form-field"
import { ExpiryBadge, StateBadge } from "@/components/expiry-badges"
import { PartySummary } from "@/components/party-summary"
import { FolderPicker, type PickedFolder } from "@/components/transfers/folder-picker"
import { PageDropZone } from "@/components/transfers/page-drop-zone"
import { UploadProgress } from "@/components/transfers/upload-progress"
import { Button } from "@/components/ui/button"
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog"
import { Input } from "@/components/ui/input"
import { useUploadQueue } from "@/hooks/use-upload-queue"
import { formatShortDate, withOnlineState } from "@/lib/datetime"
import { analyseFolder, suggestName, type CollectedFolder } from "@/lib/folder-drop"
import { xsrfHeaders } from "@/lib/upload-queue"

type TransferRow = {
    id: number
    name: string
    display_name: string | null
    pictures_count: number
    pictures_size: number
    thumbnail_urls: (string | null)[]
    expire_at: string | null
    created_at: string | null
    public_url: string
    /** Derived from expire_at on the page, so the table can filter on it. */
    state: "Online" | "Expired"
}

type PageProps = SidebarData & { rows: Omit<TransferRow, "state">[] }

const columns = [
    dataColumn<TransferRow>("display_name", "Name", {
        cell: (transfer) => (
            <PartySummary
                title={transfer.display_name ?? ""}
                picturesCount={transfer.pictures_count}
                picturesSize={transfer.pictures_size}
                thumbnailUrls={transfer.thumbnail_urls}
                expired={transfer.state === "Expired"}
            />
        ),
    }),
    dataColumn<TransferRow>("state", "State", { cell: (transfer) => <StateBadge state={transfer.state} /> }),
    dataColumn<TransferRow>("created_at", "Created", { type: "datetime" }),
    dataColumn<TransferRow>("expire_at", "Expires", { cell: (transfer) => <ExpiryBadge expireAt={transfer.expire_at} /> }),
    dataColumn<TransferRow>("public_url", "Link", { cell: (transfer) => <CopyButton value={transfer.public_url} label="Copy link" size="sm" /> }),
]

/** "new" opens the creation dialog, a row opens the completion of that transfer. */
type DialogState = { transfer: TransferRow | null } | null

const EXPIRY_CHOICES = [
    { days: 0, label: "No expiry" },
    { days: 7, label: "7 days" },
    { days: 30, label: "30 days" },
    { days: 90, label: "90 days" },
]

const daysFromNow = (days: number) => new Date(Date.now() + days * 86_400_000).toISOString()

export default function TransfersIndex({ rows, ...sidebar }: PageProps) {
    const [dialog, setDialog] = useState<DialogState>(null)
    const [folder, setFolder] = useState<PickedFolder | null>(null)
    const [excluded, setExcluded] = useState<Set<string>>(new Set())
    const [name, setName] = useState("")
    const [expiryDays, setExpiryDays] = useState(0)
    const [createdUrl, setCreatedUrl] = useState<string | null>(null)
    const reloadRows = useCallback(() => router.reload({ only: ["rows"] }), [])
    const queue = useUploadQueue(reloadRows)
    const transfer = dialog?.transfer ?? null
    const tableRows = useMemo((): TransferRow[] => withOnlineState(rows), [rows])
    const selected = folder?.analysis.groups.filter((group) => !excluded.has(group.folder)).flatMap((group) => group.items) ?? []
    const canSend = selected.length > 0 && (transfer !== null || name.trim() !== "")

    const open = (state: DialogState) => {
        setFolder(null)
        setExcluded(new Set())
        setName("")
        setExpiryDays(0)
        setCreatedUrl(null)
        queue.reset()
        setDialog(state)
    }

    const openWithFolder = (collected: CollectedFolder) => {
        open({ transfer: null })
        setFolder({ root: collected.root, analysis: analyseFolder(collected.files) })
        setName(suggestName(collected.root))
    }

    // The folder names the transfer unless a name was typed by hand.
    const pick = ({ root, files }: CollectedFolder) => {
        if (name.trim() === "" || name === suggestName(folder?.root ?? null)) {
            setName(suggestName(root))
        }

        setFolder({ root, analysis: analyseFolder(files) })
        setExcluded(new Set())
    }

    const createTransfer = async (): Promise<string> => {
        const response = await fetch(route("admin.transfers.store"), {
            method: "POST",
            headers: { ...xsrfHeaders(), "Content-Type": "application/json" },
            body: JSON.stringify({ display_name: name, expire_at: expiryDays ? daysFromNow(expiryDays) : null }),
        })
        const body = await response.json().catch(() => null)

        if (!response.ok) {
            throw new Error(body?.message ?? `HTTP ${response.status}`)
        }

        setCreatedUrl(body.public_url)

        return body.upload_url
    }

    const send = () => queue.start(
        transfer ? async () => route("party.dashboard.pictures.store", transfer.name) : createTransfer,
        selected,
    )

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

    const note = queue.error
        ?? (!folder ? "Add a folder of pictures first." : !transfer && name.trim() === "" ? "Name the transfer." : null)

    return (
        <AdminLayout
            title="Administration / Transfers"
            sidebar={sidebar}
            description="Photo deliveries for clients without an online gallery. The client gets a plain download link."
            actions={<Button onClick={() => open({ transfer: null })}>New transfer</Button>}
        >
            <PageDropZone enabled={dialog === null} onPick={openWithFolder} />

            <DataTable
                data={tableRows}
                columns={columns}
                emptyMessage="No transfers yet."
                rowActions={rowActions}
            />

            <Dialog open={dialog !== null} onOpenChange={(isOpen) => !isOpen && queue.status !== "uploading" && setDialog(null)}>
                <DialogContent className="max-h-[88vh] gap-5 overflow-y-auto sm:max-w-[540px]">
                    <DialogHeader>
                        {createdUrl && (
                            <span className="mb-1 flex size-10 items-center justify-center rounded-full bg-success/14 text-success">
                                <CheckIcon className="size-4.5" />
                            </span>
                        )}
                        <DialogTitle>
                            {createdUrl ? "Transfer created" : transfer ? `Complete ${transfer.display_name}` : "New transfer"}
                        </DialogTitle>
                        <DialogDescription>
                            {createdUrl
                                ? queue.status === "uploading"
                                    ? "Send this link to the client. The remaining pictures keep uploading, the link already works."
                                    : queue.failed.length > 0
                                        ? "Some pictures did not go through: retry them before sending the link."
                                        : `Send this link to the client: they can download the ${queue.counts.created + queue.counts.skipped} photos at once.`
                                : transfer
                                    ? "Drop the same folder again: pictures already online are skipped."
                                    : "Name the transfer, then send the link to the client."}
                        </DialogDescription>
                    </DialogHeader>

                    {queue.status === "idle" ? (
                        <>
                            <FolderPicker
                                folder={folder}
                                excluded={excluded}
                                onExcludedChange={setExcluded}
                                onPick={pick}
                                onClear={() => setFolder(null)}
                            />

                            {!transfer && (
                                <>
                                    <FormField id="transfer-name" label="Transfer name">
                                        <Input
                                            id="transfer-name"
                                            value={name}
                                            placeholder="Mariage Lemoine"
                                            onChange={(event) => setName(event.target.value)}
                                            required
                                        />
                                    </FormField>

                                    <div className="space-y-2">
                                        <p className="text-sm font-medium">Available for</p>
                                        <div className="flex flex-wrap gap-1.5">
                                            {EXPIRY_CHOICES.map((choice) => (
                                                <Button
                                                    key={choice.days}
                                                    type="button"
                                                    size="sm"
                                                    variant={expiryDays === choice.days ? "default" : "outline"}
                                                    aria-pressed={expiryDays === choice.days}
                                                    className="rounded-full"
                                                    onClick={() => setExpiryDays(choice.days)}
                                                >
                                                    {choice.label}
                                                </Button>
                                            ))}
                                        </div>
                                        <p className="text-xs text-muted-foreground">
                                            {expiryDays
                                                ? `The link will expire on ${formatShortDate(daysFromNow(expiryDays), false)}.`
                                                : "The link stays available with no time limit."}
                                        </p>
                                    </div>
                                </>
                            )}

                            <div className="flex flex-wrap items-center justify-end gap-2">
                                <span className={`min-w-40 flex-1 text-xs ${queue.error ? "text-destructive" : "text-muted-foreground"}`}>
                                    {note}
                                </span>
                                <Button type="button" variant="outline" onClick={() => setDialog(null)}>
                                    Cancel
                                </Button>
                                <Button type="button" disabled={!canSend} onClick={() => void send()}>
                                    {transfer ? `Send ${selected.length} photos` : "Create transfer"}
                                </Button>
                            </div>
                        </>
                    ) : (
                        <>
                            {createdUrl && (
                                <div className="flex gap-1.5">
                                    <Input
                                        id="transfer-link"
                                        aria-label="Link to send"
                                        readOnly
                                        value={createdUrl}
                                        className="bg-background font-mono text-xs"
                                        onFocus={(event) => event.target.select()}
                                    />
                                    <CopyButton value={createdUrl} />
                                </div>
                            )}

                            <UploadProgress queue={queue} />

                            <div className="flex justify-end gap-2">
                                <Button type="button" variant="outline" disabled={queue.status === "uploading"} onClick={() => setDialog(null)}>
                                    Close
                                </Button>
                                {createdUrl && (
                                    <Button
                                        type="button"
                                        disabled={queue.status === "uploading"}
                                        onClick={() => {
                                            navigator.clipboard.writeText(createdUrl).catch(() => {}).finally(() => setDialog(null))
                                        }}
                                    >
                                        Copy link and close
                                    </Button>
                                )}
                            </div>
                        </>
                    )}
                </DialogContent>
            </Dialog>
        </AdminLayout>
    )
}
