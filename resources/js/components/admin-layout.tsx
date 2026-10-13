import * as React from "react"
import { ArrowUpRightIcon } from "lucide-react"

import { AppSidebar } from "@/components/app-sidebar"
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbList,
    BreadcrumbPage,
    BreadcrumbSeparator,
} from "@/components/ui/breadcrumb"
import { Separator } from "@/components/ui/separator"
import {
    SidebarInset,
    SidebarProvider,
    SidebarTrigger,
} from "@/components/ui/sidebar"
import { TooltipProvider } from "@/components/ui/tooltip"

type SidebarParty = {
    id: number
    name: string
    expired: boolean
}

/**
 * Shared by every admin page: the controllers spread it into their props.
 */
export type SidebarData = {
    currentPartyId: number | null
    parties: SidebarParty[]
    searchableParties: SidebarParty[]
}

type AdminLayoutProps = React.PropsWithChildren<{
    title: string
    sidebar: SidebarData
}>

export function AdminLayout({ title, sidebar, children }: AdminLayoutProps) {
    const crumbs = title.split(" / ")
    const heading = crumbs[crumbs.length - 1]
    const currentParty = [...sidebar.parties, ...sidebar.searchableParties]
        .find((party) => party.id === sidebar.currentPartyId)

    return (
        <TooltipProvider>
            <SidebarProvider>
                <AppSidebar
                    parties={sidebar.parties}
                    searchableParties={sidebar.searchableParties}
                    currentPartyId={sidebar.currentPartyId}
                />
                <SidebarInset>
                    <header className="flex h-15 shrink-0 items-center gap-2 border-b bg-card transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12">
                        <div className="flex items-center gap-2 px-4">
                            <SidebarTrigger className="-ml-1" />
                            <Separator
                                orientation="vertical"
                                className="mr-2 data-vertical:h-4 data-vertical:self-auto"
                            />
                            <Breadcrumb>
                                <BreadcrumbList>
                                    {crumbs.slice(0, -1).map((crumb, index) => (
                                        <React.Fragment key={index}>
                                            <BreadcrumbItem>{crumb}</BreadcrumbItem>
                                            <BreadcrumbSeparator />
                                        </React.Fragment>
                                    ))}
                                    <BreadcrumbItem>
                                        <BreadcrumbPage>{heading}</BreadcrumbPage>
                                    </BreadcrumbItem>
                                </BreadcrumbList>
                            </Breadcrumb>
                        </div>
                        {currentParty && (
                            <a
                                href={route("party.gallery", currentParty.name)}
                                target="_blank"
                                rel="noopener"
                                className="ml-auto flex items-center gap-1.5 px-4 text-[13px] whitespace-nowrap text-[#6b5a44] hover:text-foreground"
                            >
                                Voir la galerie publique
                                <ArrowUpRightIcon className="size-3.5" />
                            </a>
                        )}
                    </header>
                    <div className="flex flex-1 flex-col gap-5 p-[clamp(18px,2.5vw,28px)]">
                        <h1 className="font-heading text-[clamp(25px,3vw,32px)] leading-tight">
                            {heading}
                        </h1>
                        {children}
                    </div>
                </SidebarInset>
            </SidebarProvider>
        </TooltipProvider>
    )
}
