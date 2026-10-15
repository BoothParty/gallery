/**
 * Helpers bridging the server's ISO 8601 timestamps and the value format of an
 * HTML datetime-local input, which carries no timezone and is therefore read
 * and written as the browser's local time. The admin datatable formats dates
 * with Intl in local time too, so both views stay consistent.
 */

const pad = (value: number): string => String(value).padStart(2, "0")

/** Convert an ISO 8601 timestamp to a "YYYY-MM-DDTHH:mm" local-time value. */
export function toDateTimeLocalValue(iso: string | null | undefined): string {
    if (!iso) {
        return ""
    }

    const date = new Date(iso)

    if (Number.isNaN(date.getTime())) {
        return ""
    }

    return (
        `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}` +
        `T${pad(date.getHours())}:${pad(date.getMinutes())}`
    )
}

/** Convert a local "YYYY-MM-DDTHH:mm" input value back to an ISO 8601 timestamp. */
export function fromDateTimeLocalValue(value: string): string | null {
    if (!value) {
        return null
    }

    const date = new Date(value)

    return Number.isNaN(date.getTime()) ? null : date.toISOString()
}

const DAY = 24 * 60 * 60 * 1000

/**
 * "14 oct. 2026", with the time when the instant is less than a day away,
 * so a transfer created this morning still reads "14 oct. 2026, 10:42".
 */
export function formatShortDate(iso: string, withRecentTime = true): string {
    const date = new Date(iso)

    if (Number.isNaN(date.getTime())) {
        return iso
    }

    const recent = withRecentTime && Math.abs(Date.now() - date.getTime()) < DAY

    return new Intl.DateTimeFormat("fr-FR", {
        day: "numeric",
        month: "short",
        year: "numeric",
        ...(recent && { hour: "2-digit", minute: "2-digit" }),
    }).format(date)
}

export type ExpiryState = "none" | "active" | "soon" | "expired"

/** Where an expiry date stands: soon means within the next seven days. */
export function expiryState(expireAt: string | null): ExpiryState {
    if (!expireAt) {
        return "none"
    }

    const left = new Date(expireAt).getTime() - Date.now()

    return left <= 0 ? "expired" : left < 7 * DAY ? "soon" : "active"
}

/** Adds the Online / Expired state the admin tables show and filter on. */
export function withOnlineState<TRow extends { expire_at: string | null }>(rows: TRow[]) {
    return rows.map((row) => ({
        ...row,
        state: expiryState(row.expire_at) === "expired" ? "Expired" as const : "Online" as const,
    }))
}
