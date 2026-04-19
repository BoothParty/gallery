import * as React from "react"

import { AppSidebar } from "@/components/app-sidebar"
import {
    Breadcrumb,
    BreadcrumbItem,
    BreadcrumbList,
    BreadcrumbPage,
} from "@/components/ui/breadcrumb"
import { Separator } from "@/components/ui/separator"
import {
    SidebarInset,
    SidebarProvider,
    SidebarTrigger,
} from "@/components/ui/sidebar"

type SidebarData = {
    currentPartyId: number | null
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
}

type AdminLayoutProps = React.PropsWithChildren<{
    title: string
    sidebar: SidebarData
}>

export function AdminLayout({ title, sidebar, children }: AdminLayoutProps) {
    return (
        <SidebarProvider>
            <AppSidebar
                parties={sidebar.parties}
                searchableParties={sidebar.searchableParties}
                currentPartyId={sidebar.currentPartyId}
            />
            <SidebarInset>
                <header className="flex h-16 shrink-0 items-center gap-2 transition-[width,height] ease-linear group-has-data-[collapsible=icon]/sidebar-wrapper:h-12">
                    <div className="flex items-center gap-2 px-4">
                        <SidebarTrigger className="-ml-1" />
                        <Separator
                            orientation="vertical"
                            className="mr-2 data-vertical:h-4 data-vertical:self-auto"
                        />
                        <Breadcrumb>
                            <BreadcrumbList>
                                <BreadcrumbItem>
                                    <BreadcrumbPage>{title}</BreadcrumbPage>
                                </BreadcrumbItem>
                            </BreadcrumbList>
                        </Breadcrumb>
                    </div>
                </header>
                <div className="flex flex-1 flex-col gap-4 p-4 pt-0">{children}</div>
            </SidebarInset>
        </SidebarProvider>
    )
}
