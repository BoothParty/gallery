"use client"

import * as React from "react"
import { Link, useForm } from "@inertiajs/react"

import type { SidebarParty } from "@/components/admin-layout"
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
import { AlbumIcon, LayoutDashboardIcon, MicIcon, PartyPopperIcon, UsersIcon } from "lucide-react"

type AppSidebarProps = React.ComponentProps<typeof Sidebar> & {
  parties: SidebarParty[]
  searchableParties: SidebarParty[]
  currentPartyId: number | null
}

/** A module the current party does not have, awaiting confirmation to turn on. */
type PendingModule = {
  field: "has_gallery" | "has_audio_guestbook"
  label: string
}

export function AppSidebar({ parties, searchableParties, currentPartyId, ...props }: AppSidebarProps) {
  const currentParty = searchableParties.find((party) => party.id === currentPartyId) ?? null
  const currentPartyName = currentParty ? encodeURIComponent(currentParty.name) : null
  const dashboardUrl = currentPartyName ? `/${currentPartyName}/dashboard` : "#"
  const galleryUrl = currentPartyName ? `/${currentPartyName}/dashboard/pictures` : "#"
  const audiobookUrl = currentPartyName ? `/${currentPartyName}/dashboard/livre-or` : "#"

  const [pendingModule, setPendingModule] = React.useState<PendingModule | null>(null)
  const activateForm = useForm<{ has_gallery?: boolean; has_audio_guestbook?: boolean }>({})

  const activate = () => {
    if (!pendingModule || !currentParty) {
      return
    }

    activateForm.transform(() => ({ [pendingModule.field]: true }))
    activateForm.patch(`/admin/parties/${currentParty.id}/modules`, {
      preserveScroll: true,
      onSuccess: () => setPendingModule(null),
    })
  }

  /**
   * An inactive module is not hidden: it stays in place, dotted, and the click
   * offers to turn it on. The studio discovers what it has not sold to this
   * client without going through the parties list.
   */
  const moduleItem = (
    active: boolean,
    field: PendingModule["field"],
    label: string,
    href: string,
    icon: React.ReactNode,
  ) => (
    <SidebarMenuItem>
      {active ? (
        <SidebarMenuButton asChild tooltip={label}>
          <Link href={href}>
            {icon}
            <span>{label}</span>
          </Link>
        </SidebarMenuButton>
      ) : (
        <SidebarMenuButton
          tooltip={`${label} — non activé`}
          className="border border-dashed text-muted-foreground"
          onClick={() => setPendingModule({ field, label })}
          disabled={!currentParty}
        >
          {icon}
          <span>{label}</span>
        </SidebarMenuButton>
      )}
    </SidebarMenuItem>
  )

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
            {moduleItem(
              currentParty?.has_gallery ?? true,
              "has_gallery",
              "Pictures",
              galleryUrl,
              <AlbumIcon />,
            )}
            {moduleItem(
              currentParty?.has_audio_guestbook ?? false,
              "has_audio_guestbook",
              "Livre d'or",
              audiobookUrl,
              <MicIcon />,
            )}
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
        <NavUser />
      </SidebarFooter>
      <SidebarRail />

      <Dialog open={pendingModule !== null} onOpenChange={(open) => !open && setPendingModule(null)}>
        <DialogContent>
          <DialogHeader>
            <DialogTitle>Activer « {pendingModule?.label} » ?</DialogTitle>
            <DialogDescription>
              Ce module n'est pas activé pour {currentParty?.name}. L'activer le rend accessible
              immédiatement, côté studio comme côté client.
            </DialogDescription>
          </DialogHeader>
          <DialogFooter>
            <Button variant="outline" onClick={() => setPendingModule(null)}>
              Annuler
            </Button>
            <Button onClick={activate} disabled={activateForm.processing}>
              Activer
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </Sidebar>
  )
}
