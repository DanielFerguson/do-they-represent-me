/**
 * The layout of the picture of someone's results (the card they can share).
 *
 * This only works out what to draw and where, as a list of rectangles and
 * lines of text. Drawing it is share-card-render.js. Keeping them apart means
 * the layout can be tested without a canvas: text is measured through a
 * function passed in, and nothing here touches window or document.
 *
 * The card always uses the light palette, because it leaves the site. Every
 * party is drawn the same way: a grey bar, and colour only on the swatch.
 * Names are never cut short. A name that is too wide is made smaller instead.
 */

import { SITE_NAME } from './share.js';

export const CARD_WIDTH = 1080;
export const CARD_HEIGHT = 1350;

export const FONT_STACK = "ui-sans-serif, system-ui, -apple-system, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif";

export const CARD_COLOURS = {
    ground: '#ffffff',
    ink: '#111111',
    muted: '#525252',
    rule: '#e5e5e5',
    bar: '#737373',
};

/** The parties in the stripe, in the order of resources/views/components/party-stripe.blade.php. */
export const STRIPE_CODES = ['ajp', 'dlp', 'ffv', 'grn', 'alp', 'lcv', 'lib', 'lbt', 'nat', 'onp', 'sff'];

const PAD = 72;
const INNER = CARD_WIDTH - PAD * 2;
const STRIPE_HEIGHT = 16;
const MIN_ROW = 40;
const MAX_ROW = 88;
const MIN_NAME_SIZE = 16;

const HEADLINE = 'How often each party voted the way I would have';
const INDEPENDENT = 'Independent. Not affiliated with any party or the Parliament.';

const font = (weight, size) => `${weight} ${size}px ${FONT_STACK}`;
const clamp = (value, min, max) => Math.min(max, Math.max(min, value));

/** Breaks text into lines no wider than maxWidth. A word too long for a line gets a line to itself. */
export function wrapText(text, fontString, maxWidth, measure) {
    const lines = [];
    let line = '';

    for (const word of text.split(/\s+/).filter(Boolean)) {
        const attempt = line ? `${line} ${word}` : word;

        if (line && measure(attempt, fontString) > maxWidth) {
            lines.push(line);
            line = word;
        } else {
            line = attempt;
        }
    }

    if (line) {
        lines.push(line);
    }

    return lines;
}

/**
 * @param {object} input
 * @param {{name: string, percent: number, sharedText: string, colour: string}[]} input.ranked  parties with a result, best match first
 * @param {string[]} input.notEnough  parties without enough shared votes
 * @param {{comparable: number, dataAsOf: ?string, authorisation: ?string, stripe: string[]}} input.meta
 * @param {(text: string, font: string) => number} input.measure
 */
