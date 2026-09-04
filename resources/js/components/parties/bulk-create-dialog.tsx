import { FormEvent, useEffect, useState } from "react"
import { Deferred, router, useForm } from "@inertiajs/react"
import { RefreshCwIcon } from "lucide-react"

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
import {
    InputGroup,
    InputGroupAddon,
    InputGroupInput,
    InputGroupText,
} from "@/components/ui/input-group"
import { Skeleton } from "@/components/ui/skeleton"
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table"

export type PartySourceEvent = {
    id: string
    name: string
    starts_at: string | null
    gallery_name: string | null
    source_key: string
    source_label: string
    already_imported: boolean
}

interface PartyBulkCreateDialogProps {
    open: boolean
    onOpenChange: (open: boolean) => void
    partyDomain: string
    upcomingSourceEvents?: PartySourceEvent[]
}

type EventSelection = {
    selected: boolean
    name: string
}

/**
 * Turn an event name into a usable subdomain, the party name being the
 * subdomain part. The input stays free-form, this is only a starting point.
 */
const toPartyName = (eventName: string): string =>
    eventName
        .normalize("NFD")
        .replace(/[\u0300-\u036f]/g, "")
        .toLowerCase()
        .replace(/[^a-z0-9]+/g, "-")
        .replace(/^-+|-+$/g, "")

/**
 * The provider carries the gallery name filled by the team; the slugified event
 * name is only the fallback when that column is empty.
 */
const initialPartyName = (event: PartySourceEvent): string =>
    event.gallery_name ?? toPartyName(event.name)

const formatEventDate = (startsAt: string | null): string | null => {
    if (!startsAt) {
        return null
    }

    const date = new Date(startsAt)

    if (Number.isNaN(date.getTime())) {
        return null
    }

    return date.toLocaleDateString(undefined, {
        day: "2-digit",
        month: "long",
        year: "numeric",
    })
}

