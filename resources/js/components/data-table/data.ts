import {
    ArrowDown,
    ArrowUp,
    CheckCircle,
    Circle,
    CircleOff,
    HelpCircle,
    Timer,
} from "lucide-react"

import type { Task } from "@/components/data-table/types"

export const labels = [
    { value: "bug", label: "Bug" },
    { value: "feature", label: "Feature" },
    { value: "documentation", label: "Documentation" },
]

export const statuses = [
    { value: "backlog", label: "Backlog", icon: HelpCircle },
    { value: "todo", label: "Todo", icon: Circle },
    { value: "in progress", label: "In Progress", icon: Timer },
    { value: "done", label: "Done", icon: CheckCircle },
    { value: "canceled", label: "Canceled", icon: CircleOff },
]

export const priorities = [
    { value: "low", label: "Low", icon: ArrowDown },
    { value: "medium", label: "Medium", icon: ArrowDown },
    { value: "high", label: "High", icon: ArrowUp },
]

export const tasks: Task[] = [
    {
        id: "TASK-8782",
        title: "You can't compress the program without quantifying the open-source SSD pixel.",
        status: "in progress",
        label: "documentation",
        priority: "medium",
    },
    {
        id: "TASK-7839",
        title: "We need to bypass the neural TCP card.",
        status: "todo",
        label: "bug",
        priority: "high",
    },
    {
        id: "TASK-5562",
        title: "The SAS interface is down, bypass the open-source pixel.",
        status: "backlog",
        label: "feature",
        priority: "medium",
    },
    {
        id: "TASK-8686",
        title: "I'll parse the wireless SSL protocol, that should drive the API panel.",
        status: "canceled",
        label: "feature",
        priority: "medium",
    },
    {
        id: "TASK-1280",
        title: "Use the digital TLS panel, then you can transmit the haptic system.",
        status: "done",
        label: "bug",
        priority: "high",
    },
    {
        id: "TASK-7262",
        title: "Parse the neural bandwidth so we can back up the PNG firewall.",
        status: "done",
        label: "feature",
        priority: "high",
    },
    {
        id: "TASK-1138",
        title: "Generating the driver won't do anything, quantify the SMTP bandwidth.",
        status: "in progress",
        label: "feature",
        priority: "medium",
    },
    {
        id: "TASK-7184",
        title: "We need to program the back-end THX pixel.",
        status: "todo",
        label: "feature",
        priority: "low",
    },
    {
        id: "TASK-5160",
        title: "Navigate the back-end JSON protocol.",
        status: "in progress",
        label: "documentation",
        priority: "high",
    },
    {
        id: "TASK-5814",
        title: "We need to calculate the 1080p AGP feed.",
        status: "backlog",
        label: "bug",
        priority: "high",
    },
    {
        id: "TASK-4535",
        title: "Try to copy the JSON circuit, maybe it will connect the wireless feed.",
        status: "in progress",
        label: "feature",
        priority: "low",
    },
    {
        id: "TASK-8806",
        title: "We need to bypass the back-end SSL panel.",
        status: "done",
        label: "bug",
        priority: "medium",
    },
]
