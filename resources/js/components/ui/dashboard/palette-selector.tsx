"use client"

import * as React from "react"
import {
    Command,
    CommandDialog,
    CommandEmpty,
    CommandGroup,
    CommandInput,
    CommandItem,
    CommandList,
} from "@/components/ui/command"
import {
    SearchIcon,
} from "lucide-react"
import { InputGroup, InputGroupAddon, InputGroupInput } from "../input-group"
import { Kbd } from "../kbd"
import { useHotkeys } from 'react-hotkeys-hook'
import { Party } from "@/types/party"

export function CommandWithShortcuts({ parties }: { parties: Party[] }) {
    const [open, setOpen] = React.useState(false)
    useHotkeys(
        'ctrl+k',
        () => setOpen((open) => !open),
        { preventDefault: true },
    );

    return (
        <div>
            <div className="flex w-full max-w-xs flex-col gap-6 p-2">
                <InputGroup onClick={() => setOpen(true)}>
                    <InputGroupInput placeholder="Search..." />
                    <InputGroupAddon>
                        <SearchIcon />
                    </InputGroupAddon>
                    <InputGroupAddon align="inline-end">
                        <Kbd>⌘</Kbd>
                        <Kbd>K</Kbd>
                    </InputGroupAddon>
                </InputGroup>
            </div>
            <CommandDialog open={open} onOpenChange={setOpen}>
                <Command>
                    <CommandInput placeholder="Type a command or search..." />
                    <CommandList>
                        <CommandEmpty>No results found.</CommandEmpty>
                        <CommandGroup heading="Parties">
                            {parties.map((party) => (
                                <CommandItem key={party.id}>{party.name}</CommandItem>
                            ))}
                        </CommandGroup>
                    </CommandList>
                </Command>
            </CommandDialog>
        </div>
    )
}
