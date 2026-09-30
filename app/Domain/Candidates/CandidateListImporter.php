<?php

namespace App\Domain\Candidates;

use App\Domain\VicParliament\Members\NameNormalizer;
use App\Enums\ElectorateKind;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Electorate;
use App\Models\Member;
use App\Models\MemberAlias;
use App\Models\Party;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use SplFileObject;

/**
 * Loads an election's candidates from a CSV in ballot paper order, as
 * published by the VEC after the ballot draw:
 *
 *     electorate,group,ballot_position,surname,given_names,ballot_party,member
 *
 * - electorate: the district or region slug;
 * - group: the Council group letter, blank for Assembly candidates and
 *   ungrouped Council candidates;
 * - ballot_party: the party name as printed on the ballot, blank for none;
 * - member: blank to match a sitting MP by name, an MP's slug to settle an
 *   ambiguous name, or "-" for a candidate who is not an MP.
 *
 * Every printed party name must be listed in party_ballot_names.csv, which
 * links it to a party with a record in this Parliament or marks it as
 * having none, so a misspelt name can never silently lose a party's record.
 *
 * The whole file is checked first; nothing changes unless every row is
 * valid. A valid file replaces the election's candidates.
 *
 * @phpstan-type CandidateRow array{electorate_id: int, ballot_group: ?string, ballot_position: int, surname: string, given_names: string, ballot_party: ?string, party_id: ?int, member_id: ?int}
 */
class CandidateListImporter
{
    public const HEADINGS = ['electorate', 'group', 'ballot_position', 'surname', 'given_names', 'ballot_party', 'member'];

    public function __construct(private NameNormalizer $normalizer) {}

