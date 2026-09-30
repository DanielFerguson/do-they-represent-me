import Alpine from '@alpinejs/csp';
import { answeredBucket, browserSignalsOptOut, isOptedOut, optIn, optOut, resultsSource, startAnalytics, track } from './analytics';
import { describeDistricts, prepareLocalities, searchLocalities } from './finder';
import {
    ANSWERS,
    MIN_COMPARABLE_ANSWERS,
    MIN_SHARED_QUESTIONS,
    answersFromHash,
    clearInvite,
    clearProgress,
    compareAnswers,
    comparableAnswerCount,
    consumeArrivedFromQuiz,
    districtFromHash,
    encodeAnswers,
    friendsFromHash,
    inviteHash,
    isSharedResults,
    loadDistrict,
    loadInvite,
    loadProgress,
    markArrivedFromQuiz,
    ownerNameFromHash,
    representativesFor,
    resultsHash,
    saveInvite,
    shareHash,
    saveDistrict,
    saveProgress,
    scoreMembers,
    scoreParties,
    stanceText,
} from './quiz';
import { RESULTS_TEXT, canShareFiles, canShareLink, channelLinks, districtText, inviteText, withName } from './share';

// Anonymous visit counts. They start only on pages marked for it, and never
// carry answers or anything from the address. See analytics.js.
startAnalytics({ key: document.body.dataset.posthogKey, pageType: document.body.dataset.pageType });

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
    invite: null,

    async init() {
        this.resultsUrl = this.$el.dataset.resultsUrl;
        this.storageKey = this.$el.dataset.storageKey;

        // Someone's invitation to compare, from their link or earlier in this tab.
        const [linked] = friendsFromHash(window.location.hash);

        if (linked) {
            saveInvite(linked);
            track('invite_landed');
        }

        this.invite = linked ?? loadInvite();

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

    get hasInvite() {
        return this.invite !== null;
    },

    get inviteEyebrow() {
        return this.invite?.name ? `Invitation from ${this.invite.name}` : 'Invitation to compare';
    },

    get inviteHeading() {
        const who = this.invite?.name || 'Someone';

        return `${who} answered ${comparableAnswerCount(this.invite?.answers ?? {})} questions and wants to see how you compare.`;
    },

    /** Dismissing the invitation forgets it, and takes it out of the address so a reload doesn't bring it back. */
    dismissInvite() {
        this.invite = null;
        clearInvite();

        const own = encodeAnswers(answersFromHash(window.location.hash));
        history.replaceState(null, '', `${window.location.pathname}${window.location.search}${own ? `#a=${own}` : ''}`);
    },

    get current() {
        return this.data ? this.data.policies[this.index] : null;
    },

    get positionLabel() {
        return `Question ${this.index + 1} of ${this.total}`;
    },

    get positionShort() {
        return `${this.index + 1} of ${this.total}`;
    },

    /** True once any answer is saved; the intro then shrinks to a title line. */
    get hasStarted() {
        return Object.keys(this.answers).length > 0;
    },

    get showsQuestionCount() {
        return !this.hasStarted && this.total > 0;
    },

    get titleClass() {
        return this.hasStarted ? 'text-[15px]! leading-[18px]! tracking-normal!' : '';
    },

    get answeredCount() {
        return Object.keys(this.answers).length;
    },

    /** The desktop rail lists the questions around the current one, and says how many more follow. */
    get railLength() {
        return Math.min(this.total, Math.max(6, this.index + 3));
    },

    get railItems() {
        if (!this.data) {
            return [];
        }

        return this.data.policies.slice(0, this.railLength).map((policy, index) => {
            const answer = this.answers[policy.id];

            return {
                id: policy.id,
                index,
                number: index + 1,
                title: policy.title,
                answer: answer ? ANSWERS[answer].label : '',
                current: index === this.index ? 'step' : null,
                titleClass: answer || index === this.index ? 'text-ink' : 'text-ink-muted',
                answerClass: answer === 's' || answer === 'u' ? 'font-normal text-ink-muted' : 'text-ink',
            };
        });
    },

    get hasMoreInRail() {
        return this.total > this.railLength;
    },

    get railMoreLabel() {
        return `+ ${this.total - this.railLength} more`;
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

    choose(answer) {
        // Position only. The answer itself is never counted.
        if (!this.hasStarted) {
            track('quiz_started', { position: this.index + 1 });
        }

        track('question_answered', { position: this.index + 1, total: this.total });

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

    goTo(index) {
        this.index = index;
        this.save();
        this.announce();
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

        // "About this question" starts closed for each new question.
        if (this.$refs.about) {
            this.$refs.about.open = false;
        }
    },

    finish() {
        this.save();
        markArrivedFromQuiz();
        window.location.href = `${this.resultsUrl}${resultsHash(this.answers, loadDistrict(), this.invite ? [this.invite] : [])}`;
    },

    startAgain() {
        track('start_again');
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
    districtAnnouncement: '',
    copyStatus: '',
    quizUrl: '',
    districtUrl: '',
    storageKey: '',
    authorisation: '',

    // Comparing with a friend, and looking at someone else's results
    friends: [],
    ownerName: '',
    isShared: false,

    // The share sheet
    shareKind: 'results',
    shareName: '',
    shareStep: 1,
    shareStatus: '',
    copied: false,
    card: null,
    cardFile: null,
    cardFailed: false,
    wide: false,

    async init() {
        this.authorisation = this.$el.dataset.authorisation ?? '';

        // The share sheet shows everything at once on a wide screen and in two steps on a narrow one.
        const wide = window.matchMedia('(min-width: 1024px)');
        this.wide = wide.matches;
        wide.addEventListener('change', (event) => {
            this.wide = event.matches;
        });

        this.quizUrl = this.$el.dataset.quizUrl;
        this.districtUrl = this.$el.dataset.districtUrl;
        this.storageKey = this.$el.dataset.storageKey;

        try {
            this.data = await fetchJson(this.$el.dataset.stancesUrl);

            const fromLink = answersFromHash(window.location.hash);
            const saved = loadProgress(this.storageKey, this.data.version)?.answers ?? {};
            const hasLink = Object.keys(fromLink).length > 0;

            this.answers = hasLink ? fromLink : saved;
            this.district = districtFromHash(window.location.hash) ?? loadDistrict() ?? '';
            this.ownerName = ownerNameFromHash(window.location.hash);
            this.isShared = isSharedResults(saved, fromLink);
            this.startComparing(friendsFromHash(window.location.hash));

            this.countVisit({ hasLink, shared: isSharedResults(saved, fromLink), arrivedFromQuiz: consumeArrivedFromQuiz() });
        } catch {
            this.failed = true;
        } finally {
            this.loading = false;
        }
    },

    /**
     * A friend's answers arrive in the link, but live only for this tab after that, and the link in the
     * address bar is cut back to the visitor's own answers. So a link copied from the address bar never
     * carries a friend's answers to anyone else.
     */
    startComparing(linked) {
        if (this.isShared) {
            track('recipient_banner_shown');

            return;
        }

        const [first] = linked;

        if (first) {
            saveInvite(first);
            history.replaceState(null, '', this.currentHash());
        }

        const friend = first ?? loadInvite();
        this.friends = friend ? [friend] : [];

        if (friend && this.enoughAnswers) {
            track('compare_viewed', { group_size: 2 });
        }
    },

    stopComparing() {
        clearInvite();
        this.friends = [];
    },

    /** The part of the address after the #: the visitor's own answers, never a friend's. */
    currentHash() {
        const district = this.representatives ? this.district : '';

        return this.isShared
            ? shareHash(this.answers, district, this.ownerName)
            : resultsHash(this.answers, district);
    },

    trackCompareCta() {
        track('compare_cta_clicked');
    },

    /** Counts the visit by how they got here, and how many answers they gave. Never which answers. */
    countVisit({ hasLink, shared, arrivedFromQuiz }) {
        if (!this.enoughAnswers) {
            track('results_too_few');

            return;
        }

        track('results_viewed', {
            source: resultsSource({ fromLink: hasLink, isShared: shared, arrivedFromQuiz }),
            answered_bucket: answeredBucket(this.comparable),
        });
    },

    /**
     * The swatch class for a party code, grey for a party without its own colour.
     * Colour only ever marks a name; every bar and figure is drawn in the same ink.
     */
    swatchFor(code) {
        const known = ['ajp', 'alp', 'dlp', 'ffv', 'grn', 'ind', 'lbt', 'lcv', 'lib', 'nat', 'onp', 'sff'];
        const lower = String(code ?? '').toLowerCase();

        return known.includes(lower) ? `bg-party-${lower}` : 'bg-party';
    },

    questionsText(count) {
        return `${count} ${count === 1 ? 'question' : 'questions'}`;
    },

    get comparable() {
        return comparableAnswerCount(this.answers);
    },

    get enoughAnswers() {
        return this.comparable >= MIN_COMPARABLE_ANSWERS;
    },

    get showsResults() {
        return !this.loading && !this.failed && this.enoughAnswers;
    },

    get showsTooFew() {
        return !this.loading && !this.failed && !this.enoughAnswers;
    },

    get minimumAnswers() {
        return MIN_COMPARABLE_ANSWERS;
    },

    get answerSegments() {
        return Array.from({ length: MIN_COMPARABLE_ANSWERS }, (_, index) => ({
            key: index,
            segmentClass: index < this.comparable ? 'bg-ink' : 'bg-rule',
        }));
    },

    get neededText() {
        return `${Math.min(this.comparable, MIN_COMPARABLE_ANSWERS)} of ${MIN_COMPARABLE_ANSWERS} needed`;
    },

    get dataAsOf() {
        const asOf = this.data?.data_as_of;

        if (!asOf) {
            return '';
        }

        const date = new Date(`${asOf}T00:00:00`);

        return Number.isNaN(date.getTime())
            ? asOf
            : date.toLocaleDateString('en-AU', { day: 'numeric', month: 'long', year: 'numeric' });
    },

    get policyCount() {
        return this.data?.policies.length ?? 0;
    },

    get unsureCount() {
        return (this.data?.policies ?? []).filter((policy) => this.answers[policy.id] === 'u').length;
    },

    get skippedCount() {
        const answered = (this.data?.policies ?? []).filter((policy) => ['a', 'd', 'u'].includes(this.answers[policy.id])).length;

        return Math.max(0, this.policyCount - answered);
    },

    get scored() {
        return this.data ? scoreParties(this.data, this.answers) : [];
    },

    get rankedParties() {
        return this.scored
            .filter((party) => party.shared >= MIN_SHARED_QUESTIONS)
            .sort((a, b) => b.score - a.score)
            .map((party) => {
                const notCounted = Math.max(0, this.comparable - party.shared);

                return {
                    ...party,
                    swatchClass: this.swatchFor(party.code),
                    barStyle: { width: `${party.percent}%` },
                    percentText: `${party.percent}%`,
                    sharedText: this.questionsText(party.shared),
                    notCounted: notCounted > 0,
                    notCountedCount: String(notCounted),
                    notCountedNoun: notCounted === 1 ? 'question' : 'questions',
                    label: `${party.short_name}: ${party.percent}% match across ${this.questionsText(party.shared)}`,
                };
            });
    },

    /**
     * Parties with too few shared votes to compare, alphabetically so the order carries no meaning.
     */
    get partiesWithoutRecord() {
        return this.scored
            .filter((party) => party.shared < MIN_SHARED_QUESTIONS)
            .sort((a, b) => a.short_name.localeCompare(b.short_name, 'en-AU'));
    },

    get hasPartiesWithoutRecord() {
        return this.partiesWithoutRecord.length > 0;
    },

    get partiesWithoutRecordText() {
        return `${this.partiesWithoutRecord.map((party) => party.short_name).join(', ')}.`;
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
                    caption: `How each party voted: ${policy.question}`,
                    parties: this.data.parties.map((party) => {
                        const stance = policy.stances[party.code] ?? null;
                        const agreement = stance?.agreement ?? null;
                        const comparable = answer.value !== null && agreement !== null;
                        const matches = comparable && 1 - Math.abs(answer.value - agreement) >= 0.5;
                        const notCounted = answer.value !== null && !comparable;
                        const hasFigureOrNote = agreement !== null || Boolean(stance?.note);

                        return {
                            code: party.code,
                            name: party.short_name,
                            swatchClass: this.swatchFor(party.code),
                            stance: stanceText(stance),
                            stanceClass: hasFigureOrNote ? 'lg:text-ink' : 'lg:text-ink-muted',
                            isSame: comparable && matches,
                            isDifferent: comparable && !matches,
                            isNotCounted: notCounted,
                        };
                    }),
                };
            });
    },

    get hasMembers() {
        return !this.isShared && (this.data?.members ?? []).length > 0;
    },

    get districts() {
        return this.data?.districts ?? [];
    },

    isDistrict(slug) {
        return slug === this.district;
    },

    get representatives() {
        return this.data ? representativesFor(this.data, this.district) : null;
    },

    get representativeRows() {
        const representatives = this.representatives;

        if (!representatives) {
            return [];
        }

        const partyCodes = new Map((this.data.parties ?? []).map((party) => [party.short_name, party.code]));
        const describe = (role) => (member) => ({
            ...member,
            role,
            swatchClass: this.swatchFor(partyCodes.get(member.party)),
            percentText: `${member.percent}%`,
            sharedText: this.questionsText(member.shared),
            barStyle: { width: `${member.percent ?? 0}%` },
            hasScore: member.shared >= MIN_SHARED_QUESTIONS,
            label: member.shared >= MIN_SHARED_QUESTIONS
                ? `${member.name}, ${member.party}, ${role}: ${member.percent}% match across ${this.questionsText(member.shared)}`
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
            track('district_chosen', { source: 'results' });
        }

        history.replaceState(null, '', this.currentHash());

        const representatives = this.representatives;
        this.districtAnnouncement = representatives
            ? `Showing your MLA and ${representatives.council.length} MLCs for ${representatives.district.name}.`
            : '';
    },

    get changeAnswersUrl() {
        return `${this.quizUrl}#a=${encodeAnswers(this.answers)}`;
    },

    // ---- Someone else's results ---------------------------------------------

    get eyebrow() {
        return this.isShared ? 'Shared results' : 'Your results';
    },

    get partiesHeading() {
        return `How often each party voted the way ${this.isShared ? (this.ownerName || 'they') : 'you'} would have`;
    },

    get basedOnText() {
        return `Based on ${this.isShared ? (this.ownerName ? `${this.ownerName}'s` : 'their') : 'your'} ${this.comparable} agree or disagree answers.`;
    },

    get scopeText() {
        return `These results compare ${this.isShared ? 'these' : 'your'} answers with how parties voted in the 60th Parliament (2022–2026), not with their promises for the 2026 election.`;
    },

    get matchedLabel() {
        return this.isShared ? `Matched ${this.ownerName || 'them'}` : 'Matched you';
    },

    get answersHeading() {
        return this.isShared ? `${this.ownerName ? `${this.ownerName}'s` : 'Their'} answers` : 'Your answers';
    },

    get answerWho() {
        return this.isShared ? (this.ownerName || 'They') : 'You';
    },

    get recipientHeading() {
        return this.ownerName
            ? `You're looking at ${this.ownerName}'s results, not yours.`
            : "You're looking at someone else's results, not yours.";
    },

    /** The quiz, with the sender's answers in an invitation to compare. */
    get compareInviteUrl() {
        return `${this.quizUrl}${inviteHash(this.answers, this.ownerName)}`;
    },

    // ---- Comparing with a friend ----------------------------------------------

    get friend() {
        return this.friends[0] ?? null;
    },

    get friendName() {
        return this.friend?.name || 'your friend';
    },

    get friendNote() {
        return `${this.friend?.name ? `${this.friend.name}'s` : "Your friend's"} answers came from the link you opened. They're kept only while this tab is open, and nothing is sent to us.`;
    },

    get comparison() {
        return this.friend && this.data ? compareAnswers(this.answers, this.friend.answers, this.data.policies) : null;
    },

    get hasCompare() {
        return this.showsResults && this.comparison !== null;
    },

    get compareShared() {
        return this.comparison?.shared ?? 0;
    },

    get compareSameCount() {
        return this.comparison?.same.length ?? 0;
    },

    get compareDifferentCount() {
        return this.comparison?.different.length ?? 0;
    },

    get onlyMineCount() {
        return this.comparison?.onlyMine.length ?? 0;
    },

    get onlyTheirsCount() {
        return this.comparison?.onlyTheirs.length ?? 0;
    },

    get compareHeading() {
        const comparison = this.comparison;

        if (!comparison || comparison.percent === null) {
            return `You and ${this.friendName} have too few answers in common to compare`;
        }

        return `You and ${this.friendName} agreed on ${comparison.same.length} of ${comparison.shared} questions`;
    },

    get compareSummary() {
        return this.compareShared
            ? `Out of the ${this.compareShared} questions you both answered agree or disagree. Unsure and skipped questions aren't counted.`
            : `You haven't both answered agree or disagree on enough of the same questions yet.`;
    },

    get agreementSegments() {
        const comparison = this.comparison;

        if (!comparison) {
            return [];
        }

        return [
            ...comparison.same.map((policy) => ({ key: `same-${policy.id}`, segmentClass: 'bg-ink' })),
            ...comparison.different.map((policy) => ({ key: `different-${policy.id}`, segmentClass: 'border-[1.5px] border-ink' })),
        ];
    },

    describeComparison(policy) {
        return {
            id: policy.id,
            topic: policy.topic,
            question: policy.question,
            mine: ANSWERS[this.answers[policy.id]]?.label ?? '',
            theirs: ANSWERS[this.friend.answers[policy.id]]?.label ?? '',
        };
    },

    get differences() {
        return (this.comparison?.different ?? []).map((policy) => this.describeComparison(policy));
    },

    get agreements() {
        return (this.comparison?.same ?? []).map((policy) => this.describeComparison(policy));
    },

    get differenceCountText() {
        return this.questionsText(this.compareDifferentCount);
    },

    get agreementCountText() {
        return this.questionsText(this.compareSameCount);
    },

    get comparingWithLabel() {
        return `Comparing with ${this.friendName}`;
    },

    get onlyTheirsLabel() {
        return `Only ${this.friendName} answered`;
    },

    /** Each party in the visitor's order, with both people's percentages. */
    get partyComparison() {
        if (!this.friend || !this.data) {
            return [];
        }

        const theirs = new Map(scoreParties(this.data, this.friend.answers)
            .filter((party) => party.shared >= MIN_SHARED_QUESTIONS)
            .map((party) => [party.code, party.percent]));

        return this.rankedParties.map((party) => {
            const other = theirs.get(party.code);

            return {
                code: party.code,
                name: party.short_name,
                swatchClass: party.swatchClass,
                mineText: party.percentText,
                mineStyle: party.barStyle,
                theirsText: other === undefined ? '—' : `${other}%`,
                theirsStyle: { width: `${other ?? 0}%` },
            };
        });
    },

    async copyLink() {
        try {
            const { origin, pathname, search } = window.location;
            await navigator.clipboard.writeText(`${origin}${pathname}${search}${this.currentHash()}`);
            this.copyStatus = 'Link copied.';
        } catch {
            this.copyStatus = "Couldn't copy the link. You can copy it from the address bar instead.";
        }
    },

    // ---- The share sheet -------------------------------------------------

    get isResultsKind() {
        return this.shareKind === 'results';
    },

    get showsChoice() {
        return this.wide || this.shareStep === 1;
    },

    get showsShare() {
        return this.wide || this.shareStep === 2;
    },

    get showsContinue() {
        return !this.wide && this.shareStep === 1;
    },

    get showsBack() {
        return !this.wide && this.shareStep === 2;
    },

    get showsCard() {
        return this.showsShare && this.isResultsKind;
    },

    get sheetTitle() {
        if (!this.showsBack) {
            return 'Share';
        }

        return this.isResultsKind ? 'Share my results' : 'Invite someone';
    },

    get bodyClass() {
        return this.isResultsKind ? 'lg:grid-cols-[264px_1fr]' : 'lg:grid-cols-1';
    },

    get nameHint() {
        return this.isResultsKind
            ? "So they know it's from you. It goes in the link after the #, which is never sent to us."
            : "So they know it's from you. It goes in the message, not the link.";
    },

    /** The link to share: the visitor's own answers for "My results", the plain quiz for an invitation. */
    get shareUrl() {
        if (!this.isResultsKind) {
            return this.quizUrl;
        }

        const { origin, pathname } = window.location;

        return `${origin}${pathname}${shareHash(this.answers, this.representatives ? this.district : '', this.shareName)}`;
    },

    get shareText() {
        return withName(this.isResultsKind ? RESULTS_TEXT : inviteText(this.policyCount), this.shareName);
    },

    get channels() {
        return channelLinks(this.shareText, this.shareUrl);
    },

    get cardSrc() {
        return this.card?.dataUrl ?? '';
    },

    get canNative() {
        return this.isResultsKind ? (this.card !== null && canShareFiles(navigator, this.cardFile)) : canShareLink(navigator);
    },

    get nativeLabel() {
        return this.isResultsKind ? 'Share image and link…' : 'Share…';
    },

    get saveClass() {
        return this.canNative
            ? 'border border-rule-strong hover:border-ink'
            : 'bg-ink text-ground hover:bg-ink/85';
    },

    get copyLabel() {
        return this.copied ? 'Link copied' : 'Copy link';
    },

    openShare() {
        this.shareStep = this.wide ? 2 : 1;
        this.shareStatus = '';
        this.copied = false;
        this.$refs.sheet.showModal();

        if (this.wide) {
            this.startShare();
        }
    },

    closeShare() {
        this.$refs.sheet.close();
    },

    /** A click on the backdrop, which is the dialog itself, closes the sheet. */
    onSheetClick(event) {
        if (event.target === this.$refs.sheet) {
            this.closeShare();
        }
    },

    onSheetClosed() {
        this.shareStep = 1;
    },

    continueShare() {
        this.shareStep = 2;
        this.startShare();
    },

    backShare() {
        this.shareStep = 1;
    },

    onKindChange() {
        this.copied = false;
        this.shareStatus = '';

        if (this.showsShare) {
            this.startShare();
        }
    },

    startShare() {
        track('share_opened', { kind: this.shareKind });

        if (this.isResultsKind && !this.card) {
            this.makeCard();
        }
    },

    async makeCard() {
        this.cardFailed = false;

        try {
            const { renderCard } = await import('./share-card-render');
            const comparable = this.comparable;

            const card = await renderCard({
                ranked: this.rankedParties.map((party) => ({
                    name: party.short_name,
                    percent: party.percent,
                    sharedText: `${party.shared}/${comparable}`,
                    code: party.code,
                })),
                notEnough: this.partiesWithoutRecord.map((party) => party.short_name),
                meta: {
                    comparable,
                    dataAsOf: this.dataAsOf || null,
                    host: window.location.host,
                    authorisation: this.authorisation || null,
                },
            });

            this.cardFile = new File([card.blob], 'my-results.png', { type: 'image/png' });
            this.card = card;
        } catch {
            this.cardFailed = true;
        }
    },

    async nativeShare() {
        try {
            if (this.isResultsKind) {
                await navigator.share({ files: [this.cardFile], text: `${this.shareText} ${this.shareUrl}` });
            } else {
                await navigator.share({ text: this.shareText, url: this.shareUrl });
            }

            track('share_action', { kind: this.shareKind, channel: 'native' });
        } catch {
            // Closing the share sheet without choosing an app is not an error.
        }
    },

    saveImage() {
        if (!this.card?.blob) {
            return;
        }

        const link = document.createElement('a');
        link.href = URL.createObjectURL(this.card.blob);
        link.download = 'my-results.png';
        link.click();
        window.setTimeout(() => URL.revokeObjectURL(link.href), 1000);

        this.shareStatus = 'Image saved.';
        track('share_action', { kind: this.shareKind, channel: 'save_image' });
    },

    async copyShareLink() {
        try {
            await navigator.clipboard.writeText(this.shareUrl);
            this.copied = true;
            this.shareStatus = 'Link copied.';
            track('share_action', { kind: this.shareKind, channel: 'copy' });
        } catch {
            this.shareStatus = "Couldn't copy the link. You can copy it from the box below.";
        }
    },

    trackChannel(channel) {
        track('share_action', { kind: this.shareKind, channel });
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
        track('finder_used');

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
        return this.isOpen && this.active >= 0 ? `finder-option-${this.active}` : null;
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
        return index === this.active ? 'bg-surface outline-2 -outline-offset-2 outline-ink' : '';
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
        } else if (event.key === 'Escape' || event.key === 'Tab') {
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

    /**
     * The districts a split suburb falls in, largest share first, each with
     * its share of residents in words ("about 67%").
     */
    get chosenDistricts() {
        if (!this.chosen) {
            return [];
        }

        return describeDistricts(this.data, this.chosen).map((district, index) => {
            const percent = Math.round(district.share * 100);

            return {
                ...district,
                isLargest: index === 0,
                shareText: percent < 1 ? 'less than 1%' : `about ${percent}%`,
            };
        });
    },

    get chosenName() {
        return this.chosen?.name ?? '';
    },

    get chosenHeading() {
        return this.chosen ? `${this.chosen.name} is split between ${this.chosen.districts.length} districts` : '';
    },

    get chosenSummary() {
        const [largest] = this.chosenDistricts;

        if (!largest) {
            return '';
        }

        return largest.share >= 0.5 ? `Most residents are in ${largest.name}.` : `The largest part is in ${largest.name}.`;
    },

    districtLink(slug) {
        return this.districtUrl.replace('__district__', slug);
    },

    remember(slug) {
        saveDistrict(slug);
        track('district_chosen', { source: 'finder' });
    },

    go(slug) {
        saveDistrict(slug);
        track('district_chosen', { source: 'finder' });
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

    get status() {
        return this.isMine ? 'Your results will show these members.' : '';
    },

    choose() {
        saveDistrict(this.slug);
        this.mine = this.slug;
        track('district_chosen', { source: 'district_page' });
    },

    takeQuiz() {
        track('district_take_quiz_clicked');
    },
}));

/**
 * Sharing a district page: the link to the page, and its picture (made on the
 * server) shows in the preview. No answers are involved.
 */
Alpine.data('shareDistrict', () => ({
    url: '',
    name: '',
    status: '',
    copied: false,

    init() {
        this.url = this.$el.dataset.url;
        this.name = this.$el.dataset.name;
    },

    get text() {
        return districtText(this.name);
    },

    get channels() {
        return channelLinks(this.text, this.url);
    },

    get canNative() {
        return canShareLink(navigator);
    },

    get copyLabel() {
        return this.copied ? 'Link copied' : 'Copy link';
    },

    open() {
        this.status = '';
        this.copied = false;
        this.$refs.sheet.showModal();
        track('share_opened', { kind: 'district' });
    },

    close() {
        this.$refs.sheet.close();
    },

    /** A click on the backdrop, which is the dialog itself, closes the sheet. */
    onSheetClick(event) {
        if (event.target === this.$refs.sheet) {
            this.close();
        }
    },

    async nativeShare() {
        try {
            await navigator.share({ text: this.text, url: this.url });
            track('share_action', { kind: 'district', channel: 'native' });
        } catch {
            // Closing the share sheet without choosing an app is not an error.
        }
    },

    async copyLink() {
        try {
            await navigator.clipboard.writeText(this.url);
            this.copied = true;
            this.status = 'Link copied.';
            track('share_action', { kind: 'district', channel: 'copy' });
        } catch {
            this.status = "Couldn't copy the link. You can copy it from the address bar instead.";
        }
    },

    trackChannel(channel) {
        track('share_action', { kind: 'district', channel });
    },
}));

/** The privacy page's switch for anonymous visit counts, kept in this browser only. */
Alpine.data('analyticsChoice', () => ({
    off: false,
    signalled: false,

    init() {
        this.off = isOptedOut();
        this.signalled = browserSignalsOptOut();
    },

    get label() {
        return this.off ? 'Turn counting back on' : 'Turn off counting on this device';
    },

    get status() {
        return this.off ? 'Counting is off on this device.' : 'Counting is on.';
    },

    toggle() {
        if (this.off) {
            optIn();
        } else {
            optOut();
        }

        this.off = isOptedOut();
    },
}));

/**
 * The small-screen menu. Escape closes it and returns focus to the button,
 * so keyboard users are never left inside a hidden panel.
 */
Alpine.data('menu', () => ({
    open: false,

    get buttonLabel() {
        return this.open ? 'Close' : 'Menu';
    },

    toggle() {
        this.open = !this.open;
    },

    close() {
        if (this.open) {
            this.open = false;
            this.$refs.button.focus();
        }
    },
}));

window.Alpine = Alpine;
Alpine.start();
