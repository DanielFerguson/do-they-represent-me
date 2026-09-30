import Alpine from '@alpinejs/csp';
import { describeDistricts, prepareLocalities, searchLocalities } from './finder';
import {
    ANSWERS,
    MIN_COMPARABLE_ANSWERS,
    MIN_SHARED_QUESTIONS,
    answersFromHash,
    clearProgress,
    comparableAnswerCount,
    districtFromHash,
    encodeAnswers,
    loadDistrict,
    loadProgress,
    representativesFor,
    resultsHash,
    saveDistrict,
    saveProgress,
    scoreMembers,
    scoreParties,
    stanceText,
} from './quiz';

async function fetchJson(url) {
    const response = await fetch(url, { headers: { Accept: 'application/json' } });

    if (!response.ok) {
        throw new Error(`Could not load ${url}`);
    }

    return response.json();
}

Alpine.data('quiz', () => ({
    loading: true,
    failed: false,
    data: null,
    index: 0,
    answers: {},
    announcement: '',
    resultsUrl: '',
    storageKey: '',

    async init() {
        this.resultsUrl = this.$el.dataset.resultsUrl;
        this.storageKey = this.$el.dataset.storageKey;

        try {
            this.data = await fetchJson(this.$el.dataset.stancesUrl);

            const fromLink = answersFromHash(window.location.hash);
            const saved = loadProgress(this.storageKey, this.data.version);

            if (Object.keys(fromLink).length) {
                this.answers = fromLink;
            } else if (saved) {
                this.answers = saved.answers;
                this.index = Math.min(saved.index, this.total - 1);
            }

            this.announce();
        } catch {
            this.failed = true;
        } finally {
            this.loading = false;
        }
    },

    get total() {
        return this.data ? this.data.policies.length : 0;
    },

    get current() {
        return this.data ? this.data.policies[this.index] : null;
    },

    get positionLabel() {
        return `Question ${this.index + 1} of ${this.total}`;
    },

    get progress() {
        return { width: `${this.total ? (this.index / this.total) * 100 : 0}%` };
    },

    get isFirst() {
        return this.index === 0;
    },

    get comparable() {
        return comparableAnswerCount(this.answers);
    },

    get canSeeResults() {
        return this.comparable >= MIN_COMPARABLE_ANSWERS;
    },

    get remainingForResults() {
        return Math.max(0, MIN_COMPARABLE_ANSWERS - this.comparable);
    },

    isSelected(answer) {
        return this.current !== null && this.answers[this.current.id] === answer;
    },

    answerClass(answer) {
        return this.isSelected(answer)
            ? 'border-zinc-900 bg-zinc-900 text-white dark:border-zinc-100 dark:bg-zinc-100 dark:text-zinc-900'
            : 'border-zinc-300 hover:border-zinc-500 dark:border-zinc-700 dark:hover:border-zinc-400';
    },

    choose(answer) {
        this.answers = { ...this.answers, [this.current.id]: answer };

        if (this.index < this.total - 1) {
            this.index++;
            this.save();
            this.announce();
        } else {
            this.finish();
        }
    },

    agree() {
        this.choose('a');
    },

    disagree() {
        this.choose('d');
    },

    unsure() {
        this.choose('u');
    },

    skip() {
        this.choose('s');
    },

    back() {
        if (!this.isFirst) {
            this.index--;
            this.save();
            this.announce();
        }
    },

    /**
     * Single-key shortcuts only apply while the quiz card has focus, so they
     * never interfere with assistive technology elsewhere (WCAG 2.1.4).
     */
    onKey(event) {
        if (event.altKey || event.ctrlKey || event.metaKey) {
            return;
        }

        const actions = { a: 'agree', d: 'disagree', u: 'unsure', s: 'skip', ArrowLeft: 'back' };
        const action = actions[event.key.length === 1 ? event.key.toLowerCase() : event.key];

        if (action) {
            event.preventDefault();
            this[action]();
        }
    },

    save() {
        saveProgress(this.storageKey, this.data.version, this.answers, this.index);
    },

    announce() {
        this.announcement = this.current ? `${this.positionLabel}: ${this.current.question}` : '';
    },

    finish() {
        this.save();
        window.location.href = `${this.resultsUrl}${resultsHash(this.answers, loadDistrict())}`;
    },

    startAgain() {
        clearProgress(this.storageKey);
        this.answers = {};
        this.index = 0;
        this.announce();
    },
}));