    /**
     * @return array{candidates: int, matched: list<array{electorate: string, candidate: string, member: string}>}
     *
     * @throws InvalidCandidateList when any row is invalid
     * @throws RuntimeException when a file is missing
     */
    public function import(Election $election, string $candidatesPath, string $ballotNamesPath): array
    {
        $errors = [];
        $ballotNames = $this->ballotNames($ballotNamesPath, $errors);
        $electorates = Electorate::query()->get()->keyBy('slug');
        $members = Member::query()->get()->keyBy('slug');
        $membersByName = $this->membersByName($members->all());

        /** @var list<CandidateRow> $candidates */
        $candidates = [];
        $positions = [];
        $matched = [];

        foreach ($this->rows($candidatesPath, self::HEADINGS) as $line => $row) {
            $where = "Line {$line}";
            $electorate = $electorates->get($row['electorate']);

            if ($electorate === null) {
                $errors[] = "{$where}: unknown electorate [{$row['electorate']}].";

                continue;
            }

            $group = $row['group'] === '' ? null : $row['group'];

            if ($group !== null && ($electorate->kind !== ElectorateKind::Region || preg_match('/^[A-Z]{1,2}$/', $group) !== 1)) {
                $errors[] = "{$where}: [{$group}] is not a valid group; only region candidates have group letters (A, B, …).";
            }

            if (preg_match('/^[1-9]\d*$/', $row['ballot_position']) !== 1) {
                $errors[] = "{$where}: ballot_position must be a whole number from 1.";

                continue;
            }

            if ($row['surname'] === '' || $row['given_names'] === '') {
                $errors[] = "{$where}: surname and given_names are both required.";
            }

            $partyId = null;

            if ($row['ballot_party'] !== '') {
                if (! array_key_exists($row['ballot_party'], $ballotNames)) {
                    $errors[] = "{$where}: [{$row['ballot_party']}] is not in party_ballot_names.csv. Add it, with the party it belongs to or blank for none.";
                } else {
                    $partyId = $ballotNames[$row['ballot_party']];
                }
            }

            $memberId = null;
            $name = "{$row['given_names']} {$row['surname']}";

            if ($row['member'] === '') {
                $found = $this->findMember($membersByName, $row['given_names'], $row['surname']);

                if (count($found) > 1) {
                    $errors[] = "{$where}: [{$name}] matches more than one MP (".implode(', ', array_map(fn (Member $member): string => $member->slug, $found)).'). Put the right slug, or "-", in the member column.';
                } elseif (count($found) === 1) {
                    $memberId = $found[0]->id;
                    $matched[] = ['electorate' => $electorate->name, 'candidate' => $name, 'member' => $found[0]->display_name];
                }
            } elseif ($row['member'] !== '-') {
                $member = $members->get($row['member']);

                if ($member === null) {
                    $errors[] = "{$where}: unknown member [{$row['member']}].";
                } else {
                    $memberId = $member->id;
                    $matched[] = ['electorate' => $electorate->name, 'candidate' => $name, 'member' => $member->display_name];
                }
            }

            $positions[$electorate->slug.($group === null ? '' : " group {$group}")][] = (int) $row['ballot_position'];
            $candidates[] = [
                'electorate_id' => $electorate->id,
                'ballot_group' => $group,
                'ballot_position' => (int) $row['ballot_position'],
                'surname' => $row['surname'],
                'given_names' => $row['given_names'],
                'ballot_party' => $row['ballot_party'] === '' ? null : $row['ballot_party'],
                'party_id' => $partyId,
                'member_id' => $memberId,
            ];
        }

        foreach ($positions as $ballot => $numbers) {
            sort($numbers);

            if ($numbers !== range(1, count($numbers))) {
                $errors[] = "{$ballot}: ballot positions must run 1 to ".count($numbers).' with no gaps or repeats.';
            }
        }

        if ($candidates === [] && $errors === []) {
            $errors[] = 'The candidate list is empty.';
        }

        if ($errors !== []) {
            throw new InvalidCandidateList($errors);
        }

        DB::transaction(function () use ($election, $candidates): void {
            $election->candidates()->delete();
            $now = now();

            foreach (array_chunk($candidates, 500) as $chunk) {
                Candidate::query()->insert(array_map(fn (array $candidate): array => [
                    ...$candidate,
                    'election_id' => $election->id,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk));
            }
        });

        return ['candidates' => count($candidates), 'matched' => $matched];
    }

    /**
     * @param  list<string>  $errors
     * @return array<string, ?int> each printed name => its party's id, or null for a party with no record
     */
    private function ballotNames(string $path, array &$errors): array
    {
        $parties = Party::query()->pluck('id', 'short_name');
        $names = [];

        foreach ($this->rows($path, ['ballot_name', 'party']) as $line => $row) {
            if ($row['party'] !== '' && ! $parties->has($row['party'])) {
                $errors[] = "party_ballot_names.csv line {$line}: unknown party [{$row['party']}].";

                continue;
            }

            $names[$row['ballot_name']] = $row['party'] === '' ? null : (int) $parties[$row['party']];
        }

        return $names;
    }

    /**
     * Each MP under every form of their name: display name, first and last
     * name, and any curated alias.
     *
     * @param  array<string, Member>  $members
     * @return array<string, array<int, Member>>
     */
    private function membersByName(array $members): array
    {
        $byName = [];
        $byId = [];

        foreach ($members as $member) {
            $byId[$member->id] = $member;

            foreach ([$member->display_name, "{$member->first_name} {$member->last_name}"] as $name) {
                $byName[$this->normalizer->normalize($name)][$member->id] = $member;
            }
        }

        foreach (MemberAlias::query()->get() as $alias) {
            if (isset($byId[$alias->member_id])) {
                $byName[$alias->normalized_name][$alias->member_id] = $byId[$alias->member_id];
            }
        }

        return $byName;
    }

    /**
     * MPs whose name matches the candidate's, trying all their given names
     * and then just the first, since ballots can include middle names.
     *
     * @param  array<string, array<int, Member>>  $membersByName
     * @return list<Member>
     */
    private function findMember(array $membersByName, string $givenNames, string $surname): array
    {
        $firstName = strtok($givenNames, ' ') ?: $givenNames;

        foreach (array_unique([$givenNames, $firstName]) as $given) {
            $found = $membersByName[$this->normalizer->normalize("{$given} {$surname}")] ?? [];

            if ($found !== []) {
                return array_values($found);
            }
        }

        return [];
    }

    /**
     * @param  list<string>  $headings
     * @return iterable<int, array<string, string>> rows keyed by heading, by line number
     */
    private function rows(string $path, array $headings): iterable
    {
        if (! is_file($path)) {
            throw new RuntimeException("[{$path}] is missing.");
        }

        $file = new SplFileObject($path);
        $file->setFlags(SplFileObject::READ_CSV | SplFileObject::SKIP_EMPTY | SplFileObject::READ_AHEAD);
        $header = null;

        foreach ($file as $index => $fields) {
            if (! is_array($fields) || $fields === [null]) {
                continue;
            }

            $fields = array_map(fn (?string $value): string => trim((string) $value), $fields);

            if ($header === null) {
                $missing = array_diff($headings, $fields);

                if ($missing !== []) {
                    throw new InvalidCandidateList(["[{$path}] has no ".implode(', ', $missing).' column.']);
                }

                $header = $fields;

                continue;
            }

            yield $index + 1 => array_combine($header, array_slice(array_pad($fields, count($header), ''), 0, count($header)));
        }
    }
}
