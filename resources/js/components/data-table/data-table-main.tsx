import * as React from "react"
import {
    type ColumnDef,
    flexRender,
    getCoreRowModel,
    getFilteredRowModel,
    getPaginationRowModel,
    getSortedRowModel,
    useReactTable,
    getFacetedRowModel,
    getFacetedUniqueValues,
    type ColumnFiltersState,
    type SortingState,
    type VisibilityState,
} from "@tanstack/react-table"

import { selectColumn } from "@/components/data-table/columns"
import { DataTablePagination } from "@/components/data-table/data-table-pagination"
import { DataTableRowActions } from "@/components/data-table/data-table-row-actions"
import { DataTableToolbar } from "@/components/data-table/data-table-toolbar"
import type { RowAction } from "@/components/data-table/types"
import { cn } from "@/lib/utils"
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table"

interface DataTableProps<TRow> {
    data: TRow[]
    columns: ColumnDef<TRow>[]
    emptyMessage: string
    toolbarAction?: React.ReactNode
    onRowClick?: (row: TRow) => void
    rowActions?: RowAction<TRow>[]
}

export function DataTable<TRow>({
    data,
    columns: dataColumns,
    emptyMessage,
    toolbarAction,
    onRowClick,
    rowActions = [],
}: DataTableProps<TRow>) {
    const [rowSelection, setRowSelection] = React.useState({})
    const [columnVisibility, setColumnVisibility] = React.useState<VisibilityState>({})
    const [columnFilters, setColumnFilters] = React.useState<ColumnFiltersState>([])
    const [sorting, setSorting] = React.useState<SortingState>([])
    const [globalFilter, setGlobalFilter] = React.useState("")
    const columns = React.useMemo<ColumnDef<TRow>[]>(() => {
        const baseColumns = [selectColumn<TRow>(), ...dataColumns]

        if (rowActions.length === 0) {
            return baseColumns
        }

        return [
            ...baseColumns,
            {
                id: "actions",
                header: () => null,
                enableSorting: false,
                enableHiding: false,
                cell: ({ row }) => (
                    <div className="flex justify-end">
                        <DataTableRowActions row={row} actions={rowActions} />
                    </div>
                ),
            },
        ]
    }, [dataColumns, rowActions])

    const table = useReactTable({
        data,
        columns,
        state: {
            sorting,
            columnVisibility,
            rowSelection,
            columnFilters,
            globalFilter,
        },
        initialState: {
            pagination: {
                pageSize: 10,
            },
        },
        enableRowSelection: true,
        onRowSelectionChange: setRowSelection,
        onSortingChange: setSorting,
        onColumnFiltersChange: setColumnFilters,
        onColumnVisibilityChange: setColumnVisibility,
        onGlobalFilterChange: setGlobalFilter,
        globalFilterFn: "includesString",
        getCoreRowModel: getCoreRowModel(),
        getFilteredRowModel: getFilteredRowModel(),
        getFacetedRowModel: getFacetedRowModel(),
        getFacetedUniqueValues: getFacetedUniqueValues(),
        getPaginationRowModel: getPaginationRowModel(),
        getSortedRowModel: getSortedRowModel(),
    })

    return (
        <div className="overflow-hidden rounded-md border bg-card">
            <div className="border-b px-3.5 py-3">
                <DataTableToolbar table={table} toolbarAction={toolbarAction} />
            </div>
            <Table>
                <TableHeader>
                    {table.getHeaderGroups().map((headerGroup) => (
                        <TableRow key={headerGroup.id}>
                            {headerGroup.headers.map((header) => (
                                <TableHead
                                    key={header.id}
                                    colSpan={header.colSpan}
                                    className={cn(
                                        "h-auto bg-background px-3.5 py-2.5 text-[11px] font-medium tracking-[.1em] text-muted-foreground uppercase",
                                        header.column.id === "actions" && "w-0 px-6",
                                    )}
                                >
                                    {header.isPlaceholder
                                        ? null
                                        : flexRender(header.column.columnDef.header, header.getContext())}
                                </TableHead>
                            ))}
                        </TableRow>
                    ))}
                </TableHeader>
                <TableBody>
                    {table.getRowModel().rows?.length ? (
                        table.getRowModel().rows.map((row) => (
                            <TableRow
                                key={row.id}
                                data-state={row.getIsSelected() && "selected"}
                                className={cn("border-border/70 hover:bg-background", onRowClick && "cursor-pointer")}
                                onClick={(event) => {
                                    const target = event.target as HTMLElement

                                    if (!onRowClick || target.closest("button, a, input, [role='menuitem'], [data-slot='checkbox']")) {
                                        return
                                    }

                                    onRowClick(row.original)
                                }}
                            >
                                {row.getVisibleCells().map((cell) => (
                                    <TableCell
                                        key={cell.id}
                                        className={cn("px-3.5 py-2.5", cell.column.id === "actions" && "w-0 px-1")}
                                    >
                                        {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                    </TableCell>
                                ))}
                            </TableRow>
                        ))
                    ) : (
                        <TableRow>
                            <TableCell colSpan={columns.length} className="h-24 text-center text-muted-foreground">
                                {emptyMessage}
                            </TableCell>
                        </TableRow>
                    )}
                </TableBody>
            </Table>
            <div className="border-t px-4 py-3">
                <DataTablePagination table={table} />
            </div>
        </div>
    )
}
