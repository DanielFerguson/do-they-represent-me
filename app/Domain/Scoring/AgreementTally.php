<?php

namespace App\Domain\Scoring;

use App\Enums\AgreementCategory;
use App\Enums\VoteValue;

/**
 * Counts how a party or member voted on a policy's linked divisions and
 * turns the counts into an agreement score.
 *
 * Follows TheyVoteForYou: strong votes (second and third readings) weigh 25
 * and other votes 5, and agreement is the weighted share of votes that went
 * the policy's "agree" way. Unlike TheyVoteForYou, absences are left out of
 * the score: Victoria does not record pairs, so an absence may be a pair or
 * an illness. Absences are still counted so they can be shown.
 */
final class AgreementTally
{
    public const NORMAL_WEIGHT = 5;

    public const STRONG_WEIGHT = 25;

    /**
     * With no strong votes, a score needs at least this many votes.
     */
    public const MIN_NORMAL_VOTES = 2;

    public int $same = 0;

    public int $sameStrong = 0;

    public int $differ = 0;

    public int $differStrong = 0;

    public int $absent = 0;

    public int $absentStrong = 0;

    /**
     * @param  ?VoteValue  $vote  how they voted, or null if they could have voted but did not
     */
    public function record(?VoteValue $vote, VoteValue $agreeWhen, bool $isStrong): void
    {
        if ($vote === null) {
            $isStrong ? $this->absentStrong++ : $this->absent++;
        } elseif ($vote === $agreeWhen) {
            $isStrong ? $this->sameStrong++ : $this->same++;
        } else {
            $isStrong ? $this->differStrong++ : $this->differ++;
        }
    }

    public function category(): AgreementCategory
    {
        $votes = $this->same + $this->sameStrong + $this->differ + $this->differStrong;

        if ($votes === 0) {
            return AgreementCategory::DidNotVote;
        }

        if ($this->sameStrong + $this->differStrong === 0 && $votes < self::MIN_NORMAL_VOTES) {
            return AgreementCategory::NotEnough;
        }

        return AgreementCategory::forAgreement($this->weightedAgreement());
    }

    /**
     * The agreement score from 0 to 1, or null when there is too little
     * record to state one.
     */
    public function agreement(): ?float
    {
        return $this->category()->isScored() ? round($this->weightedAgreement(), 4) : null;
    }

    /**
     * @return array{votes_same: int, votes_same_strong: int, votes_differ: int, votes_differ_strong: int, votes_absent: int, votes_absent_strong: int, agreement: ?float, category: string}
     */
    public function toRecord(): array
    {
        return [
            'votes_same' => $this->same,
            'votes_same_strong' => $this->sameStrong,
            'votes_differ' => $this->differ,
            'votes_differ_strong' => $this->differStrong,
            'votes_absent' => $this->absent,
            'votes_absent_strong' => $this->absentStrong,
            'agreement' => $this->agreement(),
            'category' => $this->category()->value,
        ];
    }

    private function weightedAgreement(): float
    {
        $agreeing = self::NORMAL_WEIGHT * $this->same + self::STRONG_WEIGHT * $this->sameStrong;
        $total = $agreeing + self::NORMAL_WEIGHT * $this->differ + self::STRONG_WEIGHT * $this->differStrong;

        return $agreeing / $total;
    }
}
