/**
 * Shared geometry contract between the fly-to-stack animation and the
 * selection bar's thumbnail stack — both must agree on where the stack sits
 * and how its thumbnails are sized and spaced, or the animation will land
 * next to the stack instead of on it.
 */
export const STACK_ELEMENT_ID = 'selection-stack';
export const THUMBNAIL_SIZE = 46;
export const THUMBNAIL_OFFSET = 15;
export const MAX_STACK_THUMBNAILS = 4;
