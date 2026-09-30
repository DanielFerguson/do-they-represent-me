# Scoring

The method follows TheyVoteForYou's ([`distance.rb`](https://github.com/openaustralia/publicwhip/blob/main/app/lib/distance.rb) and [`policy_person_distance.rb`](https://github.com/openaustralia/publicwhip/blob/main/app/models/policy_person_distance.rb)), with changes for the Parliament of Victoria's records. They are marked **Victorian change**. `vic:score` runs every calculation below. It runs after each proceedings sync, reparse and policy import.

## Who could vote

A member could vote in a division if a dated membership shows they held a seat in that house on the sitting date.

**Victorian change: presiding officers.** If the member in the chair did not vote, they are neither eligible nor absent. The Speaker of the Assembly does not vote, and should not be counted as missing. The Council's President votes normally, and that vote is counted.

## Party positions

For each division and each party, using each member's party **on the day of the vote** (`PartyPositionCalculator`):

- **ayes** and **noes:** the party's members who voted each way;
- **eligible:** the party's members who could have voted;
- **position:**
  - Aye or No, by a strict majority of those who voted;
  - Split, for a tie;
  - None, when no member voted.

Some parties are never given positions:
- **Independents are never a bloc.** Members of a whipless party are scored one by one.
- **Free votes.** On a division flagged as a free (conscience) vote, no party has a position.

## Agreement with a policy

Each linked division says which vote, Aye or No, matches the policy's "agree" answer, and whether it is a **strong** vote. The curators' rule is that second and third readings are strong, and everything else, including reasoned amendments, committee votes and motions, is normal.

For each party and each member, every linked division they could vote in counts as **same**, **differ** or **absent**, and **strong** or **normal** (`AgreementTally`):
- **For a member:** their own vote. If they held no seat in that house on that day, the division is left out entirely.
- **For a party:** its position. A split or no position counts as absent.

```
agreement = (5 × same + 25 × same_strong) / (5 × (same + differ) + 25 × (same_strong + differ_strong))
```

**Victorian change: absences are left out of the score.** Victoria does not record pairs, so a missing vote may be a pair, an illness or a choice. Absences are counted and can be shown as "did not vote", but they never move the score. TheyVoteForYou scores an absence as half agreeing, weighted 1 for a normal vote and 25 for a strong one. Its weights for votes cast are the same as here: 5 for normal and 25 for strong.

### Categories

| Agreement | Category | Label |
|---|---|---|
| ≥ 0.95 | `for3` | Consistently for |
| ≥ 0.85 | `for2` | Almost always for |
| ≥ 0.60 | `for1` | Generally for |
| ≥ 0.40 | `mixture` | Mixed |
| ≥ 0.15 | `against1` | Generally against |
| ≥ 0.05 | `against2` | Almost always against |
| < 0.05 | `against3` | Consistently against |

Two cases get no agreement figure:
- **Not enough votes** (`not_enough`): no strong votes and fewer than two normal votes. One normal vote is too little to state a position. This is TheyVoteForYou's rule, and like theirs it counts only votes cast.
- **Did not vote** (`did_not_vote`): the subject could have voted but never did. **Victorian change:** TheyVoteForYou shows this as not enough information too.

A member who could not vote on any linked division gets no row at all. This is how an Assembly MP is shown as having "no vote recorded" on a Council-only question.

**Display notes.** Where reviewers found that a figure would misstate a party's or MP's position, the policy workbook gives a note for that pair.
- The published data, the district pages and the evidence pages all show the note instead of the figure (`SubjectStance`).
- That party or MP is left out of matching on that policy.
- The underlying score is still calculated and visible to curators.

## Published quiz data

After each recalculation the published policies are written out as one JSON document. Only policies marked Ready in the workbook are included. The document contains:
- each policy's question and description, and a link to its evidence page;
- for each party with a record, in alphabetical order so the order means nothing:
  - its agreement figure and category label, or its note;
- for each current MP with a record on the policy, the same;
- the MPs, with their party, house and electorate, and the districts and regions, so results can show a voter's own MPs.

The document is stored exactly as served and named by the SHA-256 of its content. It records the date of the latest division and the SHA-256 of the workbook it came from. Earlier versions are kept, so a shared results link keeps working. Until a policy is published, the public quiz uses the labelled prototype data.

## Matching a voter

Matching happens in the voter's browser. Answers never leave the device. They are kept in local storage and in the part of a shared link after the `#`, which browsers do not send to servers.

- Each Agree or Disagree answer counts as 1 or 0. For each policy where a party has a score, the match is `1 − |answer − agreement|`.
- A party's overall match is the average across those policies.
- "Unsure" and "Skip" answers, and policies without a score, are left out.
- A result needs at least 5 Agree or Disagree answers.
- A party is ranked only if it has a score on at least 3 of the voter's answered questions. Otherwise it is listed separately as having too few shared votes.
- **Your MPs.** Once the voter chooses a district, the MLA for it and the five MLCs for its region are matched the same way, from their own votes, with the same minimum of 3 shared questions. An MLA has no record on a question voted on only in the Council, and an MLC none on one voted on only in the Assembly. The district is kept in the browser and in the results link (`#a=…&d=district`), like the answers.
- A party whose figure is replaced by a display note is left out on that question, and the note is shown instead.

## Checking the results

On 30 September 2026, scores calculated from the database were checked against the curation workbook's balance figures. The workbook counts a bloc as on the agree side at 60% or more and the disagree side at 40% or less.
- **Match:** Labor, the Liberals, the Nationals and the Greens were on the same side as in the workbook for every policy with a score.
- **No score:** two policies had no score. Each rests on a single normal-weight division, so the not-enough rule applies. The human reviewers have been asked whether one division is enough.

Scores can legitimately differ from the workbook in one respect. The curation data used each member's current or last party, while the app uses the party on the day of the vote. This changes figures for members who changed party, and for the independents they joined.
