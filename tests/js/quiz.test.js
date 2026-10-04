import { afterEach, describe, expect, it, vi } from 'vitest';
import {
    MAX_NAME_LENGTH,
    clearInvite,
    compareAnswers,
    consumeArrivedFromQuiz,
    decodeAnswers,
    encodeAnswers,
    friendsFromHash,
    inviteHash,
    ownerNameFromHash,
    isSharedResults,
    loadInvite,
    markArrivedFromQuiz,
    resultsHash,
    sanitiseName,
    saveInvite,
    shareHash,
    yesMeans,
} from '../../resources/js/quiz.js';

afterEach(() => {
    vi.unstubAllGlobals();
});

describe('isSharedResults', () => {
    it('is false when the link has no answers', () => {
        expect(isSharedResults({ 1: 'a' }, {})).toBe(false);
        expect(isSharedResults(null, {})).toBe(false);
    });

    it('is false when the link holds the same answers that are saved here', () => {
        expect(isSharedResults({ 1: 'a', 2: 'd' }, { 1: 'a', 2: 'd' })).toBe(false);
    });

    it('ignores skipped answers, which are never put in a link', () => {
        expect(isSharedResults({ 1: 'a', 2: 's' }, { 1: 'a' })).toBe(false);
    });

    it('is true when the answers differ', () => {
        expect(isSharedResults({ 1: 'a' }, { 1: 'd' })).toBe(true);
    });

    it('is true when nothing is saved here', () => {
        expect(isSharedResults(null, { 1: 'a' })).toBe(true);
        expect(isSharedResults({}, { 1: 'a' })).toBe(true);
    });
});

describe('arrived-from-quiz marker', () => {
    function stubSessionStorage() {
        const store = new Map();
        vi.stubGlobal('sessionStorage', {
            getItem: (key) => store.get(key) ?? null,
            setItem: (key, value) => store.set(key, value),
            removeItem: (key) => store.delete(key),
        });
    }

    it('is seen once and then cleared', () => {
        stubSessionStorage();

        markArrivedFromQuiz();

        expect(consumeArrivedFromQuiz()).toBe(true);
        expect(consumeArrivedFromQuiz()).toBe(false);
    });

    it('is false when nothing was marked', () => {
        stubSessionStorage();

        expect(consumeArrivedFromQuiz()).toBe(false);
    });

    it('copes with storage being unavailable', () => {
        expect(() => markArrivedFromQuiz()).not.toThrow();
        expect(consumeArrivedFromQuiz()).toBe(false);
    });
});

describe('encodeAnswers and decodeAnswers', () => {
    it('round-trips agree, disagree and unsure, and drops skipped', () => {
        const encoded = encodeAnswers({ 1: 'a', 2: 'd', 7: 'u', 9: 's' });

        expect(encoded).toBe('1a.2d.7u');
        expect(decodeAnswers(encoded)).toEqual({ 1: 'a', 2: 'd', 7: 'u' });
    });

    it('ignores anything that is not an id and a valid letter', () => {
        expect(decodeAnswers('1a.x.2z.3d.4')).toEqual({ 1: 'a', 3: 'd' });
        expect(decodeAnswers('1234567a')).toEqual({});
        expect(decodeAnswers(null)).toEqual({});
    });

    it('does not treat inherited object keys as answers', () => {
        expect(encodeAnswers({ 1: 'toString', 2: 'constructor', 3: 'a' })).toBe('3a');
    });
});

describe('yesMeans', () => {
    it('drops the workbook\'s "Agree =" label, which the quiz shows as "Yes ="', () => {
        expect(yesMeans('Agree = opposing the Short Stay Levy Bill 2024 (voting against it).')).toBe('opposing the Short Stay Levy Bill 2024 (voting against it).');
        expect(yesMeans('  agree=supporting the bill')).toBe('supporting the bill');
    });

    it('leaves text without the label unchanged', () => {
        expect(yesMeans('supporting the bill, as agreed = in committee')).toBe('supporting the bill, as agreed = in committee');
    });

    it('returns an empty string when there is no text', () => {
        expect(yesMeans(null)).toBe('');
        expect(yesMeans(undefined)).toBe('');
        expect(yesMeans('Agree = ')).toBe('');
    });
});

describe('sanitiseName', () => {
    it('trims and collapses whitespace', () => {
        expect(sanitiseName('  Sam   Nguyen ')).toBe('Sam Nguyen');
    });

    it('strips control, bidirectional and zero-width characters', () => {
        expect(sanitiseName('Sa\u0000m\u202E\u200B!')).toBe('Sam!');
        expect(sanitiseName('line\nbreak\ttab')).toBe('line break tab');
    });

    it('caps the length by characters, not bytes', () => {
        const long = 'é'.repeat(40);

        expect(Array.from(sanitiseName(long))).toHaveLength(MAX_NAME_LENGTH);
        expect(Array.from(sanitiseName('😀'.repeat(40)))).toHaveLength(MAX_NAME_LENGTH);
    });

    it('returns an empty string for nothing usable', () => {
        expect(sanitiseName(null)).toBe('');
        expect(sanitiseName(undefined)).toBe('');
        expect(sanitiseName('\u200B\u0007')).toBe('');
    });
});

