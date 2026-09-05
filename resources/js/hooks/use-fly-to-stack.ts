import { useCallback } from 'react';

import { MAX_STACK_THUMBNAILS, STACK_ELEMENT_ID, THUMBNAIL_OFFSET, THUMBNAIL_SIZE } from '@/components/party/selection-stack';

const FLIGHT_DURATION = 620;

function prefersReducedMotion(): boolean {
    return window.matchMedia('(prefers-reduced-motion: reduce)').matches;
}

/**
 * Sends a clone of the tapped thumbnail flying towards the selection stack, then
 * bounces the stack. Resolves once the thumbnail has landed, so the caller can
 * reveal it in the stack at the right moment.
 */
export default function useFlyToStack() {
    return useCallback((anchor: HTMLElement, stackIndex: number): Promise<void> => {
        const stack = document.getElementById(STACK_ELEMENT_ID);
        const image = anchor.querySelector('img');

        if (stack === null || image === null || prefersReducedMotion()) {
            return Promise.resolve();
        }

        const from = image.getBoundingClientRect();
        const to = stack.getBoundingClientRect();
        const targetX =
            to.left + Math.min(stackIndex, MAX_STACK_THUMBNAILS - 1) * THUMBNAIL_OFFSET + THUMBNAIL_SIZE / 2;
        const targetY = to.top + to.height / 2;

        const clone = document.createElement('div');
        clone.style.cssText = [
            'position:fixed',
            `left:${from.left}px`,
            `top:${from.top}px`,
            `width:${from.width}px`,
            `height:${from.height}px`,
            `background-image:url("${image.getAttribute('src')}")`,
            'background-size:cover',
            'background-position:center',
            'border-radius:2px',
            'z-index:60',
            'pointer-events:none',
            'box-shadow:0 18px 40px color-mix(in srgb, var(--color-shade) 35%, transparent)',
            'will-change:transform,opacity',
        ].join(';');
        document.body.appendChild(clone);

        const dx = targetX - (from.left + from.width / 2);
        const dy = targetY - (from.top + from.height / 2);
        const scale = THUMBNAIL_SIZE / from.width;

        const flight = clone.animate(
            [
                { transform: 'translate(0,0) scale(1) rotate(0deg)', opacity: 1, offset: 0 },
                {
                    transform: `translate(${dx * 0.55}px, ${dy * 0.42}px) scale(${(1 + scale) / 2}) rotate(-5deg)`,
                    opacity: 1,
                    offset: 0.55,
                },
                { transform: `translate(${dx}px, ${dy}px) scale(${scale}) rotate(3deg)`, opacity: 0.9, offset: 1 },
            ],
            { duration: FLIGHT_DURATION, easing: 'cubic-bezier(.34,.86,.28,1)' },
        );

        return flight.finished
            .then(() => {
                clone.remove();
                stack.animate([{ transform: 'scale(1)' }, { transform: 'scale(1.11)' }, { transform: 'scale(1)' }], {
                    duration: 260,
                    easing: 'ease-out',
                });
            })
            .catch(() => {
                clone.remove();
            });
    }, []);
}
