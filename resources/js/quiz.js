/**
 * Pure functions for the quiz and results. Answers never leave the browser:
 * they live in localStorage and in the URL fragment, which is not sent to
 * the server.
 */

export const MIN_COMPARABLE_ANSWERS = 5;

export const MIN_SHARED_QUESTIONS = 3;

/** The longest name we put in a link or show from one. */
export const MAX_NAME_LENGTH = 24;

/** The most people one link can compare, counting the friends and not the owner. */
const MAX_FRIENDS = 8;

/**
 * The codes are still "a" and "d" (agree and disagree, the scoring's terms),
 * so answers saved before the quiz said Yes and No, and links shared then,
 * keep working.
 */
export const ANSWERS = {
    a: { label: 'Yes', value: 1 },
    d: { label: 'No', value: 0 },
    u: { label: 'Unsure', value: null },
    s: { label: 'Skipped', value: null },
};

/**
 * Serialise answers keyed by stable policy ID, e.g. "1a.2d.7u".
 */
export function encodeAnswers(answers) {
    return Object.entries(answers)
        .filter(([, answer]) => Object.hasOwn(ANSWERS, answer) && answer !== 's')
        .map(([id, answer]) => `${id}${answer}`)
        .join('.');
}

export function decodeAnswers(encoded) {
    const answers = {};

    for (const part of (encoded || '').split('.')) {
        const match = /^(\d{1,6})([adu])$/.exec(part);

        if (match) {
            answers[match[1]] = match[2];
        }
    }

    return answers;
}

/**
 * What a "yes" answer lines up with, from the workbook's "Agree" means text,
 * which starts with its own "Agree =" label.
 */
export function yesMeans(agreeMeans) {
    return String(agreeMeans ?? '').replace(/^\s*agree\s*=\s*/i, '').trim();
}

