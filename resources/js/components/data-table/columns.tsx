import { Link } from "@inertiajs/react"
import { type ColumnDef } from "@tanstack/react-table"
import { ArrowUpRightIcon } from "lucide-react"

import { DataTableColumnHeader } from "@/components/data-table/data-table-column-header"
import type { CrudColumn, CrudRow } from "@/components/data-table/types"
import { Badge } from "@/components/ui/badge"
import { Checkbox } from "@/components/ui/checkbox"

function badgeVariantFromMeta(meta: CrudColumn["badge"]): "default" | "secondary" | "destructive" | "outline" {
    if (meta === "danger") {
        return "destructive"
    }

    if (meta === "warning" || meta === "info") {
        return "secondary"
    }

    if (meta === "success") {
        return "default"
    }

    return "outline"
}

function EmptyCellState() {
    return (
        <span className="inline-flex items-center rounded-md border border-dashed px-2 py-0.5 text-xs text-muted-foreground">
            No value
        </span>
    )
}

function renderValue(column: CrudColumn, value: unknown): string | null {
    if (value === null || value === undefined || value === "") {
        return null
    }

    if (column.type === "boolean") {
        return value ? "Yes" : "No"
    }

    if (column.type === "date" || column.type === "datetime") {
        const date = new Date(String(value))

        if (Number.isNaN(date.getTime())) {
            return String(value)
        }

        if (column.type === "date") {
            return new Intl.DateTimeFormat(undefined, { dateStyle: "medium" }).format(date)
        }

        return new Intl.DateTimeFormat(undefined, {
            dateStyle: "medium",
            timeStyle: "short",
        }).format(date)
    }

    if (typeof value === "object") {
        return JSON.stringify(value)
    }

    return String(value)
}

function normalizeFacetValue(value: unknown): string {
    if (value === null || value === undefined || value === "") {
        return "__empty__"
    }

    if (typeof value === "boolean") {
        return value ? "true" : "false"
    }

    return String(value)
}

export function buildColumns(columnsMeta: CrudColumn[]): ColumnDef<CrudRow>[] {
    const selectableColumn: ColumnDef<CrudRow> = {
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

    const dataColumns: ColumnDef<CrudRow>[] = columnsMeta
        .map((columnMeta) => ({
            accessorKey: columnMeta.name,
            header: ({ column }) => <DataTableColumnHeader column={column} title={columnMeta.label} />,
            enableSorting: columnMeta.sortable,
            enableColumnFilter: columnMeta.name !== "id",
            meta: {
                label: columnMeta.label,
                type: columnMeta.type,
            },
            filterFn: (row, columnId, filterValues) => {
                if (!Array.isArray(filterValues) || filterValues.length === 0) {
                    return true
                }

                return filterValues.includes(normalizeFacetValue(row.getValue(columnId)))
            },
            cell: ({ row }) => {
                const rawValue = row.original[columnMeta.name]
                const displayValue = renderValue(columnMeta, rawValue)

                if (displayValue === null) {
                    return <EmptyCellState />
                }

                if (columnMeta.type === "badge") {
                    return <Badge variant={badgeVariantFromMeta(columnMeta.badge)}>{displayValue}</Badge>
                }

                if (columnMeta.type === "count_link") {
                    const count = typeof rawValue === "number" ? rawValue : parseInt(String(rawValue), 10)

                    return (
                        <div className="flex items-center gap-1.5">
                            {columnMeta.linkHref && (
                                <Badge asChild variant="secondary">
                                    <Link
                                        href={columnMeta.linkHref}
                                        className="flex items-center gap-1"
                                    >
                                        {Number.isNaN(count) ? 0 : count} <ArrowUpRightIcon data-icon="inline-end" />
                                    </Link>
                                </Badge>
                            )}
                        </div>
                    )
                }

                return <span className="max-w-[320px] truncate" title={displayValue}>{displayValue}</span>
            },
        }))

    return [selectableColumn, ...dataColumns]
}
