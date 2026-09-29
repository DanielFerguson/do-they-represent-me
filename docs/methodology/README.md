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
| Curated reference data: houses, parties, electorates, members, dated memberships, name aliases, division date corrections | `database/data/*.csv` | Edited by hand. `vic:import-data` loads everything except the date corrections, which are applied when proceedings are imported. |
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
```

1. `vic:import-data` loads the curated reference data.
2. `vic:sync-proceedings` downloads every Votes and Proceedings and Minutes document for the 60th Parliament, stores the raw files, imports every division, then recalculates scores.
   - If the Parliament's website is unavailable but the raw files are in storage, run `vic:reparse` instead.
3. `vic:audit` must pass with no failed checks before any results are published.
4. `vic:import-policies` checks and loads the workbook, then recalculates scores and publishes the quiz data. With no path it re-imports the last workbook imported, or at first `policy-research/policy-workbook-v2.xlsx`. It prints the workbook's SHA-256 hash, which is also recorded in the published data.

`vic:score` recalculates party positions and agreement scores, and publishes the quiz data, on its own. The commands above run it automatically.

To confirm a rebuild, compare division, vote and party-position counts with production, and compare party agreement scores with the policy workbook (see [Scoring: checking the results](scoring.md#checking-the-results)).