export function answersFromHash(hash) {
    const params = new URLSearchParams((hash || '').replace(/^#/, ''));

    return decodeAnswers(params.get('a'));
}

/**
 * A name typed by someone, for putting in a link and showing to someone else:
 * no control, bidirectional or zero-width characters, single spaces, and short.
 */
export function sanitiseName(raw) {
    const cleaned = String(raw ?? '')
        .replace(/\s+/gu, ' ')
        .replace(/[\p{Cc}\p{Cf}]/gu, '')
        .trim();

    return Array.from(cleaned).slice(0, MAX_NAME_LENGTH).join('').trim();
}

/**
 * The friends whose answers a link carries: each `f` is one friend's answers,
 * and the `n` after it is their name. Friends without valid answers are dropped.
 */
export function friendsFromHash(hash) {
    const friends = [];

    for (const [key, value] of new URLSearchParams((hash || '').replace(/^#/, ''))) {
        if (key === 'f') {
            friends.push({ answers: decodeAnswers(value), name: '' });
        } else if (key === 'n' && friends.length && friends.at(-1).name === '') {
            friends.at(-1).name = sanitiseName(value);
        }
    }

    return friends.filter((friend) => Object.keys(friend.answers).length > 0).slice(0, MAX_FRIENDS);
}

/** An invitation to compare: the sender's answers, and their name if they gave one. */
export function inviteHash(answers, name) {
    return `#${friendParams({ answers, name })}`;
}

/** The name of the person whose results a link carries: an `n` that comes before any friend. */
export function ownerNameFromHash(hash) {
    for (const [key, value] of new URLSearchParams((hash || '').replace(/^#/, ''))) {
        if (key === 'f') {
            return '';
        }

        if (key === 'n') {
            return sanitiseName(value);
        }
    }

    return '';
}

function friendParams({ answers, name }) {
    const clean = sanitiseName(name);

    return `f=${encodeAnswers(answers)}${clean ? `&n=${encodeURIComponent(clean)}` : ''}`;
}

export function saveProgress(key, version, answers, index) {
    try {
        localStorage.setItem(key, JSON.stringify({ version, answers, index }));
    } catch {
        // Storage can be unavailable (private browsing); progress just won't persist.
    }
}

/**
 * Saved answers are keyed by stable policy IDs, so they survive new versions
 * of the data; only the position in the quiz is reset.
 */
export function loadProgress(key, version) {
    try {
        const saved = JSON.parse(localStorage.getItem(key) || 'null');

        if (!saved || typeof saved.answers !== 'object' || saved.answers === null) {
            return null;
        }

        return { answers: saved.answers, index: saved.version === version ? saved.index : 0 };
    } catch {
        return null;
    }
}

export function clearProgress(key) {
    try {
        localStorage.removeItem(key);
    } catch {
        // Nothing to clear.
    }
}

/**
 * How a party voted on a policy, in plain words: a reviewers' note where one
 * replaces the figure, otherwise the label from the published data. Data
 * without labels (the prototype sample) falls back to the figure.
 */
export function stanceText(stance) {
    if (!stance) {
        return 'No voting record';
    }

    return stance.note ?? stance.label ?? stanceLabel(stance.agreement);
}

export function stanceLabel(agreement) {
    if (agreement === null || agreement === undefined) {
        return 'No voting record';
    }

    if (agreement >= 0.6) {
        return 'Voted for';
    }

    if (agreement < 0.4) {
        return 'Voted against';
    }

    return 'Mixed';
}

/**
 * How closely one party or MP matches the user's answers.
 *
 * For every question answered Yes (1) or No (0) where they have a
 * figure, the match is 1 − |answer − agreement|; the score is the mean
 * across those questions. Unsure, skipped and "no record" questions, and
 * questions where a note replaces the figure, are left out.
 */
function matchScore(data, answers, stanceFor) {
    const matches = [];

    for (const policy of data.policies) {
        const answer = ANSWERS[answers[policy.id]]?.value;
        const agreement = stanceFor(policy)?.agreement;

        if (answer === null || answer === undefined || agreement === null || agreement === undefined) {
            continue;
        }

        matches.push(1 - Math.abs(answer - agreement));
    }

    const score = matches.length ? matches.reduce((sum, match) => sum + match, 0) / matches.length : null;

    return { shared: matches.length, score, percent: score === null ? null : Math.round(score * 100) };
}

export function scoreParties(data, answers) {
    return data.parties.map((party) => ({ ...party, ...matchScore(data, answers, (policy) => policy.stances[party.code]) }));
}

export function scoreMembers(data, answers, members) {
    return members.map((member) => ({ ...member, ...matchScore(data, answers, (policy) => policy.members?.[member.slug]) }));
}

/**
 * The MLA for a district and the MLCs for its region, from the published
 * data. Null for an unknown district, or data with no members (the sample).
 */
export function representativesFor(data, districtSlug) {
    const district = (data.districts ?? []).find((candidate) => candidate.slug === districtSlug);

    if (!district || !(data.members ?? []).length) {
        return null;
    }

    return {
        district,
        region: (data.regions ?? []).find((region) => region.slug === district.region) ?? null,
        assembly: data.members.filter((member) => member.electorate === district.slug),
        council: data.members.filter((member) => member.electorate === district.region),
    };
}

export const DISTRICT_KEY = 'dtrm-district';

/**
 * The voter's district, kept in the browser only, like their answers.
 */
export function loadDistrict() {
    try {
        return localStorage.getItem(DISTRICT_KEY);
    } catch {
        return null;
    }
}

export function saveDistrict(slug) {
    try {
        localStorage.setItem(DISTRICT_KEY, slug);
    } catch {
        // Storage can be unavailable (private browsing); the choice just won't persist.
    }
}

export function districtFromHash(hash) {
    const slug = new URLSearchParams((hash || '').replace(/^#/, '')).get('d');

    return slug && /^[a-z0-9-]+$/.test(slug) ? slug : null;
}

/**
 * The part of a results link after the #: answers, and the district if chosen.
 */
export function resultsHash(answers, district, friends = []) {
    return `#a=${encodeAnswers(answers)}${district ? `&d=${district}` : ''}${friends.map((friend) => `&${friendParams(friend)}`).join('')}`;
}

/**
 * A link to share your own results: your answers, your district if chosen, and
 * your name if you gave one. No friends' answers go in it.
 */
export function shareHash(answers, district, name) {
    const clean = sanitiseName(name);

    return `${resultsHash(answers, district)}${clean ? `&n=${encodeURIComponent(clean)}` : ''}`;
}

export function comparableAnswerCount(answers) {
    return Object.values(answers).filter((answer) => answer === 'a' || answer === 'd').length;
}

/**
 * True when a results link carries someone else's answers: it has answers
 * after the #, and they differ from the ones saved in this browser. The sender
 * opening their own link sees the same answers they saved, so it isn't shared.
 */
export function isSharedResults(saved, fromLink) {
    return Object.keys(fromLink).length > 0 && encodeAnswers(saved ?? {}) !== encodeAnswers(fromLink);
}

const FROM_QUIZ_KEY = 'dtrm-from-quiz';

/**
 * Finishing the quiz leaves a one-off marker for the results page, so it can
 * tell someone who has just finished from someone coming back to their results.
 */
export function markArrivedFromQuiz() {
    try {
        sessionStorage.setItem(FROM_QUIZ_KEY, '1');
    } catch {
        // Storage can be unavailable; the visit is then counted as a revisit.
    }
}

export function consumeArrivedFromQuiz() {
    try {
        const arrived = sessionStorage.getItem(FROM_QUIZ_KEY) === '1';
        sessionStorage.removeItem(FROM_QUIZ_KEY);

        return arrived;
    } catch {
        return false;
    }
}

/**
 * How two people's answers compare, question by question, in the order of the
 * questions. Only yes and no count as answers. Answers to questions
 * that aren't in the data are ignored, so a forged link can't add any.
 */
export function compareAnswers(mine, theirs, policies) {
    const result = { same: [], different: [], onlyMine: [], onlyTheirs: [] };
    const answered = (answer) => answer === 'a' || answer === 'd';

    for (const policy of policies) {
        const a = mine[policy.id];
        const b = theirs[policy.id];

        if (answered(a) && answered(b)) {
            (a === b ? result.same : result.different).push(policy);
        } else if (answered(a)) {
            result.onlyMine.push(policy);
        } else if (answered(b)) {
            result.onlyTheirs.push(policy);
        }
    }

    const shared = result.same.length + result.different.length;

    return {
        ...result,
        shared,
        percent: shared >= MIN_SHARED_QUESTIONS ? Math.round((result.same.length / shared) * 100) : null,
    };
}

const INVITE_KEY = 'dtrm-invite';

/**
 * An invitation to compare, kept for the rest of this tab only. It holds
 * another person's answers, so it never goes in localStorage.
 */
export function saveInvite(friend) {
    try {
        sessionStorage.setItem(INVITE_KEY, JSON.stringify({ answers: friend.answers, name: friend.name }));
    } catch {
        // Storage can be unavailable; the invitation then lives only in the link.
    }
}

export function loadInvite() {
    try {
        const saved = JSON.parse(sessionStorage.getItem(INVITE_KEY) || 'null');
        const answers = decodeAnswers(encodeAnswers(saved?.answers ?? {}));

        return Object.keys(answers).length ? { answers, name: sanitiseName(saved.name) } : null;
    } catch {
        return null;
    }
}

export function clearInvite() {
    try {
        sessionStorage.removeItem(INVITE_KEY);
    } catch {
        // Nothing to clear.
    }
}
