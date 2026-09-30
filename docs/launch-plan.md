# Launch plan: M5 and M6

Written 30 September 2026, so the last three weeks before the election can be run from a checklist instead of from memory.

## Status

M0 to M4 are done and live at https://dotheyrepresentme.com, about five weeks ahead of the original schedule:
- the data pipeline, scoring and the policy workbook importer;
- the suburb finder, district pages, evidence pages, quiz and results;
- security headers, caching, accessibility, backups and the rebuild rehearsal (see [Hosting notes](methodology/README.md#hosting-notes)).

Since then (30 Sep 2026):
- the "Gazette" redesign, with the quiz as the home page, share images and a beta note;
- a contact form, stored in the admin panel and emailed to the owner through Namecheap Private Email;
- the final review of the questions, made by AI reviewers at the owner's choice (see [Policy curation](methodology/policy-curation.md)): 22 published, 2 dropped;
- corrected dates and titles for Council committee divisions;
- the soft launch, with testers using the live site.

**M5 (2–10 Nov) and M6 (11–17 Nov) were deliberately deferred and are planned as one run, starting Monday 2 November.** Most of the work is triggered by dates or by tester feedback:
- 3 Nov: the writs issue and Parliament expires.
- 9 Nov, noon: nominations close. The ballot draws start at 4pm.
- The VEC hasn't said how or when it will publish the candidate lists, so the 2026 converter can't be written until it does.

Building the date-independent code early would only mean guessing what the testers will find. The critical path is human review (the questions) and the soft launch, not engineering. Starting the soft launch as soon as 15 questions are published gives M5 more feedback to work with.

## Don't wait until 2 November

| Item | Why now |
|---|---|
| **Parliament of Victoria permission.** **Settled.** The Clerks of both houses decided (letter of 11 Feb 2026, seen 30 Sep 2026) that the Presiding Officers' permission is not required: Votes, Minutes and Hansard are public and can be used for the project. | Their one condition: the site must never suggest endorsement by, or partnership with, the Presiding Officers, the parliamentary departments or the Parliament of Victoria. Every footer and the About page say the project is independent and not affiliated with the Parliament. |
| **Set `SITE_CONTACT_EMAIL`** as a Cloud environment variable. **Done 30 Sep 2026.** | The contact form emails it every message, through Namecheap Private Email over SMTP (the `MAIL_*` variables on Cloud). Tested on production. |
| **Set `SITE_AUTHORISATION`**, a Cloud environment variable shown in every footer and on the About page. **Done 30 Sep 2026.** | Never commit the text or the address: this repository is public. The approved wording is kept privately. It uses the full street address, because a town alone isn't enough (VEC Determination 018/2026 cl 13.7; not legal advice). |
| **Sweep the copy for wording that goes stale on 3 November**, in the last week of October. | After the writs there are no sitting MPs. "At present", "current", "sitting", "your MLA" and "has no member" must be true on both sides of 3 Nov. "MP in the 60th Parliament" is the model. |
| **Restore points.** Serverless Postgres doesn't support manual snapshots, so note the time before each risky step and rely on point-in-time recovery (7 days). | Beyond 7 days everything except contact messages can be rebuilt from the stored documents, the repository data and the uploaded workbook. |
| **Check the admin panel** on dotheyrepresentme.com under the admin CSP. **Done 30 Sep 2026:** login, Messages and "Mark handled", the workbook upload and the preview-link modal. | The admin doesn't work on the laravel.cloud address, because production forces the root URL. |
| **Publish at least 15 questions.** **Done 30 Sep 2026:** 22 published from `policy-workbook-v3.xlsx` after the AI final review; P17 and P28 dropped for too little record. | A published question where no party has enough votes for a figure fails `vic:audit`, unless a note or more votes are added. |

## After questions are published, before 2 November: the PDF cross-check

`vic:audit` proves the recorded votes match the printed totals and that every voter held a seat that day. It can't see a parsing error that keeps the counts right, such as the Aye and No lists swapped. To close that gap:
1. Download the PDF of every Votes and Proceedings or Minutes document that holds a division linked to a published question. There were 40 such documents for 92 linked divisions at the last count. Every `ProceedingsDocument` has a `pdf_url`; use `sourceUrl()`. Store them in the git-ignored `storage/app/private/reference-data/`. **Get explicit permission again before downloading**: name the files, the source and the size.
2. Run `pdftotext` on each, and compare every linked division's Aye and No name lists with the database.
3. For any difference, add a parser fixture test, fix the parser, run `vic:reparse`, and re-run `vic:audit`.
4. Record the result (how many divisions, how many differences) in [Data pipeline](methodology/data-pipeline.md). Commit nothing else from the check.

Time-box it to one session.

## M5 build list (from 2 November)

1. **`vic:launch-check`**, a go/no-go command (`app/Console/Commands/LaunchCheck.php`), with `--launch` for the final checks. It exits non-zero on any failure.
   - Checks: at least 15 published questions; `vic:audit` passes; the live snapshot isn't stale (add `StanceSnapshots::isStale()`, comparing `current()->hash` with a fresh hash of `StanceSnapshotBuilder::build()`); `SITE_CONTACT_EMAIL` and `SITE_AUTHORISATION` are set; `APP_URL` is https and production has `APP_DEBUG=false`; every district has localities.
   - Added by `--launch`: the 2026 election has candidates in all 88 districts and 8 regions and `candidates_as_of` is set; the data is frozen.
   - Print the facts to record: published count, snapshot hash, workbook hash (the latest `PolicyImport`), divisions, votes, documents, latest sitting date and candidate count.
   - A Pest test with a passing case and one failing case per check, like `AuditDivisionsTest`.
2. **Data freeze.** `services.parliament_vic.sync_frozen` (from `PARLIAMENT_VIC_SYNC_FROZEN`, default false) in `config/services.php`. The daily sync in `routes/console.php` gets `->skip(fn () => config('services.parliament_vic.sync_frozen'))`. The audit keeps running and manual commands are unaffected. This stops published results changing mid-campaign, and matters because Cloud's logs keep only one day, so a failing audit would otherwise go unseen.
3. **Candidate pipeline.**
   - A migration for a nullable `elections.candidates_as_of` date.
   - `vic:import-candidates` gets `--as-of=YYYY-MM-DD` (required for a real import, and stored) and `--check` (validate and report, change nothing).
   - After an import, print per-electorate counts and a **reconciliation**: sitting MPs with no matching candidate. Verify that list by hand before any "not standing" line goes live, so a name mismatch can't wrongly say an MP isn't recontesting.
   - District pages show "Candidate list as published by the VEC on …". `DistrictProfile::record()` adds `standing`, and `member-record.blade.php` shows "Standing again in …" or "Not on the candidate list", and nothing while the election has no candidates.
   - Extend `ImportCandidatesTest` and `DistrictPagesTest` to cover all of this.
4. **Corrections links.** **Done 30 Sep 2026:** "Report a problem" on district and question pages opens the contact form with the question or district filled in.
5. **PageSpeed reruns** of the home page, results and a question page, using `pagespeed.web.dev/analysis?url=…` in the browser pane (the keyless API is usually over quota). The M4 home page run never finished.
6. **Feedback triage.** Sort each report into one of four kinds:
   - a **data error**: fix it at the source (an alias, a date correction or the parser), with a test;
   - **wording**: send it to the reviewers, and change it only in the policy workbook, never in the app;
   - a **UI bug**: fix it;
   - a **request**: note it, and do nothing before launch unless it fixes a real barrier.

## Dated runbook

| When | What |
|---|---|
| **Mon 2 Nov** | Start M5. Triage the soft-launch feedback and build items 1 to 5. The fix window runs to 8 Nov. |
| **Tue 3 Nov** | Writs issue and Parliament expires. Check the wording live. |
| **Thu 5 Nov** | **Final sync**: `vic:sync-proceedings` over the whole Parliament, `vic:audit`, `vic:import-policies`, then `vic:launch-check`. Note the time as a restore point. Set `PARLIAMENT_VIC_SYNC_FROZEN=true` on Cloud and redeploy. Run the local rebuild rehearsal (see [Rebuilding the results](methodology/README.md#rebuilding-the-results-from-scratch)) and compare hashes with production. Record the frozen data's hash in the docs. |
| **Mon 9 Nov** | Nominations close at noon. Ballot draws start at 4pm. |
| **Tue 10 Nov** | **Candidates.** Write the converter for the VEC's format, time-boxed (the 2022 conversion is the precedent). Run `--check`, commit `database/data/candidates/2026.csv` and any new names in `party_ballot_names.csv`, deploy, note the time as a restore point, then run `./vendor/bin/cloud command:run production --cmd='php artisan vic:import-candidates 2026 --as-of=…' -n`. Verify every electorate's count against the VEC's pages, at least 10 random electorates and all 8 regions by name and order, and the MP reconciliation list. |
| **Wed 11 – Fri 13 Nov** | Final QA (below). After 10 Nov the workbook changes only for factual errors. |
| **Sat 14 – Mon 16 Nov** | Buffer. No feature work. Re-check the VEC list for withdrawals, and re-import if it changed. |
| **Tue 17 Nov** | `vic:launch-check --launch` is green on production, plus headers, sitemap and PageSpeed. Announce. |
| **Wed 18 Nov, Sat 28 Nov** | Spot-check on the first day of early voting and on election day. From 29 Nov the candidates stop showing, because `Election::upcoming()` moves on. |

## Final QA (11–13 November)

- `vic:launch-check --launch` on production.
- **Browser pane, desktop and 320 px:** suburb → district → quiz → results, a shared results link that reproduces the results, keyboard only, and dark mode.
- **iOS Simulator:** Mobile Safari on the finder, quiz, results and a district page with candidates.
- **PageSpeed:** home, quiz, results, a district page and a question page, on mobile and desktop.
- **Console:** no CSP violations.
- **Admin:** login, the workbook upload and the preview-link modal.
- **Headers and pages:** `curl -I` for the CSP, cache and security headers; the `www` redirect; `robots.txt`; the sitemap (published questions only); and a 404.
- **Neutrality read-through:** candidates in ballot order only, with no ranking or party colours, and the About and methodology pages match what the site does.
- **Load, for information only:** a short, modest load test to report headroom. Cloud settings stay as they are.

## Go/no-go and rollback

- Launch when `vic:launch-check --launch` is green. (The Parliament of Victoria question is settled: no permission is required.)
- **Code:** redeploy the last good commit.
- **Data:** use point-in-time recovery (7 days) to the time noted before the freeze or the candidate import, or rebuild from scratch. The from-scratch rebuild has already matched production byte for byte.

## Decisions already taken

- **Capacity:** leave Cloud settings as they are (scale-to-zero, `flex-512mb`, 0.25 CU). The Starter plan can't autoscale (one replica), and Cloud's edge caches neither HTML nor the data JSON, so a traffic spike hits the origin.
- **59th-Parliament backfill:** dropped. The data scope is the 60th Parliament only, and it would add nothing for this election.
- **Candidates:** shown in ballot order only. Neutrality applies to everything on the candidate pages.
- **Final review:** made by AI reviewers, not humans (owner decision, 30 Sep 2026). The site says so; never claim human reviewers.
- **Contact email:** Namecheap Private Email over SMTP, not Resend (avoids a paid plan).
- **Policy text:** changed only in the workbook. Once reviewers edit it directly, don't re-run the scripts used to build it.
- **Analytics and cookies:** none. The only cookie is Cloudflare's `__cf_bm`, which the privacy page discloses.
- **Alerting:** Cloud's logs only. That is another reason to freeze the data after the final sync.
