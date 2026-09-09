import { Deferred } from "@inertiajs/react"

import { AdminLayout } from "@/components/admin-layout"
import PartyAudience, { type Audience } from "@/components/party-audience"
import { Skeleton } from "@/components/ui/skeleton"

type DashboardProps = {
  currentPartyId: number | null
  partyName: string
  analyticsEnabled: boolean
  /** Deferred: undefined until the reload brings it back, null when Umami is silent. */
  analytics?: Audience | null
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

function AudienceSkeleton() {
  return (
    <div className="space-y-4">
      <Skeleton className="h-6 w-40" />
      <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
        {[0, 1, 2, 3].map((tile) => (
          <Skeleton key={tile} className="h-[74px]" />
        ))}
      </div>
      <Skeleton className="h-56" />
      <div className="grid gap-3 lg:grid-cols-2">
        <Skeleton className="h-40" />
        <Skeleton className="h-40" />
      </div>
    </div>
  )
}

export default function Page({
  currentPartyId,
  partyName,
  analyticsEnabled,
  analytics,
  parties,
  searchableParties,
}: DashboardProps) {
  return (
    <AdminLayout
      title="Party / Dashboard"
      sidebar={{ currentPartyId, parties, searchableParties }}
    >
      <h1 className="sr-only">{partyName}</h1>

      {analyticsEnabled ? (
        <Deferred data="analytics" fallback={<AudienceSkeleton />}>
          <PartyAudience audience={analytics ?? null} />
        </Deferred>
      ) : (
        <div className="rounded-xl border border-dashed bg-muted/30 p-8 text-center">
          <h2 className="text-xl font-semibold">Statistiques désactivées</h2>
          <p className="mt-2 text-sm text-muted-foreground">
            Renseignez les variables <code>UMAMI_*</code> pour suivre l'audience de la galerie.
          </p>
        </div>
      )}
    </AdminLayout>
  )
}
