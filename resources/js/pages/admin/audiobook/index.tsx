import { useForm } from "@inertiajs/react"
import { useState, type FormEvent } from "react"
import { CheckIcon, LinkIcon, XIcon } from "lucide-react"

import { AdminLayout, type SidebarParty } from "@/components/admin-layout"
import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog"
import { Label } from "@/components/ui/label"
import { Textarea } from "@/components/ui/textarea"

type GreetingStatus = "draft" | "pending" | "validated" | "rejected"

type Greeting = {
    status: GreetingStatus
    url: string | null
    duration_seconds: number | null
    client_note: string | null
    rejection_reason: string | null
    submitted_at: string | null
    validated_at: string | null
    /** True while the MP3 job has not run: the studio hears the browser's take. */
    awaiting_conversion: boolean
}

type PageProps = {
    currentPartyId: number | null
    parties: SidebarParty[]
    searchableParties: SidebarParty[]
    party: {
        id: number
        name: string
        client_email: string | null
        client_url: string
    }
    greeting: Greeting | null
}

const STATUS_LABEL: Record<GreetingStatus, string> = {
    draft: "Brouillon du client",
    pending: "À valider",
    validated: "Validée",
    rejected: "Refusée",
}

const STATUS_VARIANT: Record<GreetingStatus, "default" | "secondary" | "destructive" | "outline"> = {
    draft: "outline",
    pending: "default",
    validated: "secondary",
    rejected: "destructive",
}

function formatDuration(seconds: number | null): string {
    if (seconds === null) {
        return "—"
    }

    return `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, "0")}`
}

function formatMoment(iso: string | null): string | null {
    if (!iso) {
        return null
    }

    return new Date(iso).toLocaleString("fr-FR", {
        day: "numeric",
        month: "long",
        hour: "2-digit",
        minute: "2-digit",
    })
}

export default function AudiobookIndex({
    currentPartyId,
    parties,
    searchableParties,
    party,
    greeting,
}: PageProps) {
    const [isRejectOpen, setRejectOpen] = useState(false)
    const [linkCopied, setLinkCopied] = useState(false)

    const approveForm = useForm({})
    const rejectForm = useForm({ rejection_reason: "" })

    const partySegment = encodeURIComponent(searchableParties.find((p) => p.id === party.id)?.name ?? "")
    const basePath = `/${partySegment}/dashboard/livre-or`

    const approve = () => approveForm.post(`${basePath}/annonce/valider`, { preserveScroll: true })

    const reject = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault()

        rejectForm.post(`${basePath}/annonce/refuser`, {
            preserveScroll: true,
            onSuccess: () => {
                rejectForm.reset()
                setRejectOpen(false)
            },
        })
    }

    const copyClientLink = async () => {
        await navigator.clipboard.writeText(party.client_url)
        setLinkCopied(true)
        window.setTimeout(() => setLinkCopied(false), 2000)
    }

    return (
        <AdminLayout
            title="Party / Livre d'or"
            sidebar={{ currentPartyId, parties, searchableParties }}
        >
            <div className="flex flex-col gap-4">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <div className="flex flex-col gap-1">
                        <h2 className="text-xl font-semibold">{party.name}</h2>
                        <p className="text-sm text-muted-foreground">
                            {party.client_email ?? "Aucune adresse client renseignée"}
                        </p>
                    </div>
                    <Button variant="outline" size="sm" onClick={copyClientLink}>
                        <LinkIcon />
                        {linkCopied ? "Lien copié" : "Copier le lien client"}
                    </Button>
                </div>

                {greeting === null ? (
                    <div className="rounded-xl border border-dashed bg-muted/30 p-8 text-center">
                        <h3 className="text-lg font-semibold">Aucune annonce</h3>
                        <p className="mt-2 text-sm text-muted-foreground">
                            Le client n'a encore rien enregistré. Envoyez-lui son lien s'il ne l'a pas reçu.
                        </p>
                    </div>
                ) : (
                    <div className="flex flex-col gap-4 rounded-xl border p-5">
                        <div className="flex flex-wrap items-center gap-3">
                            <Badge variant={STATUS_VARIANT[greeting.status]}>
                                {STATUS_LABEL[greeting.status]}
                            </Badge>
                            <span className="text-sm text-muted-foreground">
                                Durée {formatDuration(greeting.duration_seconds)}
                            </span>
                            {greeting.submitted_at && (
                                <span className="text-sm text-muted-foreground">
                                    Reçue le {formatMoment(greeting.submitted_at)}
                                </span>
                            )}
                            {greeting.awaiting_conversion && (
                                <Badge variant="outline">Conversion MP3 en attente</Badge>
                            )}
                        </div>

                        {greeting.url ? (
                            <audio controls src={greeting.url} className="w-full max-w-lg">
                                Votre navigateur ne sait pas lire cet enregistrement.
                            </audio>
                        ) : (
                            <p className="text-sm text-muted-foreground">Aucun fichier à écouter.</p>
                        )}

                        {greeting.client_note && (
                            <div className="flex flex-col gap-1 rounded-lg bg-muted/40 p-3">
                                <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    Note du client
                                </span>
                                <p className="text-sm">{greeting.client_note}</p>
                            </div>
                        )}

                        {greeting.status === "rejected" && greeting.rejection_reason && (
                            <div className="flex flex-col gap-1 rounded-lg border-l-2 border-destructive bg-muted/40 p-3">
                                <span className="text-xs font-medium tracking-wide text-muted-foreground uppercase">
                                    Motif du refus
                                </span>
                                <p className="text-sm">{greeting.rejection_reason}</p>
                            </div>
                        )}

                        {greeting.status !== "draft" && (
                            <div className="flex flex-wrap gap-2">
                                <Button onClick={approve} disabled={approveForm.processing}>
                                    <CheckIcon />
                                    Valider
                                </Button>
                                <Button variant="outline" onClick={() => setRejectOpen(true)}>
                                    <XIcon />
                                    Refuser…
                                </Button>
                            </div>
                        )}

                        {greeting.status === "draft" && (
                            <p className="text-sm text-muted-foreground">
                                Le client n'a pas encore envoyé cette prise pour validation.
                            </p>
                        )}
                    </div>
                )}
            </div>

            <Dialog open={isRejectOpen} onOpenChange={setRejectOpen}>
                <DialogContent>
                    <form onSubmit={reject}>
                        <DialogHeader>
                            <DialogTitle>Refuser l'annonce</DialogTitle>
                            <DialogDescription>
                                Le motif part tel quel au client et s'affiche dans son espace. C'est ce qui lui
                                dit quoi refaire.
                            </DialogDescription>
                        </DialogHeader>
                        <div className="my-4 flex flex-col gap-2">
                            <Label htmlFor="rejection_reason">Motif</Label>
                            <Textarea
                                id="rejection_reason"
                                value={rejectForm.data.rejection_reason}
                                onChange={(event) =>
                                    rejectForm.setData("rejection_reason", event.target.value)
                                }
                                maxLength={500}
                                rows={3}
                                placeholder="Le son est saturé, reculez du micro."
                            />
                            {rejectForm.errors.rejection_reason && (
                                <p className="text-sm text-destructive">
                                    {rejectForm.errors.rejection_reason}
                                </p>
                            )}
                        </div>
                        <DialogFooter>
                            <Button type="button" variant="outline" onClick={() => setRejectOpen(false)}>
                                Annuler
                            </Button>
                            <Button type="submit" variant="destructive" disabled={rejectForm.processing}>
                                Refuser
                            </Button>
                        </DialogFooter>
                    </form>
                </DialogContent>
            </Dialog>
        </AdminLayout>
    )
}
