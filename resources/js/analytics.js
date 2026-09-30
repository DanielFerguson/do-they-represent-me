/*
 * Anonymous product analytics (PostHog, sent through our own /ingest route).
 *
 * Nothing here may touch window or document when imported, so the tests can
 * run under Node. Two things must stay true, and both are unit-tested:
 *
 *  - Events carry no answers, no party results, no district, no names and no
 *    URL fragment. The page address holds a visitor's answers after the #, so
 *    every URL that leaves the browser is cut back to its origin and path.
 *  - Only the events and properties listed in EVENTS can be sent. Anything
 *    else is dropped before it reaches PostHog.
 */

const MAX_QUEUED = 50;
const OPT_OUT_KEY = 'dtrm-analytics-off';
const SESSION_KEY = 'dtrm-sid';

const PAGE_TYPES = ['home', 'results', 'districts_index', 'district', 'policies_index', 'policy', 'info'];
const SHARE_KINDS = ['results', 'invite', 'district'];
const SHARE_CHANNELS = ['native', 'copy', 'save_image', 'whatsapp', 'sms', 'email', 'facebook', 'x'];
const RESULT_SOURCES = ['quiz', 'shared_link', 'revisit'];
const ANSWERED_BUCKETS = ['5-9', '10-14', '15+'];
const DISTRICT_SOURCES = ['finder', 'results', 'district_page'];

const oneOf = (...allowed) => (value) => allowed.includes(value);
const integer = (min, max) => (value) => Number.isInteger(value) && value >= min && value <= max;

/** Every event we may send, and the properties each may carry. */
const EVENTS = {
    $pageview: { page_type: oneOf(...PAGE_TYPES) },
    quiz_started: { position: integer(1, 99) },
    question_answered: { position: integer(1, 99), total: integer(1, 99) },
    start_again: {},
    results_viewed: { source: oneOf(...RESULT_SOURCES), answered_bucket: oneOf(...ANSWERED_BUCKETS) },
    results_too_few: {},
    share_opened: { kind: oneOf(...SHARE_KINDS) },
    share_action: { kind: oneOf(...SHARE_KINDS), channel: oneOf(...SHARE_CHANNELS) },
    invite_landed: {},
    recipient_banner_shown: {},
    compare_viewed: { group_size: integer(1, 8) },
    compare_cta_clicked: {},
    district_chosen: { source: oneOf(...DISTRICT_SOURCES) },
    finder_used: {},
    district_take_quiz_clicked: {},
};

/**
 * Cuts a URL back to its origin and path. Anything that isn't a URL or a path
 * comes back unchanged.
 */
