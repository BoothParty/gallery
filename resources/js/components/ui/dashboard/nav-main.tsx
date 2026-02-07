"use client"

import { IconCamera, IconChartBar, IconDashboard, IconFolderCode, type Icon } from "@tabler/icons-react"

import {
  SidebarGroup,
  SidebarGroupContent,
  SidebarGroupLabel,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
} from "@/components/ui/sidebar"
import { Empty, EmptyContent, EmptyDescription, EmptyHeader, EmptyMedia, EmptyTitle } from "../empty"
import { Button } from "../button"
import { ArrowUpRightIcon } from "lucide-react"
import { Party } from "@/types/party"

const navParty = [
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
]

export function NavMain({
  party,
}: {
  party?: Party
}) {
  return (
    <SidebarGroup>
      <SidebarGroupContent>
        <SidebarGroupLabel>Party</SidebarGroupLabel>
        { party
          ? <SidebarMenu>
              {navParty.map((item) => (
                <SidebarMenuItem key={item.title}>
                  <SidebarMenuButton tooltip={item.title}>
                    {item.icon && <item.icon />}
                    <span>{item.title}</span>
                  </SidebarMenuButton>
                </SidebarMenuItem>
              ))}
            </SidebarMenu>
          : <div className="flex flex-col items-center justify-center w-full rounded-xl border border-dashed text-sm">
              <span className="p-4 text-center text-muted-foreground">
                No party selected
              </span>
              <Button
                variant="link"
                asChild
                className="text-muted-foreground"
                size="sm"
              >
                <a href="#">
                  Select a party <ArrowUpRightIcon />
                </a>
              </Button>
            </div>
        }
      </SidebarGroupContent>
    </SidebarGroup>
  )
}
