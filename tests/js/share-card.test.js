import { describe, expect, it } from 'vitest';
import {
    CARD_COLOURS,
    CARD_HEIGHT,
    CARD_WIDTH,
    FONT_STACK,
    STRIPE_CODES,
    buildCardLayout,
    wrapText,
} from '../../resources/js/share-card.js';

/** A stand-in for canvas text measuring: width grows with the characters and the font size. */
const measure = (text, font) => text.length * Number(/(\d+(?:\.\d+)?)px/.exec(font)[1]) * 0.5;

const stripe = STRIPE_CODES.map((code, index) => `#${(index + 1).toString(16).repeat(6)}`);

const parties = [
    ['Nationals', 71, '10/14'],
    ['Greens', 64, '9/14'],
    ['Labor', 57, '8/14'],
    ['Liberal', 50, '7/14'],
    ['Legalise Cannabis', 43, '6/14'],
    ['Libertarian', 38, '5/13'],
    ['Animal Justice', 36, '4/11'],
    ['Democratic Labour', 33, '4/12'],
    ['Family First', 29, '4/14'],
    ['Shooters, Fishers and Farmers', 26, '3/14'],
    ['One Nation', 24, '3/14'],
    ['Fusion', 20, '3/13'],
    ['Socialist Alliance', 18, '3/12'],
    ['Sustainable Australia', 15, '3/12'],
];

const ranked = (count) => parties.slice(0, count).map(([name, percent, sharedText]) => ({
    name,
    percent,
    sharedText,
    colour: '#336699',
}));

const baseMeta = {
    comparable: 14,
    dataAsOf: '3 November 2026',
    host: 'dotheyrepresentme.com',
    authorisation: null,
    stripe,
};

const layoutFor = (count, overrides = {}) => buildCardLayout({
    ranked: ranked(count),
    notEnough: ['Animal Justice', 'Democratic Labour', 'Family First', 'One Nation', 'Shooters, Fishers and Farmers'],
    meta: baseMeta,
    measure,
    ...overrides,
});

const texts = (layout) => layout.ops.filter((op) => op.type === 'text');
const rects = (layout) => layout.ops.filter((op) => op.type === 'rect');
const allText = (layout) => texts(layout).map((op) => op.text).join('\n').replace(/\s+/g, ' ');

describe('wrapText', () => {
    it('breaks on spaces to fit the width', () => {
        expect(wrapText('one two three four', '20px sans-serif', 120, measure)).toEqual(['one two', 'three four']);
    });

    it('keeps a word that is too long on a line of its own rather than cutting it', () => {
        expect(wrapText('a supercalifragilistic b', '20px sans-serif', 100, measure))
            .toEqual(['a', 'supercalifragilistic', 'b']);
    });

    it('returns no lines for empty text', () => {
        expect(wrapText('', '20px sans-serif', 100, measure)).toEqual([]);
    });
});

