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
