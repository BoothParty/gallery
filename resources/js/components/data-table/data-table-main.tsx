"use client"

import * as React from "react"
import { router } from "@inertiajs/react"
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

import { buildColumns } from "@/components/data-table/columns"
import { DataTablePagination } from "@/components/data-table/data-table-pagination"
import { DataTableRowActions } from "@/components/data-table/data-table-row-actions"
import { DataTableToolbar } from "@/components/data-table/data-table-toolbar"
import type { CrudRow, CrudRowAction, CrudTableData } from "@/components/data-table/types"
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from "@/components/ui/table"

interface DataTableMainProps {
    tableData: CrudTableData
    rowEditHrefBase?: string
    toolbarAction?: React.ReactNode
    onRowClick?: (row: CrudRow) => void
    rowActions?: CrudRowAction[]
}

export function DataTableMain({ tableData, rowEditHrefBase, toolbarAction, onRowClick, rowActions = [] }: DataTableMainProps) {
    const defaultColumnVisibility = React.useMemo<VisibilityState>(() => {
        return tableData.columns.reduce<VisibilityState>((visibility, column) => {
            visibility[column.name] = column.visible

            return visibility
        }, {})
    }, [tableData.columns])

    const [rowSelection, setRowSelection] = React.useState({})
    const [columnVisibility, setColumnVisibility] = React.useState<VisibilityState>(defaultColumnVisibility)
    const [columnFilters, setColumnFilters] = React.useState<ColumnFiltersState>([])
    const [sorting, setSorting] = React.useState<SortingState>([])
    const [globalFilter, setGlobalFilter] = React.useState("")
    const columns = React.useMemo<ColumnDef<CrudRow>[]>(() => {
        const baseColumns = buildColumns(tableData.columns)

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
    }, [tableData.columns, rowActions])

    React.useEffect(() => {
        setColumnVisibility(defaultColumnVisibility)
    }, [defaultColumnVisibility])

    const table = useReactTable({
        data: tableData.rows,
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
        <div className="flex flex-col gap-4 p-4">
            <DataTableToolbar table={table} columnsMeta={tableData.columns} toolbarAction={toolbarAction} />
            <div className="overflow-hidden rounded-md border">
                <Table>
                    <TableHeader>
                        {table.getHeaderGroups().map((headerGroup) => (
                            <TableRow key={headerGroup.id}>
                                {headerGroup.headers.map((header) => (
                                    <TableHead
                                        key={header.id}
                                        colSpan={header.colSpan}
                                        className={header.column.id === "actions" ? "w-0 px-6" : undefined}
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
                                    className={rowEditHrefBase || onRowClick ? "cursor-pointer" : undefined}
                                    onClick={(event) => {
                                        if (!rowEditHrefBase && !onRowClick) {
                                            return
                                        }

                                        const target = event.target as HTMLElement

                                        if (target.closest("button, a, input, [role='menuitem'], [data-slot='checkbox']")) {
                                            return
                                        }

                                        if (onRowClick) {
                                            onRowClick(row.original)

                                            return
                                        }

                                        const rowId = row.original.id

                                        if (typeof rowId === "number" || typeof rowId === "string") {
                                            router.visit(`${rowEditHrefBase}/${rowId}/edit`)
                                        }
                                    }}
                                >
                                    {row.getVisibleCells().map((cell) => (
                                        <TableCell
                                            key={cell.id}
                                            className={cell.column.id === "actions" ? "w-0 px-1" : undefined}
                                        >
                                            {flexRender(cell.column.columnDef.cell, cell.getContext())}
                                        </TableCell>
                                    ))}
                                </TableRow>
                            ))
                        ) : (
                            <TableRow>
                                <TableCell colSpan={columns.length} className="h-24 text-center">
                                    No parties found.
                                </TableCell>
                            </TableRow>
                        )}
                    </TableBody>
                </Table>
            </div>
            <DataTablePagination table={table} />
        </div>
    )
}
