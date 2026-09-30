/**
 * Draws the share card on a canvas, in the browser. The layout comes from
 * share-card.js. The picture is made here and never uploaded.
 */

import { STRIPE_CODES, buildCardLayout } from './share-card.js';

const KNOWN_PARTIES = ['ajp', 'alp', 'dlp', 'ffv', 'grn', 'ind', 'lbt', 'lcv', 'lib', 'nat', 'onp', 'sff'];

/** A party's colour from the site's own tokens, grey for a party without one. */
export function partyColour(code) {
    const lower = String(code ?? '').toLowerCase();
    const styles = getComputedStyle(document.documentElement);
    const own = KNOWN_PARTIES.includes(lower) ? styles.getPropertyValue(`--color-party-${lower}`).trim() : '';

    return own || '#737373';
}

function draw(context, ops, width, height) {
    for (const op of ops) {
        if (op.type === 'rect') {
            context.fillStyle = op.fill;
            context.beginPath();
            context.roundRect(op.x, op.y, op.w, op.h, op.radius);
            context.fill();
        } else {
            context.font = op.font;
            context.fillStyle = op.fill;
            context.textAlign = op.align;
            context.textBaseline = 'alphabetic';
            context.fillText(op.text, op.x, op.y);
        }
    }

    return { width, height };
}

/**
 * @param {{ranked: {name: string, percent: number, sharedText: string, code: string}[], notEnough: string[], meta: object}} input
 * @returns {Promise<{dataUrl: string, blob: Blob, layout: object}>}
 */
export async function renderCard({ ranked, notEnough, meta }) {
    const canvas = document.createElement('canvas');
    const context = canvas.getContext('2d');

    const layout = buildCardLayout({
        ranked: ranked.map((party) => ({ ...party, colour: partyColour(party.code) })),
        notEnough,
        meta: { ...meta, stripe: STRIPE_CODES.map(partyColour) },
        measure: (text, font) => {
            context.font = font;

            return context.measureText(text).width;
        },
    });

    canvas.width = layout.width;
    canvas.height = layout.height;
    draw(context, layout.ops, layout.width, layout.height);

    const blob = await new Promise((resolve) => canvas.toBlob(resolve, 'image/png'));

    return { dataUrl: canvas.toDataURL('image/png'), blob, layout };
}
