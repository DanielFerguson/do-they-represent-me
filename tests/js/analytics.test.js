import { describe, expect, it, vi } from 'vitest';
import {
    cleanProps,
    createAnalytics,
    resultsSource,
    answeredBucket,
    scrubEvent,
    scrubUrl,
} from '../../resources/js/analytics.js';

describe('scrubUrl', () => {
    it('drops the fragment and the query from absolute URLs', () => {
        expect(scrubUrl('https://dotheyrepresentme.com/results?x=1#a=1a.2d.7u&d=albert-park'))
            .toBe('https://dotheyrepresentme.com/results');
    });

    it('drops the fragment and the query from relative paths', () => {
        expect(scrubUrl('/results#a=1a')).toBe('/results');
        expect(scrubUrl('/districts/albert-park?signature=abc')).toBe('/districts/albert-park');
    });

    it('leaves values that are not URLs alone', () => {
        expect(scrubUrl('Nationals')).toBe('Nationals');
        expect(scrubUrl(12)).toBe(12);
        expect(scrubUrl(null)).toBe(null);
    });
});

describe('scrubEvent', () => {
    const leaky = () => ({
        event: '$pageview',
        properties: {
            $current_url: 'https://dotheyrepresentme.com/results#a=1a.2d',
            $initial_current_url: 'https://dotheyrepresentme.com/#f=1a&n=Sam',
            $session_entry_url: 'https://dotheyrepresentme.com/results?x=1#a=1a',
            $referrer: 'https://example.com/page?utm=1#frag',
            $set: { $current_url: 'https://dotheyrepresentme.com/results#a=1a' },
            $set_once: { $initial_current_url: 'https://dotheyrepresentme.com/results#a=1a' },
            page_type: 'results',
        },
        $set: { $initial_referrer: 'https://example.com/x#a=1a' },
    });

    it('removes fragments and queries from every URL-like property, including $set and $set_once', () => {
        const event = scrubEvent(leaky());
        const serialised = JSON.stringify(event);

        expect(serialised).not.toContain('#');
        expect(serialised).not.toContain('?');
        expect(event.properties.$current_url).toBe('https://dotheyrepresentme.com/results');
        expect(event.properties.$referrer).toBe('https://example.com/page');
        expect(event.properties.page_type).toBe('results');
    });

    it('never leaks the answer hash, however deeply it is nested', () => {
        const event = scrubEvent({
            event: 'x',
            properties: { nested: { deeper: ['https://dotheyrepresentme.com/results#a=1a.2d'] } },
        });

        expect(JSON.stringify(event)).not.toContain('#a=');
    });

    it('returns null for a missing event', () => {
        expect(scrubEvent(null)).toBe(null);
    });
});

describe('cleanProps', () => {
    it('keeps allowlisted properties with valid values', () => {
        expect(cleanProps('question_answered', { position: 3, total: 22 }))
            .toEqual({ position: 3, total: 22 });
        expect(cleanProps('share_action', { kind: 'results', channel: 'whatsapp' }))
            .toEqual({ kind: 'results', channel: 'whatsapp' });
    });

    it('drops properties that are not allowlisted', () => {
        expect(cleanProps('question_answered', { position: 3, total: 22, answer: 'a', district: 'albert-park' }))
            .toEqual({ position: 3, total: 22 });
    });

    it('drops values outside the allowed set', () => {
        expect(cleanProps('share_action', { kind: 'results', channel: 'albert-park' }))
            .toEqual({ kind: 'results' });
        expect(cleanProps('question_answered', { position: 'Nationals', total: 22 }))
            .toEqual({ total: 22 });
        expect(cleanProps('compare_viewed', { group_size: 99 })).toEqual({});
    });

    it('returns null for an unknown event', () => {
        expect(cleanProps('party_matched', { party: 'ALP' })).toBe(null);
    });
});

describe('answeredBucket and resultsSource', () => {
    it('buckets answer counts so the exact number is not sent', () => {
        expect(answeredBucket(5)).toBe('5-9');
        expect(answeredBucket(9)).toBe('5-9');
        expect(answeredBucket(10)).toBe('10-14');
        expect(answeredBucket(14)).toBe('10-14');
        expect(answeredBucket(15)).toBe('15+');
        expect(answeredBucket(22)).toBe('15+');
    });

    it('labels how the visitor reached their results', () => {
        expect(resultsSource({ fromLink: true, isShared: true, arrivedFromQuiz: false })).toBe('shared_link');
        expect(resultsSource({ fromLink: true, isShared: false, arrivedFromQuiz: true })).toBe('quiz');
        expect(resultsSource({ fromLink: false, isShared: false, arrivedFromQuiz: false })).toBe('revisit');
    });
});

