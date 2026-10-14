/**
 * Turns a dropped or picked photobooth folder into the list of pictures to
 * send, each one keyed by its path inside that folder. The path is what the
 * server deduplicates on, so it leaves out the root folder's own name: the same
 * folder dropped again under another name is still recognised.
 */

export type DroppedFile = { file: File, path: string }

/** The root folder's name is kept apart: it only serves to suggest a name. */
export type CollectedFolder = { root: string | null, files: DroppedFile[] }

export type FolderGroup = { folder: string, items: DroppedFile[] }

export type FolderAnalysis = {
    groups: FolderGroup[]
    tooLarge: DroppedFile[]
    ignoredCount: number
}

/** Mirrors the `max:10240` rule of StoreAdminPicturesRequest. */
export const MAX_FILE_BYTES = 10 * 1024 * 1024

const ACCEPTED_TYPES = ["image/jpeg", "image/png"]
const ACCEPTED_EXTENSION = /\.(jpe?g|png)$/i

const byPath = (a: DroppedFile, b: DroppedFile) => a.path.localeCompare(b.path, undefined, { numeric: true })

function stripRoot(path: string): string {
    const segments = path.split("/").filter(Boolean)

    return (segments.length > 1 ? segments.slice(1) : segments).join("/")
}

/**
 * Chrome hands a directory's content over in batches of at most 100 entries:
 * reading once would silently drop the rest of a large folder.
 */
async function readAllEntries(reader: FileSystemDirectoryReader): Promise<FileSystemEntry[]> {
    const entries: FileSystemEntry[] = []

    for (;;) {
        const batch = await new Promise<FileSystemEntry[]>((resolve, reject) => reader.readEntries(resolve, reject))

        if (batch.length === 0) {
            return entries
        }

        entries.push(...batch)
    }
}

async function walk(entry: FileSystemEntry, files: DroppedFile[]): Promise<void> {
    if (entry.isFile) {
        const file = await new Promise<File>((resolve, reject) => (entry as FileSystemFileEntry).file(resolve, reject))
        files.push({ file, path: stripRoot(entry.fullPath) })

        return
    }

    if (entry.isDirectory) {
        for (const child of await readAllEntries((entry as FileSystemDirectoryEntry).createReader())) {
            await walk(child, files)
        }
    }
}

/**
 * Must be called synchronously from the drop handler: the browser empties the
 * DataTransfer as soon as the handler yields, so the entries are taken before
 * the first await.
 */
export async function collectFromDataTransfer(dataTransfer: DataTransfer): Promise<CollectedFolder> {
    const entries = Array.from(dataTransfer.items)
        .map((item) => item.webkitGetAsEntry())
        .filter((entry): entry is FileSystemEntry => entry !== null)

    // Two roots stripped of their names could yield the same paths, and the
    // server would take the second picture for a copy of the first.
    if (entries.filter((entry) => entry.isDirectory).length > 1) {
        throw new Error("Drop a single folder at a time.")
    }

    const files: DroppedFile[] = []
    const root = entries.find((entry) => entry.isDirectory)?.name ?? null

    for (const entry of entries) {
        await walk(entry, files)
    }

    return { root, files }
}

export function collectFromInput(fileList: FileList): CollectedFolder {
    const files = Array.from(fileList)
    const relativePath = files[0]?.webkitRelativePath ?? ""

    return {
        root: relativePath.includes("/") ? relativePath.split("/")[0] : null,
        files: files.map((file) => ({ file, path: stripRoot(file.webkitRelativePath || file.name) })),
    }
}

/**
 * The transfer name a booth folder suggests: "Photobooth_Pinel_2026-10-14"
 * gives "Pinel". Falls back to the folder name itself, tidied.
 */
export function suggestName(root: string | null): string {
    if (!root) {
        return ""
    }

    const tidy = (value: string) => value.replace(/[_-]+/g, " ").replace(/\s+/g, " ").trim()
    const stripped = root
        .replace(/[_ -]?\d{4}[-_.]\d{2}[-_.]\d{2}.*$/, "")
        .replace(/^photobooth[_ -]?/i, "")

    return tidy(stripped) || tidy(root)
}

export function analyseFolder(files: DroppedFile[]): FolderAnalysis {
    const groups = new Map<string, DroppedFile[]>()
    const tooLarge: DroppedFile[] = []
    let ignoredCount = 0

    for (const item of files) {
        const segments = item.path.split("/")
        const name = segments[segments.length - 1]
        const isHidden = segments.some((segment) => segment.startsWith("."))
        const isImage = ACCEPTED_TYPES.includes(item.file.type) || ACCEPTED_EXTENSION.test(name)

        if (isHidden || name === "Thumbs.db" || !isImage) {
            ignoredCount++
            continue
        }

        if (item.file.size > MAX_FILE_BYTES) {
            tooLarge.push(item)
            continue
        }

        const folder = segments.slice(0, -1).join("/")
        groups.set(folder, [...(groups.get(folder) ?? []), item])
    }

    return {
        groups: Array.from(groups, ([folder, items]) => ({ folder, items: items.sort(byPath) }))
            .sort((a, b) => a.folder.localeCompare(b.folder)),
        tooLarge,
        ignoredCount,
    }
}
