import { AdminLayout } from "@/components/admin-layout"

type DashboardProps = {
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

export default function Page({ currentPartyId, parties, searchableParties }: DashboardProps) {
  return (
    <AdminLayout
      title="Party / Dashboard"
      sidebar={{ currentPartyId, parties, searchableParties }}
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