export function PartyBulkCreateDialog({
    open,
    onOpenChange,
    partyDomain,
    upcomingSourceEvents,
}: PartyBulkCreateDialogProps) {
    const events = upcomingSourceEvents ?? []
    const [selections, setSelections] = useState<Record<string, EventSelection>>({})
    const [submittedEventIds, setSubmittedEventIds] = useState<string[]>([])
    const [isRefreshing, setRefreshing] = useState(false)
    const form = useForm<{ parties: { name: string; source_event_id: string }[] }>({
        parties: [],
    })

    useEffect(() => {
        setSelections((current) =>
            Object.fromEntries(
                events.map((event) => [
                    event.id,
                    current[event.id] ?? { selected: false, name: initialPartyName(event) },
                ]),
            ),
        )
    }, [upcomingSourceEvents])

    const sourceLabels = new Set(events.map((event) => event.source_label))
    const selectableEvents = events.filter((event) => !event.already_imported)
    const selectedEvents = selectableEvents.filter((event) => selections[event.id]?.selected)
    const allSelected =
        selectableEvents.length > 0 && selectedEvents.length === selectableEvents.length
    const someSelected = selectedEvents.length > 0

    const toggleAll = () => {
        setSelections((current) =>
            Object.fromEntries(
                events.map((event) => [
                    event.id,
                    {
                        selected: !allSelected && !event.already_imported,
                        name: current[event.id]?.name ?? initialPartyName(event),
                    },
                ]),
            ),
        )
    }

    /**
     * Bypass the cached provider events; the deferred prop is reloaded on its own.
     */
    const refresh = () => {
        router.reload({
            only: ["upcomingSourceEvents"],
            data: { refresh: 1 },
            preserveUrl: true,
            onStart: () => setRefreshing(true),
            onFinish: () => setRefreshing(false),
        })
    }

    const updateSelection = (eventId: string, changes: Partial<EventSelection>) => {
        setSelections((current) => ({
            ...current,
            [eventId]: { ...current[eventId], ...changes },
        }))
    }

    /**
     * Validation errors come back indexed by the position in the submitted
     * payload, which only holds the selected events.
     */
    const errorForEvent = (eventId: string): string | undefined => {
        const index = submittedEventIds.indexOf(eventId)

        if (index === -1) {
            return undefined
        }

        return (form.errors as Record<string, string>)[`parties.${index}.name`]
    }

    const close = () => {
        onOpenChange(false)
    }

    const submit = (submitEvent: FormEvent<HTMLFormElement>) => {
        submitEvent.preventDefault()

        const parties = selectedEvents.map((event) => ({
            name: selections[event.id]?.name ?? "",
            source_event_id: event.id,
        }))

        setSubmittedEventIds(selectedEvents.map((event) => event.id))
        form.transform(() => ({ parties }))
        form.post("/admin/parties/bulk", {
            preserveScroll: true,
            onSuccess: () => {
                setSelections({})
                setSubmittedEventIds([])
                form.clearErrors()
                onOpenChange(false)
            },
        })
    }

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="sm:max-w-3xl">
                <DialogHeader>
                    <DialogTitle>Create parties from upcoming events</DialogTitle>
                    <DialogDescription>
                        Pick the upcoming events to turn into parties, then adjust the name each
                        gallery will be published under.
                    </DialogDescription>
                </DialogHeader>

                <form onSubmit={submit} className="mt-4 space-y-4">
                    <Deferred
                        data="upcomingSourceEvents"
                        fallback={
                            <div className="space-y-2" aria-busy="true">
                                <Skeleton className="h-10 w-full" />
                                <Skeleton className="h-12 w-full" />
                                <Skeleton className="h-12 w-full" />
                                <Skeleton className="h-12 w-full" />
                            </div>
                        }
                    >
                        {events.length === 0 ? (
                            <p className="rounded-xl border bg-muted/30 p-6 text-center text-sm text-muted-foreground">
                                No upcoming event found. Once an event is planned, it will show up
                                here.
                            </p>
                        ) : (
                            <div className="rounded-xl border">
                                <Table>
                                    <TableHeader>
                                        <TableRow>
                                            <TableHead className="w-10 pl-3">
                                                <Checkbox
                                                    checked={
                                                        allSelected ||
                                                        (someSelected ? "indeterminate" : false)
                                                    }
                                                    onCheckedChange={toggleAll}
                                                    aria-label="Select all events"
                                                    id="bulk-create-select-all"
                                                />
                                            </TableHead>
                                            <TableHead>Event</TableHead>
                                            <TableHead className="w-[46%]">Party name</TableHead>
                                        </TableRow>
                                    </TableHeader>
                                    <TableBody>
                                        {events.map((event, index) => {
                                            const eventDate = formatEventDate(event.starts_at)
                                            const error = errorForEvent(event.id)

                                            return (
                                                <TableRow key={event.id}>
                                                    <TableCell className="pl-3 align-top pt-4">
                                                        <Checkbox
                                                            checked={
                                                                selections[event.id]?.selected ??
                                                                false
                                                            }
                                                            onCheckedChange={(checked) =>
                                                                updateSelection(event.id, {
                                                                    selected: checked === true,
                                                                })
                                                            }
                                                            disabled={event.already_imported}
                                                            aria-label={`Select ${event.name}`}
                                                            id={`bulk-create-select-${index}`}
                                                        />
                                                    </TableCell>
                                                    <TableCell className="align-top whitespace-normal">
                                                        <div className="flex items-center gap-2">
                                                            <span className="font-medium">
                                                                {event.name}
                                                            </span>
                                                            {event.already_imported && (
                                                                <Badge variant="secondary">
                                                                    Already created
                                                                </Badge>
                                                            )}
                                                            {sourceLabels.size > 1 && (
                                                                <Badge variant="outline">
                                                                    {event.source_label}
                                                                </Badge>
                                                            )}
                                                        </div>
                                                        <p className="text-xs text-muted-foreground">
                                                            {eventDate ?? "No date"}
                                                        </p>
                                                    </TableCell>
                                                    <TableCell className="align-top">
                                                        <InputGroup>
                                                            <InputGroupInput
                                                                id={`bulk-create-party-name-${index}`}
                                                                value={
                                                                    selections[event.id]?.name ?? ""
                                                                }
                                                                onChange={(changeEvent) =>
                                                                    updateSelection(event.id, {
                                                                        name: changeEvent.target
                                                                            .value,
                                                                    })
                                                                }
                                                                aria-invalid={
                                                                    error ? true : undefined
                                                                }
                                                                disabled={event.already_imported}
                                                            />
                                                            <InputGroupAddon align="inline-end">
                                                                <InputGroupText>
                                                                    .{partyDomain}
                                                                </InputGroupText>
                                                            </InputGroupAddon>
                                                        </InputGroup>
                                                        {error && (
                                                            <p className="mt-1 text-xs text-red-500">
                                                                {error}
                                                            </p>
                                                        )}
                                                    </TableCell>
                                                </TableRow>
                                            )
                                        })}
                                    </TableBody>
                                </Table>
                            </div>
                        )}
                    </Deferred>

                    {form.errors.parties && (
                        <p className="text-xs text-red-500">{form.errors.parties}</p>
                    )}

                    <DialogFooter>
                        <Button
                            type="button"
                            variant="outline"
                            size="sm"
                            className="my-auto me-auto"
                            onClick={refresh}
                            disabled={isRefreshing}
                            id="bulk-create-refresh"
                        >
                            <RefreshCwIcon className={isRefreshing ? "animate-spin" : undefined} />
                            {isRefreshing ? "Refreshing…" : "Refresh"}
                        </Button>
                        <Button
                            type="button"
                            variant="outline"
                            onClick={close}
                            disabled={form.processing}
                        >
                            Cancel
                        </Button>
                        <Button
                            type="submit"
                            disabled={form.processing || selectedEvents.length === 0}
                        >
                            {`Create ${selectedEvents.length} part${selectedEvents.length === 1 ? "y" : "ies"
                                }`}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>
    )
}
