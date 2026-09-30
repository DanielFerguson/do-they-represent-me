<?php

namespace App\Domain\Stances;

use App\Enums\AgreementCategory;
use App\Models\Member;
use App\Models\Party;
use App\Models\Policy;
use App\Models\PolicyAgreement;

/**
 * What the public site shows for one party or MP on one policy: their
 * agreement figure and its label, or the reviewers' display note instead.
 *
 * Every public page and the published quiz data go through this class, so a
 * display note can never be skipped: where one applies there is no figure,
 * and the party or MP is left out of matching on that policy.
 *
 * @phpstan-type Stance array{agreement: ?float, label: ?string, note?: string}
 */
final readonly class SubjectStance
{
    public function __construct(
        public ?float $agreement,
        public ?string $label,
        public ?string $note = null,
        public ?PolicyAgreement $record = null,
    ) {}

    /**
     * The stance for the subject, or null when they have no record on the
     * policy and no note (for example an MLA on a Council-only question).
     */
    public static function for(Policy $policy, Party|Member $subject, ?PolicyAgreement $record): ?self
    {
        $note = self::noteFor($policy, $subject);

        if ($note !== null) {
            return new self(null, null, $note, $record);
        }

        if ($record === null) {
            return null;
        }

        return new self(
            $record->agreement === null ? null : round((float) $record->agreement, 4),
            AgreementCategory::from($record->category)->label(),
            null,
            $record,
        );
    }

    /**
     * How many linked divisions the subject voted in (for a party, took a
     * position on), and how many they could have.
     *
     * @return array{voted: int, possible: int}
     */
    public function votes(): array
    {
        $record = $this->record;

        if ($record === null) {
            return ['voted' => 0, 'possible' => 0];
        }

        $voted = $record->votes_same + $record->votes_same_strong + $record->votes_differ + $record->votes_differ_strong;

        return ['voted' => $voted, 'possible' => $voted + $record->votes_absent + $record->votes_absent_strong];
    }

    /**
     * The text to show: the note, the category label, or "Not enough votes".
     */
    public function text(): string
    {
        return $this->note ?? $this->label ?? AgreementCategory::NotEnough->label();
    }

    /**
     * @return Stance
     */
    public function toArray(): array
    {
        $stance = ['agreement' => $this->agreement, 'label' => $this->label];

        return $this->note === null ? $stance : [...$stance, 'note' => $this->note];
    }

    private static function noteFor(Policy $policy, Party|Member $subject): ?string
    {
        foreach ($policy->display_notes ?? [] as $note) {
            if ($note['subject_type'] === $subject->getMorphClass() && $note['subject_id'] === $subject->getKey()) {
                return $note['note'];
            }
        }

        return null;
    }
}
