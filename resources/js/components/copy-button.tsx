import { useEffect, useState } from "react"

import { Button } from "@/components/ui/button"
import { cn } from "@/lib/utils"

type CopyButtonProps = {
    value: string
    label?: string
    size?: "default" | "sm"
}

/** Copies a value, then turns green and reads "Copied" for a moment. */
export function CopyButton({ value, label = "Copy", size = "default" }: CopyButtonProps) {
    const [copied, setCopied] = useState(false)

    useEffect(() => {
        if (!copied) {
            return
        }

        const timer = window.setTimeout(() => setCopied(false), 1800)

        return () => window.clearTimeout(timer)
    }, [copied])

    return (
        <Button
            type="button"
            size={size}
            variant="outline"
            className={cn(copied && "border-success bg-success/10 text-success hover:bg-success/10 hover:text-success")}
            onClick={(event) => {
                event.stopPropagation()
                navigator.clipboard.writeText(value).then(() => setCopied(true), () => {})
            }}
        >
            {copied ? "Copied" : label}
        </Button>
    )
}
