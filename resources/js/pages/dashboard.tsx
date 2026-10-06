import { AdminLayout, type SidebarData } from "@/components/admin-layout"

export default function Page(sidebar: SidebarData) {
  return (
    <AdminLayout
      title="Party / Dashboard"
      sidebar={sidebar}
    >
      <div className="rounded-xl border border-dashed bg-muted/30 p-8 text-center">
        <h2 className="text-xl font-semibold">Party dashboard</h2>
        <p className="mt-2 text-sm text-muted-foreground">
          This page is intentionally empty for now.
        </p>
      </div>
    </AdminLayout>
  )
}