export function buildCardLayout({ ranked, notEnough, meta, measure }) {
    const ops = [];
    const rect = (role, x, y, w, h, fill, radius = 0) => ops.push({ type: 'rect', role, x, y, w, h, fill, radius });
    const text = (role, x, y, string, fontString, fill, align = 'left') => ops.push({ type: 'text', role, x, y, text: string, font: fontString, fill, align });
    const { ground, ink, muted, rule, bar } = CARD_COLOURS;

    rect('background', 0, 0, CARD_WIDTH, CARD_HEIGHT, ground);

    // Header
    const wordmarkFont = font(600, 28);
    text('wordmark', PAD, 92, SITE_NAME, wordmarkFont, ink);
    text('edition', PAD + measure(SITE_NAME, wordmarkFont) + 12, 92, 'VICTORIA 2026', font(500, 20), muted);
    text('label', CARD_WIDTH - PAD, 92, 'MY RESULTS', font(500, 20), muted, 'right');

    // Headline and the line under it
    rect('rule', PAD, 150, 96, 3, ink);
    const headlineTop = 177;
    const headlineLines = wrapText(HEADLINE, font(600, 64), 900, measure);
    headlineLines.forEach((line, index) => text('headline', PAD, headlineTop + index * 68 + 52, line, font(600, 64), ink));

    const subTop = headlineTop + headlineLines.length * 68 + 24;
    text('subhead', PAD, subTop + 26, `Based on my ${meta.comparable} yes or no answers.`, font(400, 26), muted);

    const rowsTop = subTop + 34 + 40;

    // Footer, worked out from the bottom so the ladder knows how much room it has
    const authorisationLines = meta.authorisation ? wrapText(meta.authorisation, font(400, 17), INNER, measure) : [];
    const hostFont = font(600, 24);
    const independentFont = font(400, 20);
    const sideBySide = measure(meta.host, hostFont) + measure(INDEPENDENT, independentFont) + 40 <= INNER;
    const footerRowsHeight = sideBySide ? 30 : 30 + 28;
    const footerHeight = 1 + 20 + footerRowsHeight + (authorisationLines.length ? 8 + authorisationLines.length * 24 : 0);
    const footerTop = CARD_HEIGHT - STRIPE_HEIGHT - 36 - footerHeight;

    const scopeText = `Compared with how parties voted in the 60th Parliament (2022–2026), not with their promises for the 2026 election.${meta.dataAsOf ? ` Voting records up to ${meta.dataAsOf}.` : ''}`;
    const scopeLines = wrapText(scopeText, font(400, 22), INNER, measure);
    const scopeTop = footerTop - 20 - scopeLines.length * 30;

    const noteLines = notEnough.length ? wrapText(`Not enough shared votes: ${notEnough.join(', ')}.`, font(400, 22), INNER, measure) : [];
    const noteHeight = noteLines.length ? 24 + noteLines.length * 30 : 0;

    // The ladder: as tall a row as fits, never taller than the design
    const budget = scopeTop - 20 - rowsTop - 2 - noteHeight;
    const rowHeight = clamp(Math.floor(budget / Math.max(1, ranked.length)), MIN_ROW, MAX_ROW);
    const overflow = ranked.length * MIN_ROW > budget;
    const rowsBottom = rowsTop + 2 + ranked.length * rowHeight;

    rect('ladder-rule', PAD, rowsTop, INNER, 2, ink);

    const scale = Math.min(1, rowHeight / 84);
    const nameSize = Math.max(MIN_NAME_SIZE, Math.round(28 * scale));
    const percentSize = Math.max(18, Math.round(32 * scale));
    const countSize = Math.max(14, Math.round(22 * scale));
    const barHeight = Math.max(6, Math.round(10 * scale));
    const swatch = Math.max(10, Math.round(16 * scale));
    const nameLine = Math.round(34 * scale);
    const gap = Math.round(10 * scale);
    const padding = Math.max(0, Math.floor((rowHeight - 1 - (nameLine + gap + barHeight)) / 2));
    const countWidth = Math.round(84 * scale);
    const percentRight = CARD_WIDTH - PAD - countWidth - 14;
    const nameX = PAD + swatch + 14;
    const nameMax = percentRight - Math.round(96 * scale) - nameX - 16;

    ranked.forEach((party, index) => {
        const top = rowsTop + 2 + index * rowHeight;
        const middle = top + padding + nameLine / 2;

        let size = nameSize;

        while (size > MIN_NAME_SIZE && measure(party.name, font(500, size)) > nameMax) {
            size--;
        }

        rect('swatch', PAD, top + padding + (nameLine - swatch) / 2, swatch, swatch, party.colour, 3);
        text('party-name', nameX, middle + size * 0.32, party.name, font(500, size), ink);
        text('party-percent', percentRight, middle + percentSize * 0.32, `${party.percent}%`, font(600, percentSize), ink, 'right');
        text('party-count', CARD_WIDTH - PAD, middle + countSize * 0.32, party.sharedText, font(400, countSize), muted, 'right');

        const barTop = top + padding + nameLine + gap;
        const fillWidth = (INNER * clamp(party.percent, 0, 100)) / 100;

        rect('bar-track', PAD, barTop, INNER, barHeight, rule, barHeight / 2);
        rect('bar-fill', PAD, barTop, fillWidth, barHeight, bar, barHeight / 2);
        rect('row-rule', PAD, top + rowHeight - 1, INNER, 1, rule);
    });

    // Parties without enough shared votes: the label in grey, the names in ink
    const noteTop = rowsBottom + 24;
    const label = 'Not enough shared votes: ';

    noteLines.forEach((line, index) => {
        const baseline = noteTop + index * 30 + 22;

        if (index === 0 && line.startsWith(label)) {
            text('note-label', PAD, baseline, label, font(400, 22), muted);
            text('note-names', PAD + measure(label, font(400, 22)), baseline, line.slice(label.length), font(400, 22), ink);
        } else {
            text('note-names', PAD, baseline, line, font(400, 22), ink);
        }
    });

    scopeLines.forEach((line, index) => text('scope', PAD, scopeTop + index * 30 + 22, line, font(400, 22), ink));

    // Footer
    rect('footer-rule', PAD, footerTop, INNER, 1, rule);
    const rowBaseline = footerTop + 1 + 20 + 24;
    text('host', PAD, rowBaseline, meta.host, hostFont, ink);
    text('independent', sideBySide ? CARD_WIDTH - PAD : PAD, sideBySide ? rowBaseline : rowBaseline + 28, INDEPENDENT, independentFont, muted, sideBySide ? 'right' : 'left');

    const authorisationTop = footerTop + 1 + 20 + footerRowsHeight + 8;
    authorisationLines.forEach((line, index) => text('authorisation', PAD, authorisationTop + index * 24 + 17, line, font(400, 17), muted));

    // The party stripe
    const segment = CARD_WIDTH / STRIPE_CODES.length;
    STRIPE_CODES.forEach((code, index) => rect('stripe', index * segment, CARD_HEIGHT - STRIPE_HEIGHT, segment, STRIPE_HEIGHT, meta.stripe[index] ?? bar));

    return { width: CARD_WIDTH, height: CARD_HEIGHT, ops, rowHeight, rowsBottom, footerTop, overflow };
}
