import { Checkbox } from "@/components/ui/checkbox"
import { cn } from "@/lib/utils"

/** Roughly the first row of the widest grid (lg:grid-cols-6) — loaded eagerly, the rest lazily. */
const EAGER_PICTURES_COUNT = 6

export type GalleryPicture = {
    id: number
    url: string
    thumbnailUrl: string
    width: number | null
    height: number | null
}

interface GalleryItemProps {
    picture: GalleryPicture
    index: number
    isSelected: boolean
    onToggle: () => void
    onOpen: (index: number) => void
}

function GalleryItem({ picture, index, isSelected, onToggle, onOpen }: GalleryItemProps) {
    return (
        <div
            className={cn(
                "group relative aspect-square overflow-hidden rounded-md border bg-muted transition-shadow",
                isSelected && "ring-2 ring-primary ring-offset-2",
            )}
        >
            <img
                src={picture.thumbnailUrl}
                alt=""
                className="h-full w-full cursor-zoom-in object-cover transition-transform duration-200 group-hover:scale-105"
                onClick={() => onOpen(index)}
                loading={index < EAGER_PICTURES_COUNT ? 'eager' : 'lazy'}
                decoding="async"
                fetchPriority={index < EAGER_PICTURES_COUNT ? 'high' : 'low'}
            />

            {isSelected && (
                <div className="pointer-events-none absolute inset-0 bg-primary/20" />
            )}

            <div
                className={cn(
                    "absolute top-1.5 right-1.5 transition-opacity",
                    isSelected ? "opacity-100" : "opacity-0 group-hover:opacity-100",
                )}
            >
                <Checkbox
                    checked={isSelected}
                    onCheckedChange={onToggle}
                    aria-label="Select picture"
                    className="border-white bg-black/50 backdrop-blur-sm data-[state=checked]:border-primary data-[state=checked]:bg-primary"
                />
            </div>
        </div>
    )
}

interface GalleryGridProps {
    pictures: GalleryPicture[]
    selected: Set<number>
    onToggle: (id: number) => void
    onOpen: (index: number) => void
}

export function GalleryGrid({ pictures, selected, onToggle, onOpen }: GalleryGridProps) {
    if (pictures.length === 0) {
        return (
            <div className="flex flex-col items-center justify-center rounded-lg border border-dashed py-16 text-center">
                <p className="text-sm font-medium text-muted-foreground">No pictures yet</p>
                <p className="mt-1 text-xs text-muted-foreground">
                    Pictures uploaded to this party will appear here.
                </p>
            </div>
        )
    }

    return (
        <div className="grid grid-cols-2 gap-2 sm:grid-cols-3 md:grid-cols-4 lg:grid-cols-6">
            {pictures.map((picture, index) => (
                <GalleryItem
                    key={picture.id}
                    picture={picture}
                    index={index}
                    isSelected={selected.has(picture.id)}
                    onToggle={() => onToggle(picture.id)}
                    onOpen={onOpen}
                />
            ))}
        </div>
    )
}
