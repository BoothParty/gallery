import { SidebarInset, SidebarProvider } from "@/components/ui/sidebar"
import { AppSidebar } from "@/components/ui/dashboard/app-sidebar"
import { SiteHeader } from "@/components/ui/dashboard/site-header"
import { User } from "@/types"
import { usePage } from "@inertiajs/react"
import { PartiesDataTable, type PartyData } from "@/components/ui/dashboard/parties-data-table"

interface PartiesPageProps {
  parties: PartyData[]
}

export default function PartiesPage({ parties }: PartiesPageProps) {
  const { props } = usePage<{ auth: { user: User } }>()

  return (
    <SidebarProvider
      className="hidden md:flex"
      style={
        {
          "--sidebar-width": "calc(var(--spacing) * 64)",
          "--header-height": "calc(var(--spacing) * 12 + 1px)",
        } as React.CSSProperties
      }
    >
      <AppSidebar variant="sidebar" />
      <SidebarInset>
        <SiteHeader />
        <div className="flex flex-1 flex-col">
          <div className="@container/main flex flex-1 flex-col gap-2">
            <div className="flex flex-col gap-4 py-4 md:gap-6 md:py-6">
              <div className="px-4 lg:px-6">
                <div className="mb-6">
                  <h1 className="text-3xl font-bold tracking-tight">Parties</h1>
                  <p className="text-muted-foreground">
                    Manage all parties in your application
                  </p>
                </div>
                <PartiesDataTable data={parties} />
              </div>
            </div>
          </div>
        </div>
      </SidebarInset>
    </SidebarProvider>
  )
}
