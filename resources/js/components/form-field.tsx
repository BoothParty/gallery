import * as React from "react"

import { Label } from "@/components/ui/label"

type FormFieldProps = React.PropsWithChildren<{
    id: string
    label: string
    hint?: React.ReactNode
    error?: string
}>

export function FormField({ id, label, hint, error, children }: FormFieldProps) {
    return (
        <div className="space-y-2">
            <Label htmlFor={id}>{label}</Label>
            {children}
            {hint && <p className="text-xs text-muted-foreground">{hint}</p>}
            {error && <p className="text-xs text-red-500">{error}</p>}
        </div>
    )
}