describe('friendsFromHash', () => {
    it('reads one friend with a name', () => {
        expect(friendsFromHash('#f=1a.2d&n=Sam')).toEqual([{ answers: { 1: 'a', 2: 'd' }, name: 'Sam' }]);
    });

    it('reads a friend with no name', () => {
        expect(friendsFromHash('#f=1a.2d')).toEqual([{ answers: { 1: 'a', 2: 'd' }, name: '' }]);
    });

    it('keeps each name with the friend before it, even when some have none', () => {
        const friends = friendsFromHash('#a=1a&f=1a&n=Sam&f=2d&f=3a&n=Priya');

        expect(friends.map((friend) => friend.name)).toEqual(['Sam', '', 'Priya']);
        expect(friends.map((friend) => Object.keys(friend.answers))).toEqual([['1'], ['2'], ['3']]);
    });

    it('decodes percent-encoded names and cleans them', () => {
        expect(friendsFromHash('#f=1a&n=Jo%20%26%20Co%E2%80%AE')[0].name).toBe('Jo & Co');
    });

    it('drops friends with no valid answers', () => {
        expect(friendsFromHash('#f=&n=Nobody')).toEqual([]);
        expect(friendsFromHash('#f=zz.9q&n=Bad')).toEqual([]);
    });

    it('allows at most eight friends', () => {
        const hash = `#${Array.from({ length: 12 }, (_, i) => `f=${i + 1}a`).join('&')}`;

        expect(friendsFromHash(hash)).toHaveLength(8);
    });

    it('returns nothing for an empty or unrelated hash', () => {
        expect(friendsFromHash('')).toEqual([]);
        expect(friendsFromHash('#a=1a.2d&d=albert-park')).toEqual([]);
    });

    it('never throws on hostile input', () => {
        expect(() => friendsFromHash('#f=%E0%A4%A&n=%')).not.toThrow();
        expect(() => friendsFromHash(`#f=${'1a.'.repeat(5000)}`)).not.toThrow();
    });
});

describe('inviteHash and resultsHash', () => {
    it('builds an invite from answers and an optional name', () => {
        expect(inviteHash({ 1: 'a', 2: 'd' }, 'Sam')).toBe('#f=1a.2d&n=Sam');
        expect(inviteHash({ 1: 'a' }, '')).toBe('#f=1a');
        expect(inviteHash({ 1: 'a' }, null)).toBe('#f=1a');
    });

    it('encodes names that would otherwise break the link', () => {
        expect(inviteHash({ 1: 'a' }, 'Jo & Co')).toBe('#f=1a&n=Jo%20%26%20Co');
        expect(friendsFromHash(inviteHash({ 1: 'a' }, 'Jo & Co+1'))[0].name).toBe('Jo & Co+1');
    });

    it('keeps the existing results hash exactly as it was when there are no friends', () => {
        expect(resultsHash({ 1: 'a', 2: 'd' }, 'albert-park')).toBe('#a=1a.2d&d=albert-park');
        expect(resultsHash({ 1: 'a' }, '')).toBe('#a=1a');
    });

    it('adds friends after the district, each with its name', () => {
        const hash = resultsHash({ 1: 'a' }, 'albert-park', [
            { answers: { 1: 'd' }, name: 'Sam' },
            { answers: { 2: 'a' }, name: '' },
        ]);

        expect(hash).toBe('#a=1a&d=albert-park&f=1d&n=Sam&f=2a');
        expect(friendsFromHash(hash)).toEqual([
            { answers: { 1: 'd' }, name: 'Sam' },
            { answers: { 2: 'a' }, name: '' },
        ]);
    });
});

