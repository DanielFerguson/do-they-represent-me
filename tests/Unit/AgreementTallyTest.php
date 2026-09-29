<?php

use App\Domain\Scoring\AgreementTally;
use App\Enums\AgreementCategory;
use App\Enums\VoteValue;

/**
 * @param  list<array{0: ?VoteValue, 1: bool}>  $votes  each vote and whether it was a strong vote; "agree" is Aye
 */
function tally(array $votes): AgreementTally
{
    $tally = new AgreementTally;

    foreach ($votes as [$vote, $isStrong]) {
        $tally->record($vote, VoteValue::Aye, $isStrong);
    }

    return $tally;
}

it('weights strong votes five times normal votes', function () {
    $tally = tally([[VoteValue::Aye, true], [VoteValue::No, false]]);

    expect($tally->agreement())->toBe(0.8333)
        ->and($tally->category())->toBe(AgreementCategory::For1);
});

it('leaves absences out of the score but counts them', function () {
    $tally = tally([[VoteValue::Aye, false], [VoteValue::Aye, false], [null, true], [null, false]]);

    expect($tally->toRecord())->toBe([
        'votes_same' => 2,
        'votes_same_strong' => 0,
        'votes_differ' => 0,
        'votes_differ_strong' => 0,
        'votes_absent' => 1,
        'votes_absent_strong' => 1,
        'agreement' => 1.0,
        'category' => 'for3',
    ]);
});

it('gives no score for a single normal vote', function () {
    $tally = tally([[VoteValue::No, false], [null, true]]);

    expect($tally->category())->toBe(AgreementCategory::NotEnough)
        ->and($tally->agreement())->toBeNull();
});

it('scores a single strong vote', function () {
    expect(tally([[VoteValue::No, true]])->category())->toBe(AgreementCategory::Against3);
});

it('records a subject who never voted as did not vote', function () {
    $tally = tally([[null, true], [null, false]]);

    expect($tally->category())->toBe(AgreementCategory::DidNotVote)
        ->and($tally->agreement())->toBeNull();
});
