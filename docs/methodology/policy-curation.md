# Policy curation

The quiz questions ("policies") and the divisions linked to each one were chosen by a written, rule-based protocol. The project owner stepped back from choosing and wording questions, so that their own views could not shape the results. Neutrality comes from following the protocol and recording every decision, and then from human reviewers of different political leanings. Nobody's judgement about which policies are good is part of it.

The full brief (`TASK.md`) and the curation report (`REPORT.md`) are kept with the working data in the research archive (see [README](README.md#what-is-recorded-where)).

## Protocol

The protocol has six stages. The workbook was saved after each one.

1. **Candidate pool (mechanical).**
   - Every bill and motion is included if both of these hold:
     - it had at least one decisive vote (a second or third reading, a reasoned amendment, a bill introduction or a substantive motion);
     - the main blocs split on it (Labor, the Coalition as Liberal plus National, and the Greens), or a major bloc and a significant share of the crossbench voted differently.
   - Items are excluded by a recorded rule:
     - procedural business;
     - committee membership or establishment;
     - privileges;
     - motions about individual MPs;
     - motions that only censure or praise;
     - orders for the production of documents.
   - Conscience votes go on a separate list, for comparing individual MPs only.
2. **Scoring.** Each candidate was scored from 0 to 3 on five criteria, each with a one-line justification:
   - salience, backed by news sources from across the spectrum and regional media;
   - differentiation between parties;
   - record strength (the number of decisive votes, and whether both houses voted);
   - clarity as a single yes/no question;
   - relevance in 2026.

   No item was scored on whether the policy is good or bad.
3. **Selection** of 18–25 policies under balance constraints:
   - at least two policies from each of eleven topic areas, where a usable record exists;
   - each of Labor, the Coalition and the Greens on the "agree" side of 35–65% of questions;
   - "agree" matching the government's position in about half the questions, and worded as a change in some questions and as keeping the status quo in others;
   - at least four questions where the Greens or crossbench differ from both major parties;
   - at least two questions where the Coalition and the Greens vote the same way.
4. **Research from primary sources**, with every URL recorded:
   - the bill page and explanatory memorandum;
   - the second-reading speech;
   - the lead opposition and crossbench speeches;
   - Parliamentary Library papers, where they exist.

   Each question must be:
   - at most 25 words, at about a Year 8 reading level;
   - about one idea, with no party names, no double negatives and no loaded terms;
   - fairly answered by its linked votes.

   The direction of every linked vote was confirmed from the source paragraph, never guessed. Quotes are under 15 words and attributed.
5. **Adversarial review.**
   - Independent reviewers took the view of a thoughtful supporter of each of Labor, the Coalition, the Greens and the crossbench.
   - A plain-language reviewer with no political knowledge also read every question.
   - Questions were revised until no reviewer had an unresolved objection.
   - Remaining disagreements are recorded verbatim with their resolution.
   - Reviewers' factual claims were checked against the saved sources before any change was adopted.
6. **Output.** The results are in the policy workbook and the curation report. Every selected policy starts with the status "Review".

## The workbook

The workbook is the only place policy text is edited. The importer reads four tabs and matches columns by their heading, so columns can be added or moved.

- **Policies:** one row per policy.
  - Its ID (P01, P02…) is permanent and becomes the policy's number in quiz links.
  - Reviewers set each Status to **Ready** to publish a policy, or **Dropped** to remove it.
- **Policy votes:** one row per linked division, giving:
  - the division ID;
  - whether "agree" means an Aye or a No vote;
  - whether it is a strong vote;
  - a public one-line rationale.
- **Display notes:** a note to show instead of a match figure for a particular party or MP on a policy.
  - These are for cases where reviewers found that the weighted figure would misstate a position, for example where a party voted for a bill but opposed the part the question is about.
  - Name the party by its short or full name, or the MP by their full name.
  - The tab is required, even when empty, so that a renamed tab can't silently drop the notes.
- **Divisions:** every division, with the totals the curators worked from.

Other tabs record the method, the selection log with scores, the balance check, the review log, the sources and the conscience votes.

## Importing

`vic:import-policies` checks the whole workbook before it changes anything, and lists every problem it finds. The checks are:
- IDs are well formed and not repeated;
- statuses are known;
- every active policy has a title and a question;
- every link refers to a policy on the Policies tab and an imported division, and is not repeated;
- directions are Aye or No, and strong flags are Y or N;
- each linked division has the same printed totals as on the Divisions tab. This check means a link can never silently point at a different vote;
- every display note is for a policy on the Policies tab, names exactly one party or MP, isn't repeated, and isn't empty.

A valid workbook updates policies in place by their number, replaces their links and notes, and soft-deletes any policy no longer in the workbook. Scores are then recalculated and the quiz data republished.

New versions are imported on the admin panel's **Policy workbook** page. It runs the same checks and lists every problem on the page. Each successful import keeps a copy of the file named by its SHA-256 hash, and records who uploaded it and when.

Only policies with the status Ready are published. Policies still in Review are scored too, so reviewers can check them:
- in the admin panel;
- on the real quiz, through a **preview link** created on the Policy workbook page.
  - Preview links expire after 14 days.
  - The pages they open are not cached, indexed by search engines, or sent as a referrer to other sites.

## Rules for the human review

- Edit only the workbook. Policy text is never changed in the app.
- Once reviewers have edited the workbook, do not rerun the curation scripts. They rebuild the workbook from scratch and would overwrite those edits.
- Do not ask the project owner for views on issues, parties or wording, only about logistics.
