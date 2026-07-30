import { useEffect } from 'react';
import PhotoSwipeLightbox from 'photoswipe/lightbox';
import 'photoswipe/style.css';

/**
 * Initialize a PhotoSwipe lightbox on the given gallery selector.
 * Includes a custom download button in the lightbox UI.
 */
export default function usePhotoSwipe(gallerySelector: string) {
    useEffect(() => {
        const lightbox = new PhotoSwipeLightbox({
            gallery: gallerySelector,
            children: 'a',
            initialZoomLevel: 'fit',
            pswpModule: () => import('photoswipe'),
        });

        lightbox.on('uiRegister', () => {
            lightbox.pswp!.ui!.registerElement({
                name: 'download-button',
                order: 8,
                isButton: true,
                tagName: 'a',
                html: {
                    isCustomSVG: true,
                    inner: '<path d="M20.5 14.3 17.1 18V10h-2.2v7.9l-3.4-3.6L10 16l6 6.1 6-6.1ZM23 23H9v2h14Z" id="pswp__icn-download"/>',
                    outlineID: 'pswp__icn-download',
                },
                onInit: (el, pswp) => {
                    el.setAttribute('download', '');
                    el.setAttribute('target', '_blank');
                    el.setAttribute('rel', 'noopener');

                    pswp.on('change', () => {
                        (el as HTMLAnchorElement).href = pswp.currSlide!.data.src!;
                    });
                },
            });
        });

        lightbox.init();

        return () => {
            lightbox.destroy();
        };
    }, [gallerySelector]);
}
