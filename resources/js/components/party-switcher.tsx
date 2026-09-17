"use client"

import * as React from "react"
import { router, usePage } from "@inertiajs/react"

import type { SidebarParty } from "@/components/admin-layout"

import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuShortcut,
  DropdownMenuTrigger,
} from "@/components/ui/dropdown-menu"
import { Input } from "@/components/ui/input"
import {
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  useSidebar,
} from "@/components/ui/sidebar"
import { ChevronsUpDownIcon, PlusIcon, SearchIcon, XIcon } from "lucide-react"

export function PartySwitcher({
  parties,
  searchableParties,
  currentPartyId,
}: {
  parties: SidebarParty[]
  searchableParties: SidebarParty[]
  currentPartyId: number | null
}) {
  const { isMobile } = useSidebar()
  const { component } = usePage()
  const [open, setOpen] = React.useState(false)
  const [search, setSearch] = React.useState("")
  const [activeParty, setActiveParty] = React.useState(
    searchableParties.find((party) => party.id === currentPartyId) ?? parties[0] ?? searchableParties[0]
  )

  const filteredParties = React.useMemo(() => {
    const normalizedSearch = search.trim().toLocaleLowerCase()

    if (normalizedSearch.length === 0) {
      return parties
    }

    return searchableParties.filter((party) =>
      party.name.toLocaleLowerCase().includes(normalizedSearch)
    )
  }, [parties, searchableParties, search])

  React.useEffect(() => {
    setActiveParty(
      searchableParties.find((party) => party.id === currentPartyId) ?? parties[0] ?? searchableParties[0]
    )
  }, [currentPartyId, searchableParties, parties])

  if (!activeParty) {
    return null
  }

  const hasSearch = search.trim().length > 0

  return (
    <SidebarMenu>
      <SidebarMenuItem>
        <DropdownMenu
          open={open}
          onOpenChange={(nextOpen) => {
            setOpen(nextOpen)

            if (!nextOpen) {
              setSearch("")
            }
          }}
        >
          <DropdownMenuTrigger asChild>
            <SidebarMenuButton
              size="lg"
              className="data-[state=open]:bg-sidebar-accent data-[state=open]:text-sidebar-accent-foreground"
            >
              <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                {activeParty.name.charAt(0).toUpperCase()}
              </div>
              <div className="grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-medium">{activeParty.name}</span>
                <span className="truncate text-xs">Party</span>
              </div>
              <ChevronsUpDownIcon className="ml-auto" />
            </SidebarMenuButton>
          </DropdownMenuTrigger>
          <DropdownMenuContent
            className="w-(--radix-dropdown-menu-trigger-width) min-w-56 rounded-lg p-0"
            align="start"
            side={isMobile ? "bottom" : "right"}
            sideOffset={4}
          >
            <div className="sticky top-0 z-10 border-b bg-popover/95 p-2 backdrop-blur-sm">
              <div className="relative">
                <SearchIcon className="pointer-events-none absolute top-1/2 left-2.5 size-4 -translate-y-1/2 text-muted-foreground" />
                <Input
                  value={search}
                  onChange={(event) => setSearch(event.target.value)}
                  onKeyDown={(event) => {
                    if (event.key === "Escape") {
                      setOpen(false)
                      return
                    }

                    event.stopPropagation()
                  }}
                  placeholder="Search parties..."
                  aria-label="Search parties"
                  className="h-9 border-border bg-background pl-8 pr-8"
                />
                {hasSearch && (
                  <button
                    type="button"
                    onClick={() => setSearch("")}
                    className="absolute top-1/2 right-2 inline-flex size-5 -translate-y-1/2 items-center justify-center rounded-sm text-muted-foreground transition-colors hover:bg-muted hover:text-foreground"
                    aria-label="Clear search"
                  >
                    <XIcon className="size-3.5" />
                  </button>
                )}
              </div>
            </div>
            <DropdownMenuLabel className="px-3 pt-2 pb-1 text-xs text-muted-foreground">
              {hasSearch ? "Search results" : "Parties"}
            </DropdownMenuLabel>
            <div className="max-h-72 overflow-y-auto px-1 pb-1">
              {filteredParties.map((party, index) => (
                <DropdownMenuItem
                  key={party.id}
                  onClick={() => {
                    setActiveParty(party)
                    const partyName = encodeURIComponent(party.name)
                    const destination = component === 'admin/gallery/index'
                      ? `/${partyName}/dashboard/pictures`
                      : `/${partyName}/dashboard`
                    router.visit(destination)
                  }}
                  className="gap-2 rounded-md px-2 py-2"
                >
                  <div className="flex size-6 items-center justify-center rounded-md border">
                    {party.name.charAt(0).toUpperCase()}
                  </div>
                  <span className="truncate">{party.name}</span>
                  {party.expired && (
                    <span className="ml-auto rounded-sm bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground">
                      Expired
                    </span>
                  )}
                  {!party.expired && index < 9 && (
                    <DropdownMenuShortcut>⌘{index + 1}</DropdownMenuShortcut>
                  )}
                </DropdownMenuItem>
              ))}
              {filteredParties.length === 0 && (
                <div className="rounded-md border border-dashed px-3 py-4 text-center text-sm text-muted-foreground">
                  No parties found
                </div>
              )}
            </div>
            <DropdownMenuSeparator />
            <DropdownMenuItem className="m-1 gap-2 rounded-md p-2">
              <div className="flex size-6 items-center justify-center rounded-md border bg-transparent">
                <PlusIcon className="size-4" />
              </div>
              <div className="font-medium text-muted-foreground">Add party</div>
            </DropdownMenuItem>
          </DropdownMenuContent>
        </DropdownMenu>
      </SidebarMenuItem>
    </SidebarMenu>
  )
}
