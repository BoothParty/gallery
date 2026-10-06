"use client"

import * as React from "react"
import { Link } from "@inertiajs/react"

import { NavUser } from "@/components/nav-user"
import { PartySwitcher } from "@/components/party-switcher"
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarGroupLabel,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarRail,
} from "@/components/ui/sidebar"
import { AlbumIcon, LayoutDashboardIcon, PartyPopperIcon, UsersIcon } from "lucide-react"

type AppSidebarProps = React.ComponentProps<typeof Sidebar> & {
  parties: {
    id: number
    name: string
    expired: boolean
  }[]
  searchableParties: {
    id: number
    name: string
    expired: boolean
  }[]
  currentPartyId: number | null
}

export function AppSidebar({ parties, searchableParties, currentPartyId, ...props }: AppSidebarProps) {
  const currentParty = searchableParties.find((party) => party.id === currentPartyId) ?? null
  const dashboardUrl = currentParty ? route("party.dashboard.show", currentParty.name) : "#"
  const galleryUrl = currentParty ? route("party.dashboard.pictures", currentParty.name) : "#"

  return (
    <Sidebar collapsible="icon" {...props}>
      <SidebarHeader>
        <PartySwitcher
          parties={parties}
          searchableParties={searchableParties}
          currentPartyId={currentPartyId}
        />
      </SidebarHeader>
      <SidebarContent>
        <SidebarGroup>
          <SidebarGroupLabel>Party</SidebarGroupLabel>
          <SidebarMenu>
            <SidebarMenuItem>
              <SidebarMenuButton asChild tooltip="Dashboard">
                <Link href={dashboardUrl}>
                  <LayoutDashboardIcon />
                  <span>Dashboard</span>
                </Link>
              </SidebarMenuButton>
            </SidebarMenuItem>
            <SidebarMenuItem>
              <SidebarMenuButton asChild tooltip="Gallery">
                <Link href={galleryUrl}>
                  <AlbumIcon />
                  <span>Pictures</span>
                </Link>
              </SidebarMenuButton>
            </SidebarMenuItem>
          </SidebarMenu>
        </SidebarGroup>
        <SidebarGroup>
          <SidebarGroupLabel>Administration</SidebarGroupLabel>
          <SidebarMenu>
            <SidebarMenuItem>
              <SidebarMenuButton asChild tooltip="Parties">
                <Link href={route("admin.parties.index")}>
                  <PartyPopperIcon />
                  <span>Parties</span>
                </Link>
              </SidebarMenuButton>
            </SidebarMenuItem>
            <SidebarMenuItem>
              <SidebarMenuButton asChild tooltip="Users">
                <Link href={route("admin.users.index")}>
                  <UsersIcon />
                  <span>Users</span>
                </Link>
              </SidebarMenuButton>
            </SidebarMenuItem>
          </SidebarMenu>
        </SidebarGroup>
      </SidebarContent>
      <SidebarFooter>
        <NavUser />
      </SidebarFooter>
      <SidebarRail />
    </Sidebar>
  )
}
