import { useEffect, useState } from "react"

import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogHeader,
    DialogTitle,
} from "@/components/ui/dialog"

type PostersLink = {
    url: string
    qr: string
}

type PostersDialogProps = {
    open: boolean
    onOpenChange: (open: boolean) => void
    partyIds: number[]
}

/**
 * Shows the QR code that opens the posters of a batch on a phone. The link is
 * minted by the server, which alone can sign it.
 */
export function PartyPostersDialog({ open, onOpenChange, partyIds }: PostersDialogProps) {
    const [link, setLink] = useState<PostersLink | null>(null)
    const [hasFailed, setFailed] = useState(false)

    // The joined ids keep the effect from firing on every render, since the
    // array itself is rebuilt by the parent each time.
    const selection = partyIds.join(",")

    useEffect(() => {
        if (!open || selection === "") {
            return
        }

        setLink(null)
        setFailed(false)

        fetch(`/admin/parties/posters-link?parties=${encodeURIComponent(selection)}`, {
            headers: { Accept: "application/json" },
        })
            .then((response) => {
                if (!response.ok) {
                    throw new Error("The poster link could not be minted.")
                }

                return response.json()
            })
            .then((payload: PostersLink) => setLink(payload))
            .catch(() => setFailed(true))
    }, [open, selection])

    return (
        <Dialog open={open} onOpenChange={onOpenChange}>
            <DialogContent className="max-w-md">
                <DialogHeader>
                    <DialogTitle>Posters</DialogTitle>
                    <DialogDescription>
                        Scan this code with the phone that prints the posters, then share them
                        all at once.
                    </DialogDescription>
                </DialogHeader>

                <div className="mt-4 flex flex-col items-center gap-4">
                    {hasFailed && (
                        <p id="posters-error" className="text-sm text-red-500">
                            The link could not be generated. Close the dialog and try again.
                        </p>
                    )}

                    {link && (
                        <>
                            <img
                                id="posters-qr"
                                src={link.qr}
                                alt="QR code of the posters"
                                className="size-64"
                            />
                            <a
                                id="posters-link"
                                href={link.url}
                                className="text-muted-foreground text-xs break-all underline"
                            >
                                {link.url}
                            </a>
                        </>
                    )}
                </div>
            </DialogContent>
        </Dialog>
    )
}