describe('buildCardLayout', () => {
    it('makes a 1080 by 1350 card', () => {
        const layout = layoutFor(6);

        expect(layout.width).toBe(CARD_WIDTH);
        expect(layout.height).toBe(CARD_HEIGHT);
        expect([CARD_WIDTH, CARD_HEIGHT]).toEqual([1080, 1350]);
    });

    it('keeps everything inside the card', () => {
        for (const count of [1, 6, 10, 14]) {
            const layout = layoutFor(count);

            for (const op of rects(layout)) {
                expect(op.x).toBeGreaterThanOrEqual(0);
                expect(op.y).toBeGreaterThanOrEqual(0);
                expect(op.x + op.w).toBeLessThanOrEqual(CARD_WIDTH);
                expect(op.y + op.h).toBeLessThanOrEqual(CARD_HEIGHT);
            }

            for (const op of texts(layout)) {
                expect(op.y).toBeLessThanOrEqual(CARD_HEIGHT);
            }
        }
    });

    it('shows every party that has a result, in the order given, with its full name', () => {
        const layout = layoutFor(10);
        const names = texts(layout).filter((op) => op.role === 'party-name').map((op) => op.text);

        expect(names).toEqual(parties.slice(0, 10).map(([name]) => name));
        expect(names).toContain('Shooters, Fishers and Farmers');
        expect(allText(layout)).not.toContain('…');
    });

    it('gives each row its percentage and its count', () => {
        const layout = layoutFor(6);

        expect(texts(layout).filter((op) => op.role === 'party-percent').map((op) => op.text))
            .toEqual(['71%', '64%', '57%', '50%', '43%', '38%']);
        expect(texts(layout).filter((op) => op.role === 'party-count').map((op) => op.text))
            .toEqual(['10/14', '9/14', '8/14', '7/14', '6/14', '5/13']);
    });

    it('draws every bar in the same grey, and only the swatch in the party colour', () => {
        const layout = layoutFor(6);
        const fills = rects(layout).filter((op) => op.role === 'bar-fill');
        const swatches = rects(layout).filter((op) => op.role === 'swatch');

        expect(fills).toHaveLength(6);
        expect(new Set(fills.map((op) => op.fill))).toEqual(new Set([CARD_COLOURS.bar]));
        expect(swatches).toHaveLength(6);
        expect(new Set(swatches.map((op) => op.fill))).toEqual(new Set(['#336699']));
    });

    it('sizes each bar to its percentage, and never past the track', () => {
        const layout = layoutFor(6, { ranked: [
            { name: 'A', percent: 50, sharedText: '1/2', colour: '#111111' },
            { name: 'B', percent: 150, sharedText: '1/2', colour: '#111111' },
            { name: 'C', percent: -20, sharedText: '1/2', colour: '#111111' },
        ] });
        const tracks = rects(layout).filter((op) => op.role === 'bar-track');
        const fills = rects(layout).filter((op) => op.role === 'bar-fill');

        expect(fills[0].w).toBeCloseTo(tracks[0].w / 2);
        expect(fills[1].w).toBeCloseTo(tracks[1].w);
        expect(fills[2].w).toBe(0);
    });

    it('fits ten parties with long names above the footer without flagging a problem', () => {
        const layout = layoutFor(10);

        expect(layout.overflow).toBe(false);
        expect(layout.rowsBottom).toBeLessThanOrEqual(layout.footerTop);
    });

    it('fits fourteen parties by shrinking the rows', () => {
        const typical = layoutFor(6);
        const crowded = layoutFor(14);

        expect(crowded.overflow).toBe(false);
        expect(crowded.rowHeight).toBeLessThan(typical.rowHeight);
        expect(crowded.rowsBottom).toBeLessThanOrEqual(crowded.footerTop);
    });

    it('says so when there are too many rows to fit, rather than drawing over the footer silently', () => {
        const many = Array.from({ length: 40 }, (_, index) => ({ name: `Party ${index}`, percent: 10, sharedText: '3/14', colour: '#111111' }));

        expect(layoutFor(6, { ranked: many }).overflow).toBe(true);
    });

    it('shrinks a name that is too wide rather than cutting it off', () => {
        const longName = 'The Very Long Named Party For Everyone In Victoria And Beyond';
        const layout = layoutFor(6, { ranked: [{ name: longName, percent: 50, sharedText: '3/14', colour: '#111111' }] });
        const name = texts(layout).find((op) => op.role === 'party-name');
        const size = Number(/(\d+)px/.exec(name.font)[1]);

        expect(name.text).toBe(longName);
        expect(size).toBeLessThan(28);
        expect(size).toBeGreaterThanOrEqual(16);
    });

    it('lists the parties without enough shared votes, in full', () => {
        const text = allText(layoutFor(6));

        expect(text).toContain('Not enough shared votes: ');
        expect(text).toContain('Shooters, Fishers and Farmers.');
    });

    it('leaves out the not-enough line when every party has a result', () => {
        expect(allText(layoutFor(6, { notEnough: [] }))).not.toContain('Not enough shared votes');
    });

    it('says what the comparison is, and its date', () => {
        const text = allText(layoutFor(6));

        expect(text).toContain('60th Parliament (2022–2026)');
        expect(text).toContain('not with their promises for the 2026 election');
        expect(text).toContain('Voting records up to 3 November 2026.');
        expect(text).toContain('Based on my 14 yes or no answers.');
    });

    it('leaves the date out when there is none', () => {
        expect(allText(layoutFor(6, { meta: { ...baseMeta, dataAsOf: null } }))).not.toContain('Voting records up to');
    });

    it('says it is independent, and gives the site', () => {
        const text = allText(layoutFor(6));

        expect(text).toContain('dotheyrepresentme.com');
        expect(text).toContain('Independent. Not affiliated with any party or the Parliament.');
    });

    it('prints the authorisation statement when there is one, and only then', () => {
        const statement = 'Authorised by A. Person, 1 Example Street, Melbourne VIC.';

        expect(allText(layoutFor(6, { meta: { ...baseMeta, authorisation: statement } }))).toContain(statement);
        expect(allText(layoutFor(6))).not.toContain('Authorised by');
    });

    it('wraps a long authorisation statement and keeps every word', () => {
        const statement = `Authorised by ${'A Very Long Name '.repeat(8)}of 1 Example Street, Melbourne VIC.`;
        const layout = layoutFor(6, { meta: { ...baseMeta, authorisation: statement } });
        const lines = texts(layout).filter((op) => op.role === 'authorisation');

        expect(lines.length).toBeGreaterThan(1);
        expect(lines.map((op) => op.text).join(' ')).toBe(statement);
        expect(layout.overflow).toBe(false);
    });

    it('keeps the ladder clear of the footer even with a long authorisation statement', () => {
        const statement = `Authorised by ${'A Very Long Name '.repeat(8)}of 1 Example Street, Melbourne VIC.`;
        const layout = layoutFor(10, { meta: { ...baseMeta, authorisation: statement } });

        expect(layout.rowsBottom).toBeLessThanOrEqual(layout.footerTop);
    });

    it('draws the party stripe along the bottom in eleven equal segments', () => {
        const layout = layoutFor(6);
        const segments = rects(layout).filter((op) => op.role === 'stripe');

        expect(segments).toHaveLength(11);
        expect(segments.map((op) => op.fill)).toEqual(stripe);
        expect(segments.at(0).x).toBe(0);
        expect(segments.at(-1).x + segments.at(-1).w).toBeCloseTo(CARD_WIDTH);
        expect(new Set(segments.map((op) => Math.round(op.w)))).toHaveLength(1);
        expect(segments[0].y + segments[0].h).toBe(CARD_HEIGHT);
    });

    it('uses the same stripe order as the site', () => {
        expect(STRIPE_CODES).toEqual(['ajp', 'dlp', 'ffv', 'grn', 'alp', 'lcv', 'lib', 'lbt', 'nat', 'onp', 'sff']);
    });

    it('uses the always-light palette and the system font stack', () => {
        const layout = layoutFor(6);

        expect(rects(layout)[0].fill).toBe(CARD_COLOURS.ground);
        expect(CARD_COLOURS.ground).toBe('#ffffff');
        expect(texts(layout).every((op) => op.font.endsWith(FONT_STACK))).toBe(true);
    });
});
