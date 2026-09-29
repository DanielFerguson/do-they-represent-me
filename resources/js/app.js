import Alpine from '@alpinejs/csp';
import {
    ANSWERS,
    MIN_COMPARABLE_ANSWERS,
    MIN_SHARED_QUESTIONS,
    answersFromHash,
    clearProgress,
    comparableAnswerCount,
    encodeAnswers,
    loadProgress,
    saveProgress,
    scoreParties,
    stanceLabel,
} from './quiz';

async function fetchStances(url) {
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

    async init() {
        this.resultsUrl = this.$el.dataset.resultsUrl;

        try {
            this.data = await fetchStances(this.$el.dataset.stancesUrl);

            const fromLink = answersFromHash(window.location.hash);
            const saved = loadProgress(this.data.version);

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
        saveProgress(this.data.version, this.answers, this.index);
    },

    announce() {
        this.announcement = this.current ? `${this.positionLabel}: ${this.current.question}` : '';
    },

    finish() {
        this.save();
        window.location.href = `${this.resultsUrl}#a=${encodeAnswers(this.answers)}`;
    },

    startAgain() {
        clearProgress();
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
    copied: false,
    quizUrl: '',

    async init() {
        this.quizUrl = this.$el.dataset.quizUrl;

        try {
            this.data = await fetchStances(this.$el.dataset.stancesUrl);

            const fromLink = answersFromHash(window.location.hash);
            this.answers = Object.keys(fromLink).length ? fromLink : (loadProgress(this.data.version)?.answers ?? {});
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
                        const agreement = policy.stances[party.code]?.agreement ?? null;
                        const comparable = answer.value !== null && agreement !== null;
                        const matches = comparable && 1 - Math.abs(answer.value - agreement) >= 0.5;

                        return {
                            code: party.code,
                            name: party.short_name,
                            stance: stanceLabel(agreement),
                            verdict: comparable ? (matches ? 'Matches you' : 'Differs from you') : '',
                            verdictClass: comparable ? (matches ? 'text-zinc-900 dark:text-zinc-100' : 'text-zinc-500') : '',
                        };
                    }),
                };
            });
    },

    get changeAnswersUrl() {
        return `${this.quizUrl}#a=${encodeAnswers(this.answers)}`;
    },

    async copyLink() {
        try {
            await navigator.clipboard.writeText(window.location.href);
            this.copied = true;
        } catch {
            this.copied = false;
        }
    },
}));

window.Alpine = Alpine;
Alpine.start();
