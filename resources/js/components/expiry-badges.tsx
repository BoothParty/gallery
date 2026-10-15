import { Badge } from "@/components/ui/badge"
import { expiryState, formatShortDate } from "@/lib/datetime"

const expiryVariants = {
    none: "neutral",
    active: "success",
    soon: "warning",
    expired: "faded",
} as const

export function StateBadge({ state }: { state: "Online" | "Expired" }) {
    return <Badge variant={state === "Online" ? "success" : "neutral"}>{state}</Badge>
}

/** The expiry date itself, tinted by how close it is. */
export function ExpiryBadge({ expireAt }: { expireAt: string | null }) {
    const state = expiryState(expireAt)
    const daysLeft = expireAt ? Math.ceil((new Date(expireAt).getTime() - Date.now()) / 86_400_000) : 0

    return (
        <Badge
            variant={expiryVariants[state]}
            data-expiry={state}
            title={state === "soon" ? `Expires in ${daysLeft} ${daysLeft === 1 ? "day" : "days"}` : undefined}
        >
            {expireAt ? formatShortDate(expireAt, false) : "No expiry"}
        </Badge>
    )
}
