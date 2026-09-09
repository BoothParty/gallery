export type Audience = {
    hostname: string
    dashboard_url: string
    unit: string
    visitors: number
    visits: number
    pageviews: number
    average_seconds: number
    events: Record<string, number>
    series: { label: string; value: number }[]
    referrers: { label: string; value: number }[]
    devices: { label: string; value: number }[]
}

/** Reads as « 1 min 20 s », the unit being the shortest one that carries. */
function formatDuration(seconds: number): string {
    if (seconds < 60) {
        return `${seconds} s`
    }

    const minutes = Math.floor(seconds / 60)
    const rest = seconds % 60

    return rest === 0 ? `${minutes} min` : `${minutes} min ${rest} s`
}

/**
 * Umami labels a point "2026-09-09 21:00:00", which is only readable once
 * reduced to the granularity actually plotted.
 */
function formatPoint(label: string, unit: string): string {
    const [date, time] = label.split(" ")

    if (unit === "hour") {
        return `${time?.slice(0, 2) ?? ""} h`
    }

    const [, month, day] = date.split("-")

    return `${day}/${month}`
}

function Tile({ label, value }: { label: string; value: string }) {
    return (
        <div className="rounded-xl border bg-card px-4 py-3">
            <div className="text-2xl font-semibold tabular-nums">{value}</div>
            <div className="mt-0.5 text-xs text-muted-foreground">{label}</div>
        </div>
    )
}

/**
 * A single series of counts, so the bars carry no legend: the heading names
 * them. Every bar reports its own figure on hover, the axis only labelling
 * enough points to stay legible.
 */
function VisitsChart({ series, unit }: { series: Audience["series"]; unit: string }) {
    const peak = Math.max(...series.map((point) => point.value), 1)
    const tickEvery = Math.ceil(series.length / 12)

    return (
        <div className="rounded-xl border bg-card p-4">
            <h3 className="text-sm font-medium">
                {unit === "hour" ? "Visites par heure" : "Visites par jour"}
            </h3>

            {series.length === 0 ? (
                <p className="mt-3 text-sm text-muted-foreground">Aucune visite sur la période.</p>
            ) : (
                <div className="mt-4 overflow-x-auto">
                    <div className="flex h-40 min-w-full items-end gap-[2px]">
                        {series.map((point) => (
                            <div
                                key={point.label}
                                title={`${formatPoint(point.label, unit)} · ${point.value} visite${point.value === 1 ? "" : "s"}`}
                                className="flex-1 rounded-t-[4px] bg-primary/85 transition-colors hover:bg-primary"
                                style={{ height: `${Math.max((point.value / peak) * 100, point.value === 0 ? 0 : 2)}%` }}
                                aria-label={`${formatPoint(point.label, unit)} : ${point.value}`}
                            />
                        ))}
                    </div>

                    <div className="mt-2 flex min-w-full gap-[2px] text-[10px] text-muted-foreground">
                        {series.map((point, index) => (
                            <div key={point.label} className="flex-1 text-center whitespace-nowrap">
                                {index % tickEvery === 0 ? formatPoint(point.label, unit) : ""}
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    )
}

function Breakdown({ title, rows }: { title: string; rows: Audience["referrers"] }) {
    const total = rows.reduce((sum, row) => sum + row.value, 0)

    return (
        <div className="rounded-xl border bg-card p-4">
            <h3 className="text-sm font-medium">{title}</h3>

            {rows.length === 0 ? (
                <p className="mt-3 text-sm text-muted-foreground">Aucune donnée.</p>
            ) : (
                <ul className="mt-3 space-y-2">
                    {rows.map((row) => (
                        <li key={row.label} className="relative flex justify-between gap-4 text-sm">
                            <span
                                className="absolute inset-y-0 left-0 rounded-sm bg-muted"
                                style={{ width: `${total === 0 ? 0 : (row.value / total) * 100}%` }}
                                aria-hidden
                            />
                            <span className="relative truncate">{row.label}</span>
                            <span className="relative tabular-nums text-muted-foreground">{row.value}</span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    )
}

export default function PartyAudience({ audience }: { audience: Audience | null }) {
    if (audience === null) {
        return (
            <div className="rounded-xl border border-dashed bg-muted/30 p-6 text-center text-sm text-muted-foreground">
                Les statistiques ne sont pas disponibles pour le moment.
            </div>
        )
    }

    const downloads = (audience.events.download ?? 0) + (audience.events.share ?? 0)

    return (
        <section className="space-y-4">
            <header className="flex flex-wrap items-baseline justify-between gap-2">
                <div>
                    <h2 className="text-lg font-semibold">Audience</h2>
                    <p className="text-xs text-muted-foreground">
                        {audience.hostname} · {formatDuration(audience.average_seconds)} par visite en moyenne
                    </p>
                </div>

                <a
                    href={audience.dashboard_url}
                    target="_blank"
                    rel="noreferrer"
                    className="text-xs text-muted-foreground underline hover:text-foreground"
                >
                    Voir dans Umami
                </a>
            </header>

            <div className="grid grid-cols-2 gap-3 lg:grid-cols-4">
                <Tile label="Visiteurs" value={String(audience.visitors)} />
                <Tile label="Vues de la galerie" value={String(audience.pageviews)} />
                <Tile label="Photos ouvertes" value={String(audience.events.photo_open ?? 0)} />
                <Tile label="Téléchargements et partages" value={String(downloads)} />
            </div>

            <VisitsChart series={audience.series} unit={audience.unit} />

            <div className="grid gap-3 lg:grid-cols-2">
                <Breakdown title="Sources d'arrivée" rows={audience.referrers} />
                <Breakdown title="Appareils" rows={audience.devices} />
            </div>
        </section>
    )
}
