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
| Curated reference data: houses, parties, electorates, members, dated memberships, name aliases | `database/data/*.csv` | Edited by hand and loaded with `vic:import-data`. |
| Raw proceedings documents | the default storage disk, under `proceedings/{house}/` | In production this is the private Laravel Cloud bucket. |
| Policy workbook: questions, linked votes, review log and sources | the default storage disk, at `policy-research/policy-workbook-v2.xlsx` | It is the only place policy text is edited. |
| Curation brief, report, working data and scripts | `storage/app/private/policy-research/` | Git-ignored, because it holds draft questions still under review. |

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
4. `vic:import-policies` checks and loads the workbook, then recalculates scores. It prints the workbook's SHA-256 hash; keep it with any published results.

`vic:score` recalculates party positions and agreement scores on its own. The commands above run it automatically.

To confirm a rebuild, compare division, vote and party-position counts with production, and compare party agreement scores with the policy workbook (see [Scoring: checking the results](scoring.md#checking-the-results)).
