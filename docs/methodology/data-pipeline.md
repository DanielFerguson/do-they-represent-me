# Data pipeline

## Scope

The 60th Parliament of Victoria, from its first sitting in December 2022 until it expires before the 28 November 2026 election. Both houses are covered:

- the Legislative Assembly (lower house, 88 districts);
- the Legislative Council (upper house, 40 members in 8 regions).

## Sources

**Votes.** The official record of each sitting week:
- Assembly: *Votes and Proceedings*;
- Council: *Minutes of the Proceedings*.

These documents are published by the Parliament of Victoria on parliament.vic.gov.au.
- They are found through the site's house-papers search (document type 25, per house).
- The `.docx` version is used where it is published; otherwise the PDF.
- Each document records, for every division:
  - the question put;
  - the business item (the bill or motion);
  - who was in the chair;
  - the printed Ayes and Noes totals and names;
  - tellers, where recorded;
  - the result.

Hansard was considered as the source and rejected. It splits each sitting day into about 100 pages, while one proceedings document covers a whole sitting week: 154 documents for the whole Parliament.

**Members.** The Parliament's members pages give names and current parties. They carry no dated party history, so the history below was curated by hand.

**Reference data** is curated by hand in `database/data/`:

| File | Contents |
|---|---|
| `houses.csv` | The two houses and their seat counts |
| `parliaments.csv` | The 60th Parliament's start date |
| `parties.csv` | Parties, including a whipless "Independent" entry that is never scored as a bloc |
| `electorates.csv` | The 88 districts and 8 regions |
| `members.csv` | Every member who sat in the 60th Parliament |
| `memberships.csv` | One row per continuous stint in one house for one party, with start and end dates. It covers by-elections, resignations, deaths and party changes. |
| `member_aliases.csv` | Printed spellings that differ from a member's name |
| `division_date_corrections.csv` | Divisions whose document is dated differently from the vote, each with its source |

## Fetching

`ParliamentClient` only contacts the Parliament's own hosts over HTTPS. It identifies itself with a user agent that includes the site URL, retries failures, caps download size, and waits about a second between requests.

Each raw document is stored, with its SHA-256 hash, before parsing. An unchanged document is not parsed again. `vic:reparse` rebuilds every division from the stored copies without contacting the Parliament.

## Parsing

`ProceedingsParser` reads the document's paragraphs in order.
- Sitting headings give the sitting number and date.
- Numbered item headings give the business item. Unnumbered bill headings do too; these appear in Council committee-of-the-whole supplements.
- Each "The House/Council/Committee divided" paragraph starts a division. The parser then reads:
  - the question: the nearest earlier paragraph containing "That…", which is needed because some divisions only say "Question — put.";
  - the Ayes and Noes headings and totals, and the name lists that follow them;
  - the tellers, where recorded;
  - the result.
- Name lists are separated by semicolons, or by commas when every segment is a name.
- PDF text is rebuilt into paragraphs:
  - drop-capital letters are rejoined;
  - words hyphenated across lines are rejoined.

`DivisionStageClassifier` labels each division from the question put. The stages are:
- third reading, including combined "second and third time" questions;
- reasoned amendment;
- second reading;
- bill introduction;
- amendment (committee votes and clauses);
- procedural;
- other bill vote;
- motion.

The labels are for browsing. Policy weighting uses the curators' own flags (see [Scoring](scoring.md)).

## Matching names to members

`MemberResolver` matches each printed name to a member who held a seat **in that house on that date**, using their name or a curated alias. Matching ignores case, apostrophe and dash style, honorifics and punctuation. Two names printed without a separator are split and matched separately. Anything still unmatched is kept in `unresolved_names`, and its division is flagged for review.

Each vote stores the voter's party **on the day of the vote**, taken from the dated memberships. This is how votes cast before and after a party change are counted under the right party.

## Checks

`vic:audit` runs daily and must pass before results are published. Each check lists examples when it fails:
- recorded votes differ from the printed Ayes and Noes totals;
- voter names are unresolved;
- divisions are flagged for review;
- a division has more voters than the house has seats;
- documents failed to import.

When `vic:import-policies` loads the curated policies, it also checks every linked division against the totals the curators worked from (see [Policy curation](policy-curation.md)).

## Known limitations

- **No pairs.** The proceedings do not record pairs, so a missing vote may be a pair, an illness or a choice. Absences are shown but never scored.
- **Dates that differ from the document.** One Council committee division (LC-60-025-01) is printed in a Minutes supplement dated 22 June 2023, but Hansard records the vote on 20 June 2023.
  - Corrections like this are listed, with their source, in `database/data/division_date_corrections.csv`.
  - They are applied before voters are matched, so everything downstream uses the corrected date: who held a seat, each voter's party, and the dates shown.
- **Missing committee divisions.** Some committee-stage divisions appear in Hansard but not in the proceedings documents, so they cannot yet be linked to policies.
- **A double-issued document.** Votes and Proceedings 87–89 of 2024 was issued twice. Divisions are keyed by house, parliament, sitting number and sequence, so the second copy updates the same divisions rather than duplicating them.
- **Free votes are not detected.** Conscience votes are not flagged automatically yet. Where one is flagged, no party is given a position on it.
