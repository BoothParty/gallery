import { Link } from "@inertiajs/react"

import { AdminLayout, type SidebarData } from "@/components/admin-layout"

type DashboardPicture = {
  id: number
  thumbnail_url: string | null
  hidden: boolean
}

type PageProps = SidebarData & {
  party: { description: string | null, expire_at: string | null }
  pictureStats: { total: number, lastHour: number }
  latestPictures: DashboardPicture[]
  activity: { description: string, at: string }[]
}

// ponytail: placeholder data from the Claude Design mockup, replace with real party stats once they exist server-side.
const placeholderStats = [
  { label: "Visiteurs", value: "86", delta: "+12 cette semaine", up: true },
  { label: "Téléchargements", value: "41", delta: "18 en ZIP", up: false },
]

const relativeTime = new Intl.RelativeTimeFormat("fr-FR", { numeric: "auto" })

function timeAgo(iso: string): string {
  const minutes = Math.round((new Date(iso).getTime() - Date.now()) / 60_000)

  if (minutes > -60) {
    return relativeTime.format(minutes, "minute")
  }

  if (minutes > -24 * 60) {
    return relativeTime.format(Math.round(minutes / 60), "hour")
  }

  return relativeTime.format(Math.round(minutes / (24 * 60)), "day")
}

const formatDate = (iso: string) =>
  new Intl.DateTimeFormat("fr-FR", { dateStyle: "long" }).format(new Date(iso))

function expiryLabel(expireAt: string | null): string {
  if (!expireAt) {
    return "galerie en ligne sans limite de durée"
  }

  return new Date(expireAt) < new Date()
    ? `galerie expirée depuis le ${formatDate(expireAt)}`
    : `galerie en ligne jusqu'au ${formatDate(expireAt)}`
}

export default function Page({ party: details, pictureStats, latestPictures, activity, ...sidebar }: PageProps) {
  const party = [...sidebar.parties, ...sidebar.searchableParties].find((p) => p.id === sidebar.currentPartyId)
  const stats = [
    {
      label: "Photos",
      value: String(pictureStats.total),
      delta: `+${pictureStats.lastHour} dans la dernière heure`,
      up: pictureStats.lastHour > 0,
    },
    ...placeholderStats,
  ]

  return (
    <AdminLayout title={`Party / ${party?.name ?? "Dashboard"}`} sidebar={sidebar}>
      <p className="-mt-3 text-sm text-muted-foreground">
        {[details.description, expiryLabel(details.expire_at)].filter(Boolean).join(" · ")}
      </p>

      <div className="grid grid-cols-[repeat(auto-fit,minmax(min(100%,190px),1fr))] gap-3">
        {stats.map((stat) => (
          <div key={stat.label} className="flex flex-col gap-2.5 rounded-md border bg-card px-4.5 py-4">
            <span className="text-[11px] tracking-[.12em] text-muted-foreground uppercase">{stat.label}</span>
            <span className="font-heading text-[34px] leading-none">{stat.value}</span>
            <span className={stat.up ? "text-xs text-[#4f6b4a]" : "text-xs text-muted-foreground"}>{stat.delta}</span>
          </div>
        ))}
      </div>

      <div className="grid grid-cols-[repeat(auto-fit,minmax(min(100%,320px),1fr))] items-start gap-3">
        <section className="overflow-hidden rounded-md border bg-card">
          <div className="flex items-baseline justify-between gap-3 border-b px-4.5 py-3.5">
            <h2 className="font-heading text-[19px]">Dernières photos</h2>
            {party && (
              <Link href={route("party.dashboard.pictures", party.name)} className="text-xs text-[#6b5a44] hover:text-foreground">
                Tout voir
              </Link>
            )}
          </div>
          {latestPictures.length > 0 ? (
            <div className="grid grid-cols-[repeat(auto-fill,minmax(72px,1fr))] gap-1.75 px-4.5 pt-3.5 pb-4.5">
              {latestPictures.map((picture) => (
                <div key={picture.id} className="aspect-square overflow-hidden rounded-sm bg-muted">
                  {picture.thumbnail_url && (
                    <img
                      src={picture.thumbnail_url}
                      alt=""
                      loading="lazy"
                      className={picture.hidden ? "size-full object-cover opacity-40 grayscale" : "size-full object-cover"}
                    />
                  )}
                </div>
              ))}
            </div>
          ) : (
            <p className="px-4.5 py-6 text-sm text-muted-foreground">Aucune photo pour le moment.</p>
          )}
        </section>

        <section className="overflow-hidden rounded-md border bg-card">
          <div className="border-b px-4.5 py-3.5">
            <h2 className="font-heading text-[19px]">Activité</h2>
          </div>
          {activity.length > 0 ? (
            <ul>
              {activity.map((item) => (
                <li key={item.at + item.description} className="flex items-baseline gap-3 border-b px-4.5 py-3 last:border-b-0">
                  <span className="min-w-0 flex-1 text-[13px] leading-normal text-pretty">{item.description}</span>
                  <time dateTime={item.at} className="text-xs whitespace-nowrap text-muted-foreground">{timeAgo(item.at)}</time>
                </li>
              ))}
            </ul>
          ) : (
            <p className="px-4.5 py-6 text-sm text-muted-foreground">Aucune activité pour le moment.</p>
          )}
        </section>
      </div>
    </AdminLayout>
  )
}
