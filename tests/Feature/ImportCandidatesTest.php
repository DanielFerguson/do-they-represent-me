<?php

use App\Enums\ElectorateKind;
use App\Models\Candidate;
use App\Models\Election;
use App\Models\Electorate;
use App\Models\Member;
use App\Models\Party;
use Illuminate\Support\Facades\File;

/**
 * A scratch data folder with a candidate list for the given election and
 * the ballot names every test shares.
 *
 * @param  list<string>  $candidates  CSV lines after the header
 */
function candidateData(string $election, array $candidates): string
{
    $folder = sys_get_temp_dir().'/candidates-'.uniqid();
    File::ensureDirectoryExists("{$folder}/candidates");
    File::put("{$folder}/party_ballot_names.csv", implode("\n", [
        'ballot_name,party,note',
        'Australian Labor Party - Victorian Branch,ALP,',
        'Victorian Socialists,,No members',
    ]));
    File::put("{$folder}/candidates/{$election}.csv", implode("\n", ['electorate,group,ballot_position,surname,given_names,ballot_party,member', ...$candidates]));

    return $folder;
}

beforeEach(function () {
    $this->election = Election::factory()->create(['slug' => '2030']);
    $this->labor = Party::factory()->create(['short_name' => 'ALP']);
    $this->district = Electorate::factory()->create(['slug' => 'albert-park']);
    $this->region = Electorate::factory()->region()->create(['slug' => 'southern-metropolitan']);
});

it('imports candidates in ballot order, linking parties with a record and sitting MPs', function () {
    $taylor = Member::factory()->create(['first_name' => 'Nina', 'last_name' => 'Taylor', 'display_name' => 'Nina Taylor']);
    $folder = candidateData('2030', [
        'albert-park,,1,DRAGWIDGE,Georgie,,',
        'albert-park,,2,TAYLOR,Nina,Australian Labor Party - Victorian Branch,',
        'southern-metropolitan,A,1,SMALL,Jerome,Victorian Socialists,',
        'southern-metropolitan,,1,MANCELL,Colin John,,',
    ]);

    $this->artisan('vic:import-candidates', ['election' => '2030', '--data' => $folder])
        ->expectsOutputToContain('Imported 4 candidates')
        ->assertSuccessful();

    $candidates = Candidate::query()->orderBy('id')->get();
    expect($candidates->map(fn (Candidate $candidate): array => [$candidate->ballotName(), $candidate->ballot_group, $candidate->ballot_position, $candidate->party_id, $candidate->member_id])->all())->toBe([
        ['DRAGWIDGE, Georgie', null, 1, null, null],
        ['TAYLOR, Nina', null, 2, $this->labor->id, $taylor->id],
        ['SMALL, Jerome', 'A', 1, null, null],
        ['MANCELL, Colin John', null, 1, null, null],
    ]);
});

it('matches an MP whose ballot name includes a middle name, and lets the list rule out a namesake', function () {
    $werner = Member::factory()->create(['first_name' => 'Nicole', 'last_name' => 'Werner', 'display_name' => 'Nicole Werner']);
    Member::factory()->create(['first_name' => 'Jo', 'last_name' => 'Namesake', 'display_name' => 'Jo Namesake']);
    $folder = candidateData('2030', [
        'albert-park,,1,WERNER,Nicole Ta-Ei,,',
        'albert-park,,2,NAMESAKE,Jo,,-',
    ]);

    $this->artisan('vic:import-candidates', ['election' => '2030', '--data' => $folder])->assertSuccessful();

    expect(Candidate::query()->orderBy('ballot_position')->pluck('member_id')->all())->toBe([$werner->id, null]);
});

it('checks the whole list and imports nothing while any problem remains', function () {
    Candidate::factory()->for($this->election)->for($this->district)->create(['surname' => 'Existing']);
    Member::factory()->count(2)->create(['first_name' => 'Tim', 'last_name' => 'Bull', 'display_name' => 'Tim Bull']);
    $folder = candidateData('2030', [
        'nowhere,,1,ONE,Ann,,',
        'albert-park,A,1,TWO,Bo,,',
        'albert-park,,3,BULL,Tim,,',
        'albert-park,,4,FOUR,Di,Labor,',
        'albert-park,,5,FIVE,Ed,,no-such-mp',
    ]);

    $this->artisan('vic:import-candidates', ['election' => '2030', '--data' => $folder])
        ->expectsOutputToContain('Line 2: unknown electorate [nowhere].')
        ->expectsOutputToContain('Line 3: [A] is not a valid group; only region candidates have group letters')
        ->expectsOutputToContain('Line 4: [Tim BULL] matches more than one MP')
        ->expectsOutputToContain('Line 5: [Labor] is not in party_ballot_names.csv.')
        ->expectsOutputToContain('Line 6: unknown member [no-such-mp].')
        ->expectsOutputToContain('albert-park: ballot positions must run 1 to 3 with no gaps or repeats.')
        ->assertFailed();

    expect(Candidate::query()->pluck('surname')->all())->toBe(['Existing']);
});

it('replaces an election’s candidates when the list is imported again', function () {
    $folder = candidateData('2030', ['albert-park,,1,ONLY,Una,,']);
    $other = Election::factory()->create();
    Candidate::factory()->for($other)->for($this->district)->create(['surname' => 'Other election']);

    $this->artisan('vic:import-candidates', ['election' => '2030', '--data' => $folder])->assertSuccessful();
    $this->artisan('vic:import-candidates', ['election' => '2030', '--data' => $folder])->assertSuccessful();

    expect(Candidate::query()->where('election_id', $this->election->id)->count())->toBe(1)
        ->and(Candidate::query()->where('election_id', $other->id)->count())->toBe(1);
});

it('imports the real 2022 candidate list, with every ballot name mapped and the sitting MPs matched', function () {
    $this->artisan('vic:import-data')->assertSuccessful();

    $this->artisan('vic:import-candidates', ['election' => '2022'])->assertSuccessful();

    $candidates = Candidate::query()->whereRelation('election', 'slug', '2022')->with('electorate')->get();
    $taylor = $candidates->first(fn (Candidate $candidate): bool => $candidate->surname === 'TAYLOR' && $candidate->given_names === 'Nina');

    expect($candidates->pluck('electorate_id')->unique())->toHaveCount(96)
        ->and($candidates->filter(fn (Candidate $candidate): bool => $candidate->electorate->kind === ElectorateKind::Region)->whereNotNull('ballot_group')->pluck('electorate_id')->unique())->toHaveCount(8)
        ->and($taylor->member?->slug)->toBe('nina-taylor')
        ->and($taylor->party?->short_name)->toBe('ALP')
        ->and($candidates->whereNotNull('member_id')->count())->toBeGreaterThan(120);
});

it('says which election is missing', function () {
    $this->artisan('vic:import-candidates', ['election' => '1999'])
        ->expectsOutputToContain('No election [1999]')
        ->assertFailed();
});
