import { useEffect } from "react"
import { router } from "@inertiajs/react"
import {
    ChevronLeft,
    ChevronRight,
    ChevronsLeft,
    ChevronsRight,
} from "lucide-react"

import { Button } from "@/components/ui/button"
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from "@/components/ui/select"

const PER_PAGE_OPTIONS = [24, 48, 72, 96]

/**
 * Paginating only changes the pictures; the sidebar and party never do.
 * Requesting just those props keeps the payload small and lets Inertia reuse
 * a prefetched response, which requires identical visit options.
 */
const PAGE_VISIT_OPTIONS = {
    only: ["pictures", "perPage"],
    preserveScroll: true,
}

interface GalleryPaginationProps {
    currentPage: number
    lastPage: number
    from: number | null
    to: number | null
    total: number
    perPage: number
    partyName: string
}

export function GalleryPagination({
    currentPage,
    lastPage,
    from,
    to,
    total,
    perPage,
    partyName,
}: GalleryPaginationProps) {
    const url = `/${encodeURIComponent(partyName)}/dashboard/pictures`

    const visitOptions = (page: number, newPerPage: number) => ({
        ...PAGE_VISIT_OPTIONS,
        data: { page, per_page: newPerPage },
    })

    const navigate = (page: number, newPerPage = perPage) => {
        router.visit(url, visitOptions(page, newPerPage))
    }

    const handlePerPageChange = (value: string) => {
        navigate(1, Number(value))
    }

    /**
     * Warm the adjacent pages so paginating feels instant. Inertia serves the
     * next visit straight from its prefetch cache when the options match.
     */
    useEffect(() => {
        ;[currentPage - 1, currentPage + 1]
            .filter((target) => target >= 1 && target <= lastPage && target !== currentPage)
            .forEach((target) => {
                router.prefetch(
                    url,
                    { method: "get", ...visitOptions(target, perPage) },
                    { cacheFor: "2m" },
                )
            })
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [currentPage, lastPage, perPage, partyName])

    const canPrevious = currentPage > 1
    const canNext = currentPage < lastPage

    return (
        <div className="flex items-center justify-between px-2">
            <div className="flex-1 text-sm text-muted-foreground">
                Showing {from ?? 0}–{to ?? 0} of {total}
            </div>
            <div className="flex items-center gap-6 lg:gap-8">
                <div className="flex items-center gap-2">
                    <p className="text-sm font-medium">Pictures per page</p>
                    <Select
                        value={`${perPage}`}
                        onValueChange={handlePerPageChange}
                    >
                        <SelectTrigger className="h-8 w-[70px]">
                            <SelectValue placeholder={perPage} />
                        </SelectTrigger>
                        <SelectContent side="top">
                            {PER_PAGE_OPTIONS.map((size) => (
                                <SelectItem key={size} value={`${size}`}>
                                    {size}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
                <div className="flex w-[100px] items-center justify-center text-sm font-medium">
                    Page {currentPage} of {lastPage}
                </div>
                <div className="flex items-center gap-2">
                    <Button
                        variant="outline"
                        size="icon"
                        className="hidden size-8 lg:flex"
                        onClick={() => navigate(1)}
                        disabled={!canPrevious}
                    >
                        <span className="sr-only">Go to first page</span>
                        <ChevronsLeft />
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-8"
                        onClick={() => navigate(currentPage - 1)}
                        disabled={!canPrevious}
                    >
                        <span className="sr-only">Go to previous page</span>
                        <ChevronLeft />
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        className="size-8"
                        onClick={() => navigate(currentPage + 1)}
                        disabled={!canNext}
                    >
                        <span className="sr-only">Go to next page</span>
                        <ChevronRight />
                    </Button>
                    <Button
                        variant="outline"
                        size="icon"
                        className="hidden size-8 lg:flex"
                        onClick={() => navigate(lastPage)}
                        disabled={!canNext}
                    >
                        <span className="sr-only">Go to last page</span>
                        <ChevronsRight />
                    </Button>
                </div>
            </div>
        </div>
    )
}
