import type { route as routeFn } from "ziggy-js"

/**
 * Injected by the @routes directive, which the root view emits on admin pages
 * only: the public gallery must not call it.
 */
declare global {
    const route: typeof routeFn
}
