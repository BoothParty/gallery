import * as React from "react"
import { type Column } from "@tanstack/react-table"
import { PlusCircle } from "lucide-react"

import { Badge } from "@/components/ui/badge"
import { Button } from "@/components/ui/button"
import {
    DropdownMenu,
    DropdownMenuCheckboxItem,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { Input } from "@/components/ui/input"
import { Separator } from "@/components/ui/separator"

interface DataTableFacetedFilterProps<TData, TValue> {
    column?: Column<TData, TValue>
    title: string
    options: {
        label: string
        value: string
        count?: number
        icon?: React.ComponentType<{ className?: string }>
    }[]
}

export function DataTableFacetedFilter<TData, TValue>({
    column,
    title,
    options,
}: DataTableFacetedFilterProps<TData, TValue>) {
    const selectedValues = new Set(column?.getFilterValue() as string[])
    const [search, setSearch] = React.useState("")

    const filteredOptions = React.useMemo(() => {
        const normalizedSearch = search.trim().toLowerCase()

        if (!normalizedSearch) {
            return options
        }

        return options.filter((option) =>
            option.label.toLowerCase().includes(normalizedSearch)
        )
    }, [options, search])

    return (
        <DropdownMenu>
            <DropdownMenuTrigger asChild>
                <Button variant="outline" size="sm" className="h-8 border-dashed">
                    <PlusCircle />
                    {title}
                    {selectedValues.size > 0 && (
                        <>
                            <Separator orientation="vertical" className="mx-2 h-4" />
                            <Badge variant="secondary" className="rounded-sm px-1 font-normal lg:hidden">
                                {selectedValues.size}
                            </Badge>
                            <div className="hidden gap-1 lg:flex">
                                {selectedValues.size > 2 ? (
                                    <Badge variant="secondary" className="rounded-sm px-1 font-normal">
                                        {selectedValues.size} selected
                                    </Badge>
                                ) : (
                                    options
                                        .filter((option) => selectedValues.has(option.value))
                                        .map((option) => (
                                            <Badge key={option.value} variant="secondary" className="rounded-sm px-1 font-normal">
                                                {option.label}
                                            </Badge>
                                        ))
                                )}
                            </div>
                        </>
                    )}
                </Button>
            </DropdownMenuTrigger>
            <DropdownMenuContent align="start" className="w-[220px] p-1">
                <Input
                    value={search}
                    onChange={(event) => setSearch(event.target.value)}
                    placeholder={`Filter ${title.toLowerCase()}...`}
                    className="mb-1 h-8"
                />
                {filteredOptions.length > 0 ? (
                    filteredOptions.map((option) => {
                        const isSelected = selectedValues.has(option.value)

                        return (
                            <DropdownMenuCheckboxItem
                                key={option.value}
                                checked={isSelected}
                                onCheckedChange={() => {
                                    if (isSelected) {
                                        selectedValues.delete(option.value)
                                    } else {
                                        selectedValues.add(option.value)
                                    }

                                    const filterValues = Array.from(selectedValues)
                                    column?.setFilterValue(filterValues.length ? filterValues : undefined)
                                }}
                            >
                                {option.icon && <option.icon className="text-muted-foreground" />}
                                <span>{option.label}</span>
                                {option.count !== undefined && option.count > 0 && (
                                    <span className="ml-auto flex size-5 items-center justify-center rounded-sm border text-xs">
                                        {option.count}
                                    </span>
                                )}
                            </DropdownMenuCheckboxItem>
                        )
                    })
                ) : (
                    <div className="p-2 text-sm text-muted-foreground">No results found.</div>
                )}
                {selectedValues.size > 0 && (
                    <>
                        <DropdownMenuSeparator />
                        <DropdownMenuItem
                            onClick={() => column?.setFilterValue(undefined)}
                            className="justify-center"
                        >
                            Clear filters
                        </DropdownMenuItem>
                    </>
                )}
            </DropdownMenuContent>
        </DropdownMenu>
    )
}
