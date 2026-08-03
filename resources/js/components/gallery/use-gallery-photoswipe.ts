import { useCallback } from "react"
import PhotoSwipe from "photoswipe"

import type { GalleryPicture } from "@/components/gallery/gallery-grid"

/**
 * Returns an `open(index)` function that launches PhotoSwipe Core
 * programmatically with a download button in the UI.
 */
export function useGalleryPhotoSwipe(pictures: GalleryPicture[]) {
    return useCallback(
        (index: number) => {
            const pswp = new PhotoSwipe({
                dataSource: pictures.map((p) => ({
                    src: p.url,
                    width: p.width ?? 1920,
                    height: p.height ?? 1080,
                    msrc: p.thumbnailUrl ?? undefined,
                    alt: "",
                })),
                index,
            })

            pswp.on("uiRegister", () => {
                pswp.ui!.registerElement({
                    name: "download-button",
                    order: 8,
                    isButton: true,
                    tagName: "a",
                    html: {
                        isCustomSVG: true,
                        inner: '<path d="M20.5 14.3 17.1 18V10h-2.2v7.9l-3.4-3.6L10 16l6 6.1 6-6.1ZM23 23H9v2h14Z" id="pswp__icn-download"/>',
                        outlineID: "pswp__icn-download",
                    },
                    onInit: (el, instance) => {
                        el.setAttribute("download", "")
                        el.setAttribute("target", "_blank")
                        el.setAttribute("rel", "noopener")

                        instance.on("change", () => {
                            ;(el as HTMLAnchorElement).href = instance.currSlide!.data.src!
                        })
                    },
                })
            })

            pswp.init()
        },
        [pictures],
    )
}