describe('createAnalytics', () => {
    const makeClient = () => ({ init: vi.fn(), capture: vi.fn() });
    const make = (overrides = {}) => {
        const client = makeClient();
        const analytics = createAnalytics({
            loadClient: async () => client,
            isDisabled: () => false,
            sessionId: () => 'session-1',
            location: () => ({ origin: 'https://dotheyrepresentme.com', pathname: '/results' }),
            ...overrides,
        });

        return { client, analytics };
    };

    it('does not load or initialise anything without a key', async () => {
        const loadClient = vi.fn();
        const { analytics } = make({ loadClient });

        await analytics.init({ key: '' });
        analytics.track('start_again');

        expect(loadClient).not.toHaveBeenCalled();
    });

    it('does not load anything when tracking is disabled (DNT, GPC or opt-out)', async () => {
        const loadClient = vi.fn();
        const { analytics } = make({ loadClient, isDisabled: () => true });

        await analytics.init({ key: 'phc_test' });
        analytics.track('start_again');

        expect(loadClient).not.toHaveBeenCalled();
    });

    it('initialises the client with the privacy settings and the per-tab session id', async () => {
        const { client, analytics } = make();

        await analytics.init({ key: 'phc_test' });

        expect(client.init).toHaveBeenCalledTimes(1);
        const [key, config] = client.init.mock.calls[0];
        expect(key).toBe('phc_test');
        expect(config).toMatchObject({
            api_host: '/ingest',
            persistence: 'memory',
            person_profiles: 'identified_only',
            autocapture: false,
            capture_pageview: false,
            disable_session_recording: true,
            disable_external_dependency_loading: true,
            advanced_disable_flags: true,
            advanced_disable_feature_flags: true,
            bootstrap: { distinctID: 'session-1' },
        });
        expect(config.before_send).toBe(scrubEvent);
    });

    it('queues events fired before the client has loaded, then sends them in order', async () => {
        const { client, analytics } = make();

        analytics.track('question_answered', { position: 1, total: 22 });
        analytics.track('question_answered', { position: 2, total: 22 });
        expect(client.capture).not.toHaveBeenCalled();

        await analytics.init({ key: 'phc_test' });

        expect(client.capture.mock.calls).toEqual([
            ['question_answered', { position: 1, total: 22 }],
            ['question_answered', { position: 2, total: 22 }],
        ]);
    });

    it('caps the queue at 50 events', async () => {
        const { client, analytics } = make();

        for (let i = 0; i < 80; i++) {
            analytics.track('start_again');
        }
        await analytics.init({ key: 'phc_test' });

        expect(client.capture).toHaveBeenCalledTimes(50);
    });

    it('drops unknown events and strips unknown properties before sending', async () => {
        const { client, analytics } = make();
        await analytics.init({ key: 'phc_test' });

        analytics.track('party_matched', { party: 'ALP' });
        analytics.track('question_answered', { position: 4, total: 22, answer: 'd' });

        expect(client.capture.mock.calls).toEqual([
            ['question_answered', { position: 4, total: 22 }],
        ]);
    });

    it('sends a page view with only the origin and path as the URL', async () => {
        const { client, analytics } = make({
            location: () => ({ origin: 'https://dotheyrepresentme.com', pathname: '/results' }),
        });
        await analytics.init({ key: 'phc_test' });

        analytics.pageview('results');

        expect(client.capture).toHaveBeenCalledWith('$pageview', {
            $current_url: 'https://dotheyrepresentme.com/results',
            page_type: 'results',
        });
    });

    it('ignores a page view with an unknown page type', async () => {
        const { client, analytics } = make();
        await analytics.init({ key: 'phc_test' });

        analytics.pageview('albert-park');

        expect(client.capture.mock.calls[0][1]).toEqual({
            $current_url: 'https://dotheyrepresentme.com/results',
        });
    });

    it('keeps working if the client fails to load', async () => {
        const { analytics } = make({ loadClient: async () => { throw new Error('blocked'); } });

        await expect(analytics.init({ key: 'phc_test' })).resolves.toBeUndefined();
        expect(() => analytics.track('start_again')).not.toThrow();
    });
});