Alpine.data('results', () => ({
    loading: true,
    failed: false,
    data: null,
    answers: {},
    district: '',
    copied: false,
    quizUrl: '',
    districtUrl: '',
    storageKey: '',

    async init() {
        this.quizUrl = this.$el.dataset.quizUrl;
        this.districtUrl = this.$el.dataset.districtUrl;
        this.storageKey = this.$el.dataset.storageKey;

        try {
            this.data = await fetchJson(this.$el.dataset.stancesUrl);

            const fromLink = answersFromHash(window.location.hash);
            this.answers = Object.keys(fromLink).length ? fromLink : (loadProgress(this.storageKey, this.data.version)?.answers ?? {});
            this.district = districtFromHash(window.location.hash) ?? loadDistrict() ?? '';
        } catch {
            this.failed = true;
        } finally {
            this.loading = false;
        }
    },

    get comparable() {
        return comparableAnswerCount(this.answers);
    },

    get enoughAnswers() {
        return this.comparable >= MIN_COMPARABLE_ANSWERS;
    },

    get minimumAnswers() {
        return MIN_COMPARABLE_ANSWERS;
    },

    get scored() {
        return this.data ? scoreParties(this.data, this.answers) : [];
    },

    get rankedParties() {
        return this.scored
            .filter((party) => party.shared >= MIN_SHARED_QUESTIONS)
            .sort((a, b) => b.score - a.score)
            .map((party) => ({
                ...party,
                dotStyle: { left: `${party.percent}%` },
                summary: `${party.percent}% · ${party.shared} questions`,
                label: `${party.short_name}: ${party.percent}% match across ${party.shared} questions`,
            }));
    },

    get partiesWithoutRecord() {
        return this.scored.filter((party) => party.shared < MIN_SHARED_QUESTIONS);
    },

    get answeredPolicies() {
        if (!this.data) {
            return [];
        }

        return this.data.policies
            .filter((policy) => this.answers[policy.id] in ANSWERS)
            .map((policy) => {
                const answer = ANSWERS[this.answers[policy.id]];

                return {
                    ...policy,
                    yourAnswer: answer.label,
                    parties: this.data.parties.map((party) => {
                        const stance = policy.stances[party.code] ?? null;
                        const agreement = stance?.agreement ?? null;
                        const comparable = answer.value !== null && agreement !== null;
                        const matches = comparable && 1 - Math.abs(answer.value - agreement) >= 0.5;

                        return {
                            code: party.code,
                            name: party.short_name,
                            stance: stanceText(stance),
                            verdict: comparable ? (matches ? 'Matches you' : 'Differs from you') : '',
                            verdictClass: comparable ? (matches ? 'text-zinc-900 dark:text-zinc-100' : 'text-zinc-500') : '',
                        };
                    }),
                };
            });
    },

    get hasMembers() {
        return (this.data?.members ?? []).length > 0;
    },

    get districts() {
        return this.data?.districts ?? [];
    },

    get representatives() {
        return this.data ? representativesFor(this.data, this.district) : null;
    },

    get representativeRows() {
        const representatives = this.representatives;

        if (!representatives) {
            return [];
        }

        const describe = (role) => (member) => ({
            ...member,
            role,
            summary: member.shared >= MIN_SHARED_QUESTIONS ? `${member.percent}% · ${member.shared} questions` : 'Too few shared votes to compare',
            dotStyle: { left: `${member.percent ?? 0}%` },
            hasScore: member.shared >= MIN_SHARED_QUESTIONS,
            label: member.shared >= MIN_SHARED_QUESTIONS
                ? `${member.name}, ${member.party}, ${role}: ${member.percent}% match across ${member.shared} questions`
                : `${member.name}, ${member.party}, ${role}: too few shared votes to compare`,
        });
        const byScore = (a, b) => (b.shared >= MIN_SHARED_QUESTIONS) - (a.shared >= MIN_SHARED_QUESTIONS) || (b.score ?? 0) - (a.score ?? 0);

        return [
            ...scoreMembers(this.data, this.answers, representatives.assembly).map(describe(`MLA for ${representatives.district.name}`)),
            ...scoreMembers(this.data, this.answers, representatives.council).sort(byScore).map(describe(`MLC for ${representatives.region?.name ?? 'the region'}`)),
        ];
    },

    get isVacant() {
        return this.representatives !== null && this.representatives.assembly.length === 0;
    },

    get districtName() {
        return this.representatives?.district.name ?? '';
    },

    get districtPageUrl() {
        return this.district ? this.districtUrl.replace('__district__', this.district) : '';
    },

    chooseDistrict(event) {
        this.district = event.target.value;

        if (this.district) {
            saveDistrict(this.district);
        }

        history.replaceState(null, '', resultsHash(this.answers, this.district));
    },

    get changeAnswersUrl() {
        return `${this.quizUrl}#a=${encodeAnswers(this.answers)}`;
    },

    async copyLink() {
        try {
            const { origin, pathname, search } = window.location;
            await navigator.clipboard.writeText(`${origin}${pathname}${search}${resultsHash(this.answers, this.representatives ? this.district : '')}`);
            this.copied = true;
        } catch {
            this.copied = false;
        }
    },
}));

