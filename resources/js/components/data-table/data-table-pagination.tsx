import { type Table } from "@tanstack/react-table"

import { Button } from "@/components/ui/button"

interface DataTablePaginationProps<TData> {
    table: Table<TData>
}

/**
 * "1–10 of 42" and Previous / Next, or the selection count while rows are ticked.
 */
export function DataTablePagination<TData>({ table }: DataTablePaginationProps<TData>) {
    const { pageIndex, pageSize } = table.getState().pagination
    const total = table.getFilteredRowModel().rows.length
    const selected = table.getFilteredSelectedRowModel().rows.length
    const first = Math.min(total, pageIndex * pageSize + 1)
    const last = Math.min(total, (pageIndex + 1) * pageSize)

    return (
        <div className="flex flex-wrap items-center justify-between gap-2.5 text-[13px] text-muted-foreground">
            <span>
                {selected > 0 ? `${selected} of ${total} selected` : `${first}–${last} of ${total}`}
            </span>
            <div className="flex items-center gap-1.5">
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => table.previousPage()}
                    disabled={!table.getCanPreviousPage()}
                >
                    Previous
                </Button>
                <Button
                    variant="outline"
                    size="sm"
                    onClick={() => table.nextPage()}
                    disabled={!table.getCanNextPage()}
                >
                    Next
                </Button>
            </div>
        </div>
    )
}
