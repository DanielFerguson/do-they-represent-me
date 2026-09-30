/**
 * Pure helpers for the share sheets: the messages, and the links that open
 * each app. Nothing here touches window or document, so the tests can run
 * under Node. No message names a party.
 */

import { sanitiseName } from './quiz.js';

export const SITE_NAME = 'Do They Represent Me?';

export const RESULTS_TEXT = "Here's how Victoria's parties voted compared with my answers.";

export function inviteText(questionCount) {
    const questions = questionCount > 0 ? `${questionCount} questions` : 'Questions';

    return `${questions} on real votes in Victoria's Parliament. About 5 minutes. See which parties voted the way you would have.`;
}

export function districtText(districtName) {
    return `Who represents ${districtName} District in Victoria's Parliament, and how they voted.`;
}

/** The message, signed with the sender's name if they gave one. */
export function withName(text, name) {
    const clean = sanitiseName(name);

    return clean ? `${text} — ${clean}` : text;
}

/** The apps the sheet can open with a link. The rest (copy, native, save image) aren't links. */
export const CHANNELS = [
    { id: 'whatsapp', label: 'WhatsApp' },
    { id: 'sms', label: 'Messages' },
    { id: 'email', label: 'Email' },
    { id: 'facebook', label: 'Facebook' },
    { id: 'x', label: 'X' },
];

/**
 * The link that opens an app with the message ready. Facebook ignores any
 * text we send and shows the page's own preview, so it gets only the link.
 */
export function channelUrl(channel, { text, url }) {
    const encode = encodeURIComponent;

    switch (channel) {
        case 'whatsapp':
            return `https://wa.me/?text=${encode(`${text} ${url}`)}`;
        case 'x':
            return `https://x.com/intent/post?text=${encode(text)}&url=${encode(url)}`;
        case 'facebook':
            return `https://www.facebook.com/sharer/sharer.php?u=${encode(url)}`;
        case 'email':
            return `mailto:?subject=${encode(SITE_NAME)}&body=${encode(`${text}\n\n${url}`)}`;
        case 'sms':
            return `sms:?&body=${encode(`${text} ${url}`)}`;
        default:
            return null;
    }
}

/** Each channel the sheet shows, with its link, and a target for links that should open in a new tab. */
export function channelLinks(text, url) {
    return CHANNELS.map((channel) => {
        const href = channelUrl(channel.id, { text, url });

        return { ...channel, url: href, target: href.startsWith('http') ? '_blank' : null };
    });
}

/** Can this browser hand a picture to the phone's share sheet? */
export function canShareFiles(nav, file) {
    return typeof nav?.share === 'function' && typeof nav.canShare === 'function' && nav.canShare({ files: [file] });
}

export function canShareLink(nav) {
    return typeof nav?.share === 'function';
}
