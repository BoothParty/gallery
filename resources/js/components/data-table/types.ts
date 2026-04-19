export type CrudColumn = {
    name: string
    label: string
    type: string
    sortable: boolean
    searchable: boolean
    visible: boolean
    format: string | null
    badge: string | null
    relation?: {
        model: string
        labelColumn: string
    } | null
    linkHref?: string | null
}

export type CrudRow = Record<string, unknown>

export type CrudRowAction = {
    label: string
    variant?: "default" | "destructive"
    onClick: (row: CrudRow) => void
}

export type CrudTableData = {
    columns: CrudColumn[]
    rows: CrudRow[]
}

export type PartyTableData = CrudTableData
