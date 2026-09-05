import { DataTableMain } from "@/components/data-table/index"
import type { CrudRow, CrudRowAction, CrudTableData } from "@/components/data-table/types"

interface DataTableProps {
    tableData: CrudTableData
    rowEditHrefBase?: string
    toolbarAction?: React.ReactNode
    onRowClick?: (row: CrudRow) => void
    rowActions?: CrudRowAction[]
    onSelectionChange?: (rows: CrudRow[]) => void
}

export function DataTable({ tableData, rowEditHrefBase, toolbarAction, onRowClick, rowActions, onSelectionChange }: DataTableProps) {
    return (
        <DataTableMain
            tableData={tableData}
            rowEditHrefBase={rowEditHrefBase}
            toolbarAction={toolbarAction}
            onRowClick={onRowClick}
            rowActions={rowActions}
            onSelectionChange={onSelectionChange}
        />
    )
}
