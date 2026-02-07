import { SidebarInset, SidebarProvider } from "@/components/ui/sidebar"
import { AppSidebar } from "@/components/ui/dashboard/app-sidebar"
import { SiteHeader } from "@/components/ui/dashboard/site-header"
import { User } from "@/types"
import { usePage } from "@inertiajs/react"
import { UsersDataTable, type UserData } from "@/components/ui/dashboard/users-data-table"
import { Party } from "@/types/party"

interface UsersPageProps {
  users: UserData[]
}

export default function UsersPage({ users }: UsersPageProps) {
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
                  <h1 className="text-3xl font-bold tracking-tight">Users</h1>
                  <p className="text-muted-foreground">
                    Manage all users in your application
                  </p>
                </div>
                <UsersDataTable data={users} />
              </div>
            </div>
          </div>
        </div>
      </SidebarInset>
    </SidebarProvider>
  )
}
