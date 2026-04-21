"use client"

import * as React from "react"
import { Link, useForm } from "@inertiajs/react"

import { NavUser } from "@/components/nav-user"
import { PartySwitcher } from "@/components/party-switcher"
import { Button } from "@/components/ui/button"
import {
  Dialog,
  DialogContent,
  DialogDescription,
  DialogFooter,
  DialogHeader,
  DialogTitle,
} from "@/components/ui/dialog"
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
import { AlbumIcon, LayoutDashboardIcon, MusicIcon, PartyPopperIcon, UsersIcon } from "lucide-react"

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
    pictures_enabled?: boolean
    audios_enabled?: boolean
  }[]
  currentPartyId: number | null
}

export function AppSidebar({ parties, searchableParties, currentPartyId, ...props }: AppSidebarProps) {
  const [mediaToEnable, setMediaToEnable] = React.useState<"pictures" | "audios" | null>(null)
  const enableMediaForm = useForm({})
  const currentParty = searchableParties.find((party) => party.id === currentPartyId) ?? null
  const currentPartyName = currentParty ? encodeURIComponent(currentParty.name) : null
  const dashboardUrl = currentPartyName ? `/${currentPartyName}/dashboard` : "#"
  const galleryUrl = currentPartyName ? `/${currentPartyName}/dashboard/pictures` : "#"
  const audioUrl = currentPartyName ? `/${currentPartyName}/dashboard` : "#"
  const picturesEnabled = currentParty?.pictures_enabled ?? true
  const audiosEnabled = currentParty?.audios_enabled ?? false

  const openEnableMediaModal = (mediaType: "pictures" | "audios") => {
    if (!currentPartyName) {
      return
    }

    setMediaToEnable(mediaType)
  }

  const confirmEnableMedia = () => {
    if (!currentPartyName || !mediaToEnable) {
      return
    }

    enableMediaForm.post(`/${currentPartyName}/dashboard/media/${mediaToEnable}/enable`, {
      preserveScroll: true,
      onSuccess: () => setMediaToEnable(null),
    })
  }

  return (
    <>
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
                {picturesEnabled ? (
                  <SidebarMenuButton asChild tooltip="Pictures">
                    <Link href={galleryUrl}>
                      <AlbumIcon />
                      <span>Pictures</span>
                    </Link>
                  </SidebarMenuButton>
                ) : (
                  <SidebarMenuButton
                    type="button"
                    tooltip="Pictures"
                    className="border border-dashed border-sidebar-border text-sidebar-foreground/70"
                    onClick={() => openEnableMediaModal("pictures")}
                  >
                    <AlbumIcon />
                    <span>Pictures</span>
                  </SidebarMenuButton>
                )}
              </SidebarMenuItem>
              <SidebarMenuItem>
                {audiosEnabled ? (
                  <SidebarMenuButton asChild tooltip="Audios">
                    <Link href={audioUrl}>
                      <MusicIcon />
                      <span>Audios</span>
                    </Link>
                  </SidebarMenuButton>
                ) : (
                  <SidebarMenuButton
                    type="button"
                    tooltip="Audios"
                    className="border border-dashed border-sidebar-border text-sidebar-foreground/70"
                    onClick={() => openEnableMediaModal("audios")}
                  >
                    <MusicIcon />
                    <span>Audios</span>
                  </SidebarMenuButton>
                )}
              </SidebarMenuItem>
            </SidebarMenu>
          </SidebarGroup>
          <SidebarGroup>
            <SidebarGroupLabel>Administration</SidebarGroupLabel>
            <SidebarMenu>
              <SidebarMenuItem>
                <SidebarMenuButton asChild tooltip="Parties">
                  <Link href="/admin/parties">
                    <PartyPopperIcon />
                    <span>Parties</span>
                  </Link>
                </SidebarMenuButton>
              </SidebarMenuItem>
              <SidebarMenuItem>
                <SidebarMenuButton asChild tooltip="Users">
                  <Link href="/admin/users">
                    <UsersIcon />
                    <span>Users</span>
                  </Link>
                </SidebarMenuButton>
              </SidebarMenuItem>
            </SidebarMenu>
          </SidebarGroup>
        </SidebarContent>
        <SidebarFooter>
          <NavUser user={{ name: "Admin", email: "admin@example.com", avatar: "" }} />
        </SidebarFooter>
        <SidebarRail />
      </Sidebar>

      <Dialog
        open={mediaToEnable !== null}
        onOpenChange={(isOpen) => {
          if (!isOpen) {
            setMediaToEnable(null)
          }
        }}
      >
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Enable media</DialogTitle>
            <DialogDescription>
              This media type is disabled for the current party. Do you want to enable it?
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button type="button" variant="outline" onClick={() => setMediaToEnable(null)}>
              Cancel
            </Button>
            <Button type="button" onClick={confirmEnableMedia} disabled={enableMediaForm.processing}>
              Enable
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </>
  )
}