export function scrubUrl(value) {
    if (typeof value !== 'string') {
        return value;
    }

    if (/^(https?:)?\/\//i.test(value) || value.startsWith('/')) {
        return value.replace(/[?#].*$/s, '');
    }

    return value;
}

function scrubDeep(value) {
    if (typeof value === 'string') {
        return scrubUrl(value);
    }

    if (Array.isArray(value)) {
        return value.map(scrubDeep);
    }

    if (value !== null && typeof value === 'object') {
        return Object.fromEntries(Object.entries(value).map(([key, inner]) => [key, scrubDeep(inner)]));
    }

    return value;
}

/** PostHog's before_send hook: the last line of defence against a leaked fragment. */
export function scrubEvent(event) {
    if (!event) {
        return null;
    }

    return scrubDeep(event);
}

/** The allowlisted, valid properties for an event, or null if the event isn't allowed. */
export function cleanProps(name, props = {}) {
    const schema = EVENTS[name];

    if (!schema) {
        return null;
    }

    const clean = {};

    for (const [key, isValid] of Object.entries(schema)) {
        if (Object.hasOwn(props, key) && isValid(props[key])) {
            clean[key] = props[key];
        }
    }

    return clean;
}

export function answeredBucket(count) {
    if (count >= 15) {
        return '15+';
    }

    return count >= 10 ? '10-14' : '5-9';
}

export function resultsSource({ fromLink, isShared, arrivedFromQuiz }) {
    if (fromLink && isShared) {
        return 'shared_link';
    }

    return arrivedFromQuiz ? 'quiz' : 'revisit';
}

/**
 * Builds a tracker. Events fired before the client has loaded are queued (up
 * to 50) and sent in order once it has. If analytics is off, or the client
 * fails to load, tracking silently does nothing.
 */
export function createAnalytics({ loadClient, isDisabled, sessionId, location }) {
    let client = null;
    let disabled = false;
    let queue = [];

    function send(name, props) {
        if (disabled) {
            return;
        }

        if (client) {
            client.capture(name, props);
        } else if (queue.length < MAX_QUEUED) {
            queue.push([name, props]);
        }
    }

    return {
        async init({ key }) {
            if (!key || isDisabled()) {
                disabled = true;
                queue = [];

                return;
            }

            try {
                const loaded = await loadClient();

                loaded.init(key, {
                    api_host: '/ingest',
                    ui_host: 'https://us.posthog.com',
                    persistence: 'memory',
                    person_profiles: 'identified_only',
                    bootstrap: { distinctID: sessionId() },
                    autocapture: false,
                    capture_pageview: false,
                    capture_dead_clicks: false,
                    capture_heatmaps: false,
                    capture_exceptions: false,
                    disable_session_recording: true,
                    disable_surveys: true,
                    disable_external_dependency_loading: true,
                    advanced_disable_flags: true,
                    advanced_disable_feature_flags: true,
                    disable_capture_url_hashes: true,
                    respect_dnt: true,
                    before_send: scrubEvent,
                });

                client = loaded;

                for (const [name, props] of queue) {
                    client.capture(name, props);
                }
            } catch {
                disabled = true;
            }

            queue = [];
        },

        track(name, props = {}) {
            const clean = cleanProps(name, props);

            if (clean) {
                send(name, clean);
            }
        },

        pageview(pageType) {
            const { origin, pathname } = location();

            send('$pageview', { ...cleanProps('$pageview', { page_type: pageType }), $current_url: `${origin}${pathname}` });
        },
    };
}

function readStorage(storage, key) {
    try {
        return window[storage].getItem(key);
    } catch {
        return null;
    }
}

function writeStorage(storage, key, value) {
    try {
        window[storage].setItem(key, value);
    } catch {
        // Storage can be blocked. Analytics just carries on without it.
    }
}

/** A random id that lives as long as the browser tab, so one visit's events join up. */
export function browserSessionId() {
    const existing = readStorage('sessionStorage', SESSION_KEY);

    if (existing) {
        return existing;
    }

    const id = globalThis.crypto?.randomUUID?.() ?? `${Date.now()}-${Math.random().toString(16).slice(2)}`;
    writeStorage('sessionStorage', SESSION_KEY, id);

    return id;
}

export function isOptedOut() {
    return readStorage('localStorage', OPT_OUT_KEY) === '1';
}

export function optOut() {
    writeStorage('localStorage', OPT_OUT_KEY, '1');
}

export function optIn() {
    try {
        window.localStorage.removeItem(OPT_OUT_KEY);
    } catch {
        // Nothing to undo if storage is blocked.
    }
}

/** The browser asking not to be tracked: Do Not Track or Global Privacy Control. */
export function browserSignalsOptOut() {
    return navigator.doNotTrack === '1'
        || window.doNotTrack === '1'
        || navigator.globalPrivacyControl === true;
}

/** That signal, or the visitor's own opt-out on the privacy page. */
export function browserIsDisabled() {
    return browserSignalsOptOut() || isOptedOut();
}

async function loadPostHog() {
    const module = await import('posthog-js/dist/module.slim.no-external');

    return module.default;
}

/** The analytics the pages use. Created on first use so nothing runs at import time. */
let shared = null;

export function analytics() {
    shared ??= createAnalytics({
        loadClient: loadPostHog,
        isDisabled: browserIsDisabled,
        sessionId: browserSessionId,
        location: () => window.location,
    });

    return shared;
}

let started = false;

/** Does nothing until startAnalytics has run, so pages that don't count visits queue nothing. */
export function track(name, props) {
    if (started) {
        analytics().track(name, props);
    }
}

/** Starts analytics once the browser is idle, so it never competes with the page. */
export function startAnalytics({ key, pageType }) {
    if (!key) {
        return;
    }

    started = true;
    analytics().pageview(pageType);

    const start = () => analytics().init({ key });

    if ('requestIdleCallback' in window) {
        window.requestIdleCallback(start, { timeout: 3000 });
    } else {
        window.setTimeout(start, 1500);
    }
}