Alpine.data('finder', () => ({
    data: null,
    loading: false,
    failed: false,
    query: '',
    open: false,
    active: -1,
    chosen: null,
    url: '',
    districtUrl: '',

    init() {
        this.url = this.$el.dataset.localitiesUrl;
        this.districtUrl = this.$el.dataset.districtUrl;
    },

    async load() {
        if (this.data || this.loading) {
            return;
        }

        this.loading = true;

        try {
            this.data = prepareLocalities(await fetchJson(this.url));
        } catch {
            this.failed = true;
        } finally {
            this.loading = false;
        }
    },

    get matches() {
        return this.data ? searchLocalities(this.data, this.query) : [];
    },

    get isOpen() {
        return this.open && this.matches.length > 0;
    },

    get noMatches() {
        return this.data !== null && this.query.trim().length >= 2 && this.matches.length === 0;
    },

    get activeId() {
        return this.isOpen && this.active >= 0 ? `finder-option-${this.active}` : '';
    },

    get status() {
        if (this.loading) {
            return 'Loading suburbs…';
        }

        if (this.isOpen) {
            return `${this.matches.length} ${this.matches.length === 1 ? 'suburb' : 'suburbs'} found. Use the up and down arrows to choose.`;
        }

        return this.noMatches ? 'No suburbs found.' : '';
    },

    optionId(index) {
        return `finder-option-${index}`;
    },

    optionClass(index) {
        return index === this.active ? 'bg-zinc-100 dark:bg-zinc-800' : '';
    },

    optionDetail(locality) {
        const postcodes = locality.postcodes.join(', ');
        const where = locality.districts.length === 1
            ? `${this.data.districts[locality.districts[0].slug]} District`
            : `${locality.districts.length} districts`;

        return postcodes ? `${postcodes} · ${where}` : where;
    },

    onInput() {
        this.open = true;
        this.active = -1;
        this.chosen = null;
    },

    onKeydown(event) {
        if (event.key === 'ArrowDown' && this.matches.length) {
            event.preventDefault();
            this.open = true;
            this.active = (this.active + 1) % this.matches.length;
        } else if (event.key === 'ArrowUp' && this.matches.length) {
            event.preventDefault();
            this.open = true;
            this.active = this.active <= 0 ? this.matches.length - 1 : this.active - 1;
        } else if (event.key === 'Enter' && this.isOpen) {
            event.preventDefault();
            this.select(this.active >= 0 ? this.active : 0);
        } else if (event.key === 'Escape') {
            this.open = false;
            this.active = -1;
        }
    },

    close() {
        this.open = false;
    },

    select(index) {
        const locality = this.matches[index];

        if (!locality) {
            return;
        }

        this.query = locality.name;
        this.open = false;
        this.active = -1;

        if (locality.districts.length === 1) {
            this.go(locality.districts[0].slug);
        } else {
            this.chosen = locality;
            this.$nextTick(() => this.$refs.choices?.focus());
        }
    },

    get chosenDistricts() {
        return this.chosen ? describeDistricts(this.data, this.chosen) : [];
    },

    get chosenName() {
        return this.chosen?.name ?? '';
    },

    districtLink(slug) {
        return this.districtUrl.replace('__district__', slug);
    },

    remember(slug) {
        saveDistrict(slug);
    },

    go(slug) {
        saveDistrict(slug);
        window.location.href = this.districtLink(slug);
    },
}));

Alpine.data('myDistrict', () => ({
    slug: '',
    mine: null,

    init() {
        this.slug = this.$el.dataset.district;
        this.mine = loadDistrict();
    },

    get isMine() {
        return this.mine === this.slug;
    },

    choose() {
        saveDistrict(this.slug);
        this.mine = this.slug;
    },
}));

window.Alpine = Alpine;
Alpine.start();
