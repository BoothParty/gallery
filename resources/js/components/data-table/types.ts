import type { RowData } from "@tanstack/react-table"

declare module "@tanstack/react-table" {
    // eslint-disable-next-line @typescript-eslint/no-unused-vars
    interface ColumnMeta<TData extends RowData, TValue> {
        label?: string
    }
}

export type RowAction<TRow> = {
    label: string
    variant?: "default" | "destructive"
    onClick: (row: TRow) => void
}
