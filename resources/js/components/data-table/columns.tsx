import * as React from "react"
import { type ColumnDef } from "@tanstack/react-table"

import { DataTableColumnHeader } from "@/components/data-table/data-table-column-header"
import { Checkbox } from "@/components/ui/checkbox"

type ValueType = "text" | "datetime"

function EmptyCellState() {
    return (
        <span className="inline-flex items-center rounded-md border border-dashed px-2 py-0.5 text-xs text-muted-foreground">
            No value
        </span>
    )
}

function renderValue(type: ValueType, value: unknown): string | null {
    if (value === null || value === undefined || value === "") {
        return null
    }

    if (type === "datetime") {
        const date = new Date(String(value))

        if (Number.isNaN(date.getTime())) {
            return String(value)
        }

        return new Intl.DateTimeFormat(undefined, {
            dateStyle: "medium",
            timeStyle: "short",
        }).format(date)
    }

    return String(value)
}

export function normalizeFacetValue(value: unknown): string {
    if (value === null || value === undefined || value === "") {
        return "__empty__"
    }

    return String(value)
}

/**
 * A sortable, filterable column bound to one key of the row. The cell shows the
 * formatted value unless the page renders its own.
 */
export function dataColumn<TRow>(
    key: keyof TRow & string,
    label: string,
    options: { type?: ValueType, cell?: (row: TRow) => React.ReactNode } = {},
): ColumnDef<TRow> {
    const type = options.type ?? "text"

    return {
        accessorKey: key,
        header: ({ column }) => <DataTableColumnHeader column={column} title={label} />,
        meta: { label },
        filterFn: (row, columnId, filterValues) => {
            if (!Array.isArray(filterValues) || filterValues.length === 0) {
                return true
            }

            return filterValues.includes(normalizeFacetValue(row.getValue(columnId)))
        },
        cell: ({ row }) => {
            if (options.cell) {
                return options.cell(row.original)
            }

            const displayValue = renderValue(type, row.original[key])

            if (displayValue === null) {
                return <EmptyCellState />
            }

            return <span className="max-w-[320px] truncate" title={displayValue}>{displayValue}</span>
        },
    }
}

export function selectColumn<TRow>(): ColumnDef<TRow> {
    return {
        id: "select",
        header: ({ table }) => (
            <Checkbox
                checked={table.getIsAllPageRowsSelected() || (table.getIsSomePageRowsSelected() && "indeterminate")}
                onCheckedChange={(value) => table.toggleAllPageRowsSelected(!!value)}
                aria-label="Select all"
                className="translate-y-[2px]"
            />
        ),
        cell: ({ row }) => (
            <Checkbox
                checked={row.getIsSelected()}
                onCheckedChange={(value) => row.toggleSelected(!!value)}
                aria-label="Select row"
                className="translate-y-[2px]"
            />
        ),
        enableSorting: false,
        enableHiding: false,
    }
}
