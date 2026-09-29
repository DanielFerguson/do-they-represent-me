<?php

use App\Domain\VicParliament\Members\MemberResolver;
use App\Models\House;
use App\Models\Member;
use App\Models\Party;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->artisan('vic:import-data');
    $this->resolver = app(MemberResolver::class);
    $this->assembly = House::query()->where('slug', 'assembly')->firstOrFail();
    $this->council = House::query()->where('slug', 'council')->firstOrFail();
});

function memberSlug(?int $id): ?string
{
    return $id === null ? null : Member::query()->whereKey($id)->value('slug');
}

it('resolves a printed name to the member seated on that date', function () {
    $voter = $this->resolver->resolve('Jacinta Allan', $this->assembly, CarbonImmutable::parse('2025-07-29'));

    expect(memberSlug($voter?->memberId))->toBe('jacinta-allan');
});

it('respects by-election handovers', function () {
    $before = CarbonImmutable::parse('2023-08-15');
    $after = CarbonImmutable::parse('2023-09-05');

    expect($this->resolver->resolve('Nicole Werner', $this->assembly, $before))->toBeNull()
        ->and(memberSlug($this->resolver->resolve('Nicole Werner', $this->assembly, $after)?->memberId))->toBe('nicole-werner')
        ->and(memberSlug($this->resolver->resolve('Ryan Smith', $this->assembly, CarbonImmutable::parse('2023-06-01'))?->memberId))->toBe('ryan-smith')
        ->and($this->resolver->resolve('Ryan Smith', $this->assembly, $after))->toBeNull();
});

it('only matches members of the given house', function () {
    expect($this->resolver->resolve('Jaclyn Symes', $this->assembly, CarbonImmutable::parse('2025-08-26')))->toBeNull()
        ->and($this->resolver->resolve('Jaclyn Symes', $this->council, CarbonImmutable::parse('2025-08-26')))->not->toBeNull();
});

it('uses curated aliases and tolerates typographic differences', function () {
    $date = CarbonImmutable::parse('2025-08-26');

    expect(memberSlug($this->resolver->resolve('Rickie-Lee Tyrrell', $this->council, $date)?->memberId))->toBe('rikkielee-tyrrell')
        ->and(memberSlug($this->resolver->resolve('Nicholas McGowan', $this->council, $date)?->memberId))->toBe('nicholas-mcgowan')
        ->and(memberSlug($this->resolver->resolve("Danny O'Brien", $this->assembly, $date)?->memberId))->toBe('danny-obrien');
});

it('tells apart members who share a surname', function () {
    $date = CarbonImmutable::parse('2025-07-29');

    expect(memberSlug($this->resolver->resolve('Tim Bull', $this->assembly, $date)?->memberId))->toBe('tim-bull')
        ->and(memberSlug($this->resolver->resolve('Josh Bull', $this->assembly, $date)?->memberId))->toBe('josh-bull');
});

it('splits two names printed without a separator', function () {
    $result = $this->resolver->resolveMany(['Georgie Purcell Samantha Ratnam', 'Katherine Copsey'], $this->council, CarbonImmutable::parse('2023-08-02'));

    expect($result['unresolved'])->toBe([])
        ->and(array_map(fn ($voter) => memberSlug($voter->memberId), $result['resolved']))->toBe(['georgie-purcell', 'samantha-ratnam', 'katherine-copsey']);
});

it('reports names it cannot resolve', function () {
    $result = $this->resolver->resolveMany(['Not A Member'], $this->assembly, CarbonImmutable::parse('2025-07-29'));

    expect($result['unresolved'])->toBe(['Not A Member'])->and($result['resolved'])->toBe([]);
});

it('records the party a member belonged to on the day of the division', function (string $name, string $house, string $date, string $party) {
    $voter = $this->resolver->resolve($name, $house === 'assembly' ? $this->assembly : $this->council, CarbonImmutable::parse($date));

    expect(Party::query()->whereKey($voter?->partyId)->value('short_name'))->toBe($party);
})->with([
    'Fowles before leaving Labor' => ['Will Fowles', 'assembly', '2023-08-01', 'ALP'],
    'Fowles after leaving Labor' => ['Will Fowles', 'assembly', '2023-09-01', 'IND'],
    'Deeming expelled from the party room' => ['Moira Deeming', 'council', '2024-06-01', 'IND'],
    'Deeming readmitted' => ['Moira Deeming', 'council', '2025-06-01', 'LIB'],
    'Deeming joins Family First' => ['Moira Deeming', 'council', '2026-09-10', 'FFV'],
    'Somyurek as DLP' => ['Adem Somyurek', 'council', '2023-06-01', 'DLP'],
]);
