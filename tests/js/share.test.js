import { describe, expect, it, vi } from 'vitest';
import {
    CHANNELS,
    RESULTS_TEXT,
    canShareFiles,
    canShareLink,
    channelLinks,
    channelUrl,
    districtText,
    inviteText,
    withName,
} from '../../resources/js/share.js';

const url = 'https://dotheyrepresentme.com/results#a=1a.2d&d=albert-park&n=Sam';
const text = "Here's how Victoria's parties voted compared with my answers. — Sam";

describe('channelUrl', () => {
    it('builds a WhatsApp link with the text and link together', () => {
        expect(channelUrl('whatsapp', { text, url }))
            .toBe(`https://wa.me/?text=${encodeURIComponent(`${text} ${url}`)}`);
    });

    it('builds an X link with the text and the link separately', () => {
        const link = new URL(channelUrl('x', { text, url }));

        expect(link.origin + link.pathname).toBe('https://x.com/intent/post');
        expect(link.searchParams.get('text')).toBe(text);
        expect(link.searchParams.get('url')).toBe(url);
    });

    it('builds a Facebook link with only the URL, since it ignores prefilled text', () => {
        const link = new URL(channelUrl('facebook', { text, url }));

        expect(link.searchParams.get('u')).toBe(url);
        expect([...link.searchParams.keys()]).toEqual(['u']);
    });

    it('builds an email with a subject and the text and link in the body', () => {
        const link = channelUrl('email', { text, url });
        const query = new URLSearchParams(link.slice('mailto:?'.length));

        expect(link.startsWith('mailto:?')).toBe(true);
        expect(query.get('subject')).toBe('Do They Represent Me?');
        expect(query.get('body')).toBe(`${text}\n\n${url}`);
    });

    it('builds an SMS link that opens on both iPhone and Android', () => {
        const link = channelUrl('sms', { text, url });

        expect(link.startsWith('sms:?&body=')).toBe(true);
        expect(decodeURIComponent(link.slice('sms:?&body='.length))).toBe(`${text} ${url}`);
    });

    it('keeps characters that would break a URL out of it', () => {
        const link = channelUrl('whatsapp', { text: 'A & B #1?', url: 'https://x.test/?a=1&b=2#c' });

        expect(link).not.toContain('#');
        expect(link).not.toContain(' ');
        expect(link.split('?')).toHaveLength(2);
    });

    it('has no link for channels that are not links', () => {
        for (const channel of ['copy', 'native', 'save_image', 'nope']) {
            expect(channelUrl(channel, { text, url })).toBe(null);
        }
    });

    it('lists the link channels the sheet shows, each with a link builder', () => {
        expect(CHANNELS.map((channel) => channel.id)).toEqual(['whatsapp', 'sms', 'email', 'facebook', 'x']);

        for (const channel of CHANNELS) {
            expect(channelUrl(channel.id, { text, url })).toEqual(expect.any(String));
        }
    });
});

describe('messages', () => {
    it('does not name a party in any message', () => {
        const messages = [RESULTS_TEXT, inviteText(22), districtText('Albert Park')].join(' ');

        for (const party of ['Labor', 'Liberal', 'Greens', 'Nationals']) {
            expect(messages).not.toContain(party);
        }
    });

    it('says how many questions the quiz has', () => {
        expect(inviteText(22)).toContain('22 questions');
        expect(inviteText(0)).not.toContain('0 questions');
    });

    it('adds a name after the message, cleaned, only if there is one', () => {
        expect(withName(RESULTS_TEXT, 'Sam')).toBe(`${RESULTS_TEXT} — Sam`);
        expect(withName(RESULTS_TEXT, '  Sam‮  ')).toBe(`${RESULTS_TEXT} — Sam`);
        expect(withName(RESULTS_TEXT, '')).toBe(RESULTS_TEXT);
        expect(withName(RESULTS_TEXT, null)).toBe(RESULTS_TEXT);
    });

    it('names the district in the district message', () => {
        expect(districtText('Albert Park')).toContain('Albert Park District');
    });
});

describe('native sharing support', () => {
    const file = { name: 'my-results.png' };

    it('needs share and canShare to share a file', () => {
        expect(canShareFiles({ share: vi.fn(), canShare: () => true }, file)).toBe(true);
        expect(canShareFiles({ share: vi.fn(), canShare: () => false }, file)).toBe(false);
        expect(canShareFiles({ share: vi.fn() }, file)).toBe(false);
        expect(canShareFiles({}, file)).toBe(false);
        expect(canShareFiles(undefined, file)).toBe(false);
    });

    it('needs only share to share a link', () => {
        expect(canShareLink({ share: vi.fn() })).toBe(true);
        expect(canShareLink({})).toBe(false);
        expect(canShareLink(undefined)).toBe(false);
    });
});

describe('channelLinks', () => {
    it('gives every channel its label and link', () => {
        const links = channelLinks(text, url);

        expect(links.map((link) => link.label)).toEqual(['WhatsApp', 'Messages', 'Email', 'Facebook', 'X']);
        expect(links.every((link) => typeof link.url === 'string' && link.url.length > 0)).toBe(true);
    });

    it('opens web links in a new tab, and leaves the email and message apps to open themselves', () => {
        const byId = Object.fromEntries(channelLinks(text, url).map((link) => [link.id, link]));

        expect(byId.whatsapp.target).toBe('_blank');
        expect(byId.facebook.target).toBe('_blank');
        expect(byId.x.target).toBe('_blank');
        expect(byId.sms.target).toBe(null);
        expect(byId.email.target).toBe(null);
    });
});
