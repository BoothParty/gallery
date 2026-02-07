"use client"

import * as React from "react"
import { Link, usePage } from "@inertiajs/react"
import {
  IconCamera,
  IconChartBar,
  IconDashboard,
  IconDatabase,
  IconFileAi,
  IconFileDescription,
  IconFileWord,
  IconFolder,
  IconHelp,
  IconInnerShadowTop,
  IconListDetails,
  IconReport,
  IconSearch,
  IconSettings,
  IconUsers,
} from "@tabler/icons-react"

import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from "@/components/ui/sidebar"
import { NavAdmin } from "@/components/ui/dashboard/nav-admin"
import { NavMain } from "@/components/ui/dashboard/nav-main"
import { NavUser } from "@/components/ui/dashboard/nav-user"
import { CommandWithShortcuts } from "./palette-selector"
import { Party } from "@/types/party"
import { User } from "@/types/auth"
import UserController from "@/actions/App/Http/Controllers/UserController"
import PartyController from "@/actions/App/Http/Controllers/PartyController"

const data = {
  navParty: [
    {
      title: "Dashboard",
      url: "#",
      icon: IconDashboard,
    },
    {
      title: "Photos",
      url: "#",
      icon: IconCamera,
    },
    {
      title: "Analytics",
      url: "#",
      icon: IconChartBar,
    },
  ],
  navAdmin: [
    {
      title: "Dashboard",
      url: "#",
      icon: IconDatabase,
    },
    {
      title: "Users",
      url: UserController.index.url(),
      icon: IconUsers,
    },
    {
      title: "Parties",
      url: PartyController.index.url(),
      icon: IconCamera,
    },
    {
      title: "Analytics",
      url: "#",
      icon: IconChartBar,
    },
  ],
}

export function AppSidebar({...props}: React.ComponentProps<typeof Sidebar>) {
    const user = usePage().props.auth.user as User
    const parties = usePage().props.parties as Party[]
    const selected_party = usePage().props.selected_party as Party | undefined

  return (
    <Sidebar collapsible="none" className="h-auto border-r" {...props}>
      <SidebarHeader className="border-b">
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton
              asChild
              className="data-[slot=sidebar-menu-button]:!p-1.5"
            >
              <Link href="#">
                <IconInnerShadowTop className="!size-5" />
                <span className="text-base font-semibold">Acme Inc.</span>
              </Link>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarHeader>
      <SidebarContent>
        <CommandWithShortcuts parties={parties} />
        <NavMain party={selected_party} />
        <NavAdmin items={data.navAdmin} />
      </SidebarContent>
      <SidebarFooter>
        <NavUser user={user} />
      </SidebarFooter>
    </Sidebar>
  )
}
