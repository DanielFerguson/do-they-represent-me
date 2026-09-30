# Methodology

How *Do They Represent Me?* turns the Parliament of Victoria's records into quiz results, written so anyone can check the work, rebuild the results from scratch, and draft the public methodology page.

| Document | What it covers |
|---|---|
| [Data pipeline](data-pipeline.md) | Where the voting records come from, how they are parsed, matched to MPs and checked |
| [Policy curation](policy-curation.md) | How the quiz questions were chosen, worded, linked to votes and reviewed |
| [Scoring](scoring.md) | How party positions, agreement scores and a voter's match are calculated |

## What is recorded where

| Item | Location | Notes |
|---|---|---|
| Code for parsing, importing and scoring | this repository | Each result can be traced to a commit. |
| Curated reference data: houses, parties, electorates, members, dated memberships, name aliases, division date corrections, elections, ballot party names | `database/data/*.csv` | Edited by hand. `vic:import-data` loads them, except the date corrections, which are applied when proceedings are imported, and the ballot names, which the candidate importer reads. |
| Suburbs and postcodes to districts | `database/data/localities.csv` | Built by `vic:build-localities` from ABS files kept in `storage/app/private/reference-data/abs/` (git-ignored; their hashes are in [Data pipeline](data-pipeline.md#suburbs-and-postcodes)). |
| Candidates | `database/data/candidates/{election}.csv` | Loaded by `vic:import-candidates`. The 2022 list is test data, converted from VEC pages saved in `storage/app/private/reference-data/vec-2022/`. |
| Published quiz data | the `stance_snapshots` table, served at `/stances/{sha256}.json` | Every published version is kept, so a shared results link keeps the data it was made with. Each records the SHA-256 of the workbook it came from. |
| Raw proceedings documents | the default storage disk, under `proceedings/{house}/` | In production this is the private Laravel Cloud bucket. |
| Policy workbook: questions, linked votes, display notes, review log and sources | the default storage disk: every imported version at `policy-research/workbooks/{sha256}.xlsx` | It is the only place policy text is edited. New versions are uploaded on the admin panel's Policy workbook page. Each import is recorded in `policy_imports` with its SHA-256 hash and who uploaded it. |
| Curation brief, report, working data and scripts | `storage/app/private/policy-research/` locally; `policy-research/` in the private bucket | Git-ignored, because it holds draft questions still under review. The bucket holds the workbook, `REPORT.md`, `TASK.md` and a dated snapshot of the whole folder: `policy-research/archive/policy-research-2026-09-30.tar.gz` (SHA-256 `e37fe22f…93dc7b5`). |

## Rebuilding the results from scratch

Run these on a fresh database with the policy workbook in place. Every command is idempotent.

```bash
php artisan migrate
php artisan vic:import-data
php artisan vic:sync-proceedings
php artisan vic:audit
php artisan vic:import-policies
php artisan vic:import-candidates 2026   # once the VEC publishes the list
```

1. `vic:import-data` loads the curated reference data, including the localities. To rebuild `localities.csv` itself from the ABS files, run `vic:build-localities` first.
2. `vic:sync-proceedings` downloads every Votes and Proceedings and Minutes document for the 60th Parliament, stores the raw files, imports every division, then recalculates scores.
   - If the Parliament's website is unavailable but the raw files are in storage, run `vic:reparse` instead.
3. `vic:audit` must pass with no failed checks before any results are published.
4. `vic:import-policies` checks and loads the workbook, then recalculates scores and publishes the quiz data. With no path it re-imports the last workbook imported, or at first `policy-research/policy-workbook-v2.xlsx`. It prints the workbook's SHA-256 hash, which is also recorded in the published data.

`vic:score` recalculates party positions and agreement scores, and publishes the quiz data, on its own. The commands above run it automatically.

To confirm a rebuild, compare division, vote and party-position counts with production, and compare party agreement scores with the policy workbook (see [Scoring: checking the results](scoring.md#checking-the-results)).

**Rehearsal, 30 September 2026.** The steps above were run on an empty local database, fetching all 154 proceedings documents from the Parliament again (about three minutes) and importing workbook `64375119…efb307f`. The result matched production exactly:
- 1,043 divisions, 53,292 votes, 7,748 party positions and 2,748 agreement scores;
- the SHA-256 of every agreement score, and of the quiz data including policies in review, were identical.

To repeat the check, compare `hash('sha256', app(StanceSnapshots::class)->previewBody(fn ($policy) => $policy->slug))` in both databases.

## Hosting notes

- **Caching.** Public pages are sent with `Cache-Control: public, max-age=60, s-maxage=300, stale-while-revalidate=86400` and an ETag, so browsers can reuse them. On 30 September 2026, Laravel Cloud's shared edge network did not cache HTML even with these headers (`cf-cache-status: DYNAMIC`); it caches static files only. Warm pages took about 110–130 ms to start arriving in Melbourne, so no app-side caching was added.
- **Cookies.** The app sets no cookies on public pages. Cloudflare, which carries Laravel Cloud's traffic, adds a 30-minute bot-detection cookie, `__cf_bm`. The privacy page says so.
