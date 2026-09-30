/**
 * Pure functions for the quiz and results. Answers never leave the browser:
 * they live in localStorage and in the URL fragment, which is not sent to
 * the server.
 */

export const MIN_COMPARABLE_ANSWERS = 5;

export const MIN_SHARED_QUESTIONS = 3;

export const ANSWERS = {
    a: { label: 'Agree', value: 1 },
    d: { label: 'Disagree', value: 0 },
    u: { label: 'Unsure', value: null },
    s: { label: 'Skipped', value: null },
};

/**
 * Serialise answers keyed by stable policy ID, e.g. "1a.2d.7u".
 */
export function encodeAnswers(answers) {
    return Object.entries(answers)
        .filter(([, answer]) => answer in ANSWERS && answer !== 's')
        .map(([id, answer]) => `${id}${answer}`)
        .join('.');
}

export function decodeAnswers(encoded) {
    const answers = {};

    for (const part of (encoded || '').split('.')) {
        const match = /^(\d+)([adu])$/.exec(part);

        if (match) {
            answers[match[1]] = match[2];
        }
    }

    return answers;
}

export function answersFromHash(hash) {
    const params = new URLSearchParams((hash || '').replace(/^#/, ''));

    return decodeAnswers(params.get('a'));
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
 * For every question answered Agree (1) or Disagree (0) where they have a
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
export function resultsHash(answers, district) {
    return `#a=${encodeAnswers(answers)}${district ? `&d=${district}` : ''}`;
}

export function comparableAnswerCount(answers) {
    return Object.values(answers).filter((answer) => answer === 'a' || answer === 'd').length;
}
