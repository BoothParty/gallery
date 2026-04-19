import { type Row } from "@tanstack/react-table"
import { MoreHorizontal } from "lucide-react"

import type { CrudRow, CrudRowAction } from "@/components/data-table/types"
import { Button } from "@/components/ui/button"
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"

export function DataTableRowActions({ row, actions }: { row: Row<CrudRow>, actions: CrudRowAction[] }) {
    if (actions.length === 0) {
        return null
    }

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="ghost" size="icon" className="size-8 data-[state=open]:bg-muted">
                    <MoreHorizontal />
                    <span className="sr-only">Open menu</span>
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="end" className="w-[160px]">
                {actions.map((action) => (
                    <DropdownMenuItem
                        key={action.label}
                        variant={action.variant === "destructive" ? "destructive" : "default"}
                        onClick={() => action.onClick(row.original)}
                    >
                        {action.label}
                    </DropdownMenuItem>
                ))}
            </DropdownMenuContent>
        </DropdownMenu>
    )
}
