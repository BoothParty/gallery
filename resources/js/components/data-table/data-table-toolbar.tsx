import * as React from "react"
import { type Table } from "@tanstack/react-table"
import { X } from "lucide-react"

import { normalizeFacetValue } from "@/components/data-table/columns"
import { DataTableFacetedFilter } from "@/components/data-table/data-table-faceted-filter"
import { DataTableViewOptions } from "@/components/data-table/data-table-view-options"
import { Button } from "@/components/ui/button"
import { Input } from "@/components/ui/input"

interface DataTableToolbarProps<TData> {
    table: Table<TData>
    toolbarAction?: React.ReactNode
}

function facetLabel(value: unknown): string {
    if (value === null || value === undefined || value === "") {
        return "No value"
    }

    return String(value)
}

export function DataTableToolbar<TData>({ table, toolbarAction }: DataTableToolbarProps<TData>) {
    const [showAllFacets, setShowAllFacets] = React.useState(false)
    const isFiltered = table.getState().columnFilters.length > 0 || Boolean(table.getState().globalFilter)
    const maxVisibleFacets = 3
    const facetedColumns = table
        .getAllLeafColumns()
        .map((column) => {
            if (!column.getCanFilter() || !column.columnDef.meta?.label) {
                return null
            }

            const filterValue = column.getFilterValue()
            const hasActiveFilter = Array.isArray(filterValue)
                ? filterValue.length > 0
                : filterValue !== undefined && filterValue !== null && filterValue !== ""

            const allOptions = Array.from(column.getFacetedUniqueValues().entries())
                .map(([rawValue, count]) => ({
                    value: normalizeFacetValue(rawValue),
                    label: facetLabel(rawValue),
                    count,
                }))
                .sort((a, b) => b.count - a.count)

            const hasRepeatedValue = allOptions.some((option) => option.count > 1)

            if (!hasRepeatedValue && !hasActiveFilter) {
                return null
            }

            const options = allOptions
                .slice(0, 15)

            if (options.length < 2 && !hasActiveFilter) {
                return null
            }

            return {
                key: column.id,
                title: column.columnDef.meta.label,
                column,
                options,
            }
        })
        .filter((entry): entry is NonNullable<typeof entry> => entry !== null)

    const hiddenFacetsCount = Math.max(0, facetedColumns.length - maxVisibleFacets)
    const visibleFacetedColumns = showAllFacets
        ? facetedColumns
        : facetedColumns.slice(0, maxVisibleFacets)

    return (
        <div className="flex items-center justify-between">
            <div className="flex min-w-0 flex-1 items-center gap-2">
                <Input
                    placeholder="Search all columns..."
                    value={(table.getState().globalFilter as string) ?? ""}
                    onChange={(event) => table.setGlobalFilter(event.target.value)}
                    className="h-8 w-[180px] bg-background lg:w-[280px]"
                />
                <div className="relative min-w-0 flex-1">
                    <div
                        className={showAllFacets
                            ? "flex items-center gap-2 overflow-x-auto pb-1 pr-1"
                            : "flex items-center gap-2 overflow-hidden"
                        }
                    >
                        {visibleFacetedColumns.map((facetedColumn) => (
                            <DataTableFacetedFilter
                                key={facetedColumn.key}
                                column={facetedColumn.column}
                                title={facetedColumn.title}
                                options={facetedColumn.options}
                            />
                        ))}
                        {hiddenFacetsCount > 0 && (
                            <Button
                                variant="ghost"
                                size="sm"
                                className="shrink-0"
                                onClick={() => setShowAllFacets((current) => !current)}
                            >
                                {showAllFacets ? "Collapse filters" : `Show ${hiddenFacetsCount} more`}
                            </Button>
                        )}
                    </div>

                    {!showAllFacets && hiddenFacetsCount > 0 && (
                        <div className="pointer-events-none absolute inset-y-0 right-0 w-14 bg-gradient-to-l from-background via-background/90 to-transparent" />
                    )}
                </div>
                {isFiltered && (
                    <Button
                        variant="ghost"
                        size="sm"
                        onClick={() => {
                            table.resetColumnFilters()
                            table.resetGlobalFilter()
                        }}
                    >
                        Reset
                        <X />
                    </Button>
                )}
            </div>
            <div className="flex items-center gap-2">
                <DataTableViewOptions table={table} />
                {toolbarAction}
            </div>
        </div>
    )
}
