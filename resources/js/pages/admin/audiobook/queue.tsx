import { Link } from "@inertiajs/react"
import { InboxIcon, MicIcon, UploadIcon } from "lucide-react"

import { AdminLayout, type SidebarParty } from "@/components/admin-layout"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"

type QueueRow = {
    id: number
    name: string
    route_key: string | null
    event_at: string | null
    client_email: string | null
    messages_count: number
    greeting: {
        status: string
        duration_seconds: number | null
        submitted_at: string | null
    } | null
}

type PageProps = {
    currentPartyId: number | null
    parties: SidebarParty[]
    searchableParties: SidebarParty[]
    audiobookPending: number
    awaitingValidation: QueueRow[]
    awaitingUpload: QueueRow[]
    withoutGreeting: QueueRow[]
}

function formatDuration(seconds: number | null): string | null {
    if (seconds === null) {
        return null
    }

    return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, "0")}`
}

function formatDay(iso: string | null): string | null {
    if (!iso) {
        return null
    }

    return new Date(iso).toLocaleDateString("fr-FR", {
        day: "numeric",
        month: "long",
        year: "numeric",
    })
}

function Section({
    title,
    icon,
    rows,
    empty,
    children,
}: {
    title: string
    icon: React.ReactNode
    rows: QueueRow[]
    empty: string
    children: (row: QueueRow) => React.ReactNode
}) {
    return (
        <section className="flex flex-col gap-3">
            <h3 className="flex items-center gap-2 text-sm font-medium tracking-wide text-muted-foreground uppercase">
                {icon}
                {title}
                {rows.length > 0 && <Badge variant="secondary">{rows.length}</Badge>}
            </h3>
            {rows.length === 0 ? (
                <p className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">{empty}</p>
            ) : (
                <ul className="divide-y rounded-lg border">
                    {rows.map((row) => (
                        <li
                            key={row.id}
                            className="flex flex-wrap items-center justify-between gap-3 px-4 py-3"
                            data-queue-row={row.id}
                        >
                            {children(row)}
                        </li>
                    ))}
                </ul>
            )}
        </section>
    )
}

function PartyLine({ row, detail }: { row: QueueRow; detail: string | null }) {
    return (
        <div className="flex min-w-0 flex-col">
            <span className="truncate font-medium">{row.name}</span>
            {detail && <span className="text-sm text-muted-foreground">{detail}</span>}
        </div>
    )
}

export default function AudiobookQueue({
    currentPartyId,
    parties,
    searchableParties,
    audiobookPending,
    awaitingValidation,
    awaitingUpload,
    withoutGreeting,
}: PageProps) {
    return (
        <AdminLayout
            title="Livre d'or"
            sidebar={{ currentPartyId, parties, searchableParties, audiobookPending }}
        >
            <div className="flex flex-col gap-8">
                <Section
                    title="À valider"
                    icon={<MicIcon className="size-4" />}
                    rows={awaitingValidation}
                    empty="Aucune annonce n'attend le studio."
                >
                    {(row) => (
                        <>
                            <PartyLine
                                row={row}
                                detail={[formatDuration(row.greeting?.duration_seconds ?? null), formatDay(row.greeting?.submitted_at ?? null) && `reçue le ${formatDay(row.greeting?.submitted_at ?? null)}`]
                                    .filter(Boolean)
                                    .join(" · ")}
                            />
                            <Button asChild size="sm">
                                <Link href={`/admin/livre-or/${row.id}`}>Écouter</Link>
                            </Button>
                        </>
                    )}
                </Section>

                <Section
                    title="À déposer"
                    icon={<UploadIcon className="size-4" />}
                    rows={awaitingUpload}
                    empty="Aucun événement passé n'attend ses messages."
                >
                    {(row) => (
                        <>
                            <PartyLine row={row} detail={formatDay(row.event_at) && `événement du ${formatDay(row.event_at)}`} />
                            <Button asChild size="sm" variant="outline">
                                <Link href={`/admin/livre-or/${row.id}`}>Déposer</Link>
                            </Button>
                        </>
                    )}
                </Section>

                <Section
                    title="Sans annonce"
                    icon={<InboxIcon className="size-4" />}
                    rows={withoutGreeting}
                    empty="Tous les clients à venir ont enregistré."
                >
                    {(row) => (
                        <>
                            <PartyLine
                                row={row}
                                detail={[formatDay(row.event_at), row.client_email].filter(Boolean).join(" · ")}
                            />
                            <Button asChild size="sm" variant="ghost">
                                <Link href={`/admin/livre-or/${row.id}`}>Ouvrir</Link>
                            </Button>
                        </>
                    )}
                </Section>
            </div>
        </AdminLayout>
    )
}