describe('compareAnswers', () => {
    const policies = [{ id: 1 }, { id: 2 }, { id: 3 }, { id: 4 }, { id: 5 }, { id: 6 }];

    it('sorts questions into same, different, and answered by only one side', () => {
        const mine = { 1: 'a', 2: 'd', 3: 'a', 4: 'a', 5: 'u' };
        const theirs = { 1: 'a', 2: 'a', 3: 'd', 5: 'a', 6: 'd' };

        const result = compareAnswers(mine, theirs, policies);

        expect(result.same.map((p) => p.id)).toEqual([1]);
        expect(result.different.map((p) => p.id)).toEqual([2, 3]);
        expect(result.onlyMine.map((p) => p.id)).toEqual([4]);
        expect(result.onlyTheirs.map((p) => p.id)).toEqual([5, 6]);
        expect(result.shared).toBe(3);
        expect(result.percent).toBe(33);
    });

    it('does not count unsure or skipped as an answer on either side', () => {
        const result = compareAnswers({ 1: 'u', 2: 's', 3: 'a' }, { 1: 'a', 2: 'd', 3: 'u' }, policies);

        expect(result.shared).toBe(0);
        expect(result.onlyTheirs.map((p) => p.id)).toEqual([1, 2]);
        expect(result.onlyMine.map((p) => p.id)).toEqual([3]);
    });

    it('keeps the order of the questions, whatever order the answers are in', () => {
        const result = compareAnswers({ 3: 'a', 1: 'a', 2: 'a' }, { 2: 'd', 3: 'd', 1: 'd' }, policies);

        expect(result.different.map((p) => p.id)).toEqual([1, 2, 3]);
    });

    it('ignores answers to questions that are not in the data, such as forged ids', () => {
        const result = compareAnswers({ 1: 'a', 999: 'a' }, { 1: 'a', 999: 'd' }, policies);

        expect(result.shared).toBe(1);
        expect(result.same).toHaveLength(1);
        expect(result.different).toEqual([]);
    });

    it('gives no percentage until enough questions are shared', () => {
        expect(compareAnswers({ 1: 'a', 2: 'a' }, { 1: 'a', 2: 'a' }, policies).percent).toBe(null);
        expect(compareAnswers({ 1: 'a', 2: 'a', 3: 'a' }, { 1: 'a', 2: 'a', 3: 'a' }, policies).percent).toBe(100);
    });

    it('rounds the percentage', () => {
        const result = compareAnswers(
            { 1: 'a', 2: 'a', 3: 'a' },
            { 1: 'a', 2: 'a', 3: 'd' },
            policies,
        );

        expect(result.percent).toBe(67);
    });
});

describe('invite storage', () => {
    function stubSessionStorage(initial = {}) {
        const store = new Map(Object.entries(initial));
        vi.stubGlobal('sessionStorage', {
            getItem: (key) => store.get(key) ?? null,
            setItem: (key, value) => store.set(key, value),
            removeItem: (key) => store.delete(key),
        });

        return store;
    }

    it('keeps an invite for the rest of the tab and clears it', () => {
        stubSessionStorage();

        saveInvite({ answers: { 1: 'a', 2: 'd' }, name: 'Sam' });

        expect(loadInvite()).toEqual({ answers: { 1: 'a', 2: 'd' }, name: 'Sam' });

        clearInvite();

        expect(loadInvite()).toBe(null);
    });

    it('does not trust what it reads back', () => {
        stubSessionStorage({ 'dtrm-invite': JSON.stringify({ answers: { 1: 'a', 2: 'evil', toString: 'a' }, name: 'Sa\u202Em' }) });

        expect(loadInvite()).toEqual({ answers: { 1: 'a' }, name: 'Sam' });
    });

    it('treats malformed or empty storage as no invite', () => {
        stubSessionStorage({ 'dtrm-invite': '{not json' });
        expect(loadInvite()).toBe(null);

        stubSessionStorage({ 'dtrm-invite': JSON.stringify({ answers: {}, name: 'Sam' }) });
        expect(loadInvite()).toBe(null);
    });

    it('copes with storage being unavailable', () => {
        expect(() => saveInvite({ answers: { 1: 'a' }, name: '' })).not.toThrow();
        expect(loadInvite()).toBe(null);
        expect(() => clearInvite()).not.toThrow();
    });
});

describe('ownerNameFromHash and shareHash', () => {
    it('reads the name of the person whose results a link carries', () => {
        expect(ownerNameFromHash('#a=1a.2d&d=albert-park&n=Sam')).toBe('Sam');
        expect(ownerNameFromHash('#a=1a&n=Jo%20%26%20Co')).toBe('Jo & Co');
    });

    it('does not mistake a friend\'s name for the owner\'s', () => {
        expect(ownerNameFromHash('#a=1a&f=1d&n=Sam')).toBe('');
        expect(ownerNameFromHash('#f=1d&n=Sam')).toBe('');
    });

    it('is empty when there is no name', () => {
        expect(ownerNameFromHash('#a=1a.2d')).toBe('');
        expect(ownerNameFromHash('')).toBe('');
    });

    it('builds a results link to share, with the name only if given', () => {
        expect(shareHash({ 1: 'a', 2: 'd' }, 'albert-park', 'Sam')).toBe('#a=1a.2d&d=albert-park&n=Sam');
        expect(shareHash({ 1: 'a' }, '', '')).toBe('#a=1a');
        expect(shareHash({ 1: 'a' }, '', 'Jo & Co')).toBe('#a=1a&n=Jo%20%26%20Co');
    });

    it('round-trips through the parsers', () => {
        const hash = shareHash({ 1: 'a', 2: 'd' }, 'albert-park', 'Sam');

        expect(ownerNameFromHash(hash)).toBe('Sam');
        expect(friendsFromHash(hash)).toEqual([]);
    });
});
