<?php

use App\Domain\VicParliament\ParliamentClient;
use App\Enums\DivisionStage;
use App\Enums\PartyPosition;
use App\Models\Division;
use App\Models\DivisionPartyPosition;
use App\Models\ProceedingsDocument;
use App\Models\UnresolvedName;
use App\Models\Vote;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;

const ASSEMBLY_DOCX = '/4a25e5/globalassets/house-paper-documents/house-paper-6942/2025vp131134.docx';
const COUNCIL_DOCX = '/4a25d4/globalassets/house-paper-documents/house-paper-6925/m128m129m130.docx';
const ASSEMBLY_PDF_ONLY = '/4a263f/globalassets/house-paper-documents/house-paper-7126/2025vp159.pdf';

/**
 * @return array<string, mixed>
 */
function listingHit(string $title, string $meta, string $docx): array
{
    return ['id' => 'tile-'.md5($docx), 'category' => 'Legislative', 'title' => $title, 'meta' => $meta, 'buttons' => [
        ['suffix' => 'pdf', 'href' => str_replace('.docx', '.pdf', $docx)],
        ['suffix' => 'doc', 'href' => $docx],
    ]];
}

function fakeParliament(array $fixtureOverrides = []): void
{
    $fixtures = [
        ASSEMBLY_DOCX => base_path('tests/Fixtures/VotesAndProceedings/assembly-2025-vp131-134.docx'),
        COUNCIL_DOCX => base_path('tests/Fixtures/VotesAndProceedings/council-2025-m128-130.docx'),
        ASSEMBLY_PDF_ONLY => base_path('tests/Fixtures/VotesAndProceedings/assembly-2025-vp159-pdf-only.pdf'),
        ...$fixtureOverrides,
    ];

    Http::fake(function (Request $request) use ($fixtures) {
        $path = parse_url($request->url(), PHP_URL_PATH);

        if ($path === '/api/search/house-papers') {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            $hits = $query['house'] === '4'
                ? [listingHit('Votes and Proceedings Nos 131 to 134', '29 July 2025 - 31 July 2025', ASSEMBLY_DOCX)]
                : [listingHit('Minutes of the Proceedings Nos. 128, 129 and 130', '26 August 2025 - 28 August 2025', COUNCIL_DOCX),
                    listingHit('Minutes of the Proceedings No. 1', '20 December 2018 - 20 December 2018', '/old/house-paper-1/m1.docx')];

            return Http::response(['result' => ['hits' => $hits, 'totalMatching' => count($hits)]]);
        }

        return isset($fixtures[$path])
            ? Http::response(file_get_contents($fixtures[$path]))
            : Http::response('Not found', 404);
    });
}

beforeEach(function () {
    Storage::fake();
    $this->artisan('vic:import-data');
});

it('imports every division with every vote resolved to a member', function () {
    fakeParliament();

    $this->artisan('vic:sync-proceedings')->assertSuccessful();

    expect(ProceedingsDocument::query()->count())->toBe(2)
        ->and(Division::query()->count())->toBe(8 + 25)
        ->and(Vote::query()->count())->toBe((int) Division::query()->sum('ayes_count') + (int) Division::query()->sum('noes_count'))
        ->and(UnresolvedName::query()->count())->toBe(0)
        ->and(Division::query()->where('needs_review', true)->count())->toBe(0);

    $first = Division::query()->where('sitting_date', '2025-07-29')->orderBy('sequence')->firstOrFail();

    expect($first->ayes_count)->toBe(32)
        ->and($first->votes()->where('vote', 'aye')->count())->toBe(32)
        ->and($first->presidingMember?->slug)->toBe('matt-fregon')
        ->and($first->item_title)->toStartWith('WORKER SCREENING AMENDMENT');
});

it('records each vote against the member\'s party on the day', function () {
    fakeParliament();

    $this->artisan('vic:sync-proceedings');

    $vote = Vote::query()->whereHas('member', fn ($query) => $query->where('slug', 'jacinta-allan'))->with('party')->firstOrFail();

    expect($vote->party->short_name)->toBe('ALP');
});

it('recalculates each party\'s position after importing', function () {
    fakeParliament();

    $this->artisan('vic:sync-proceedings --house=assembly')->assertSuccessful();

    $first = Division::query()->where('sitting_date', '2025-07-29')->orderBy('sequence')->firstOrFail();

    expect($first->partyPositions()->with('party')->get()->mapWithKeys(fn (DivisionPartyPosition $position) => [
        $position->party->short_name => [$position->ayes, $position->noes, $position->eligible, $position->position],
    ])->sortKeys()->all())->toBe([
        'ALP' => [0, 49, 53, PartyPosition::No],
        'GRN' => [3, 0, 3, PartyPosition::Aye],
        'LIB' => [20, 0, 20, PartyPosition::Aye],
        'NAT' => [9, 0, 9, PartyPosition::Aye],
    ]);
});

it('classifies stages and keeps the raw documents', function () {
    fakeParliament();

    $this->artisan('vic:sync-proceedings');

    expect(Division::query()->where('stage', DivisionStage::ReasonedAmendment)->exists())->toBeTrue()
        ->and(Division::query()->where('stage', DivisionStage::ThirdReading)->exists())->toBeTrue();

    Storage::assertExists('proceedings/assembly/house-paper-6942.docx');
    Storage::assertExists('proceedings/council/house-paper-6925.docx');
});

it('skips unchanged documents and never duplicates divisions or votes', function () {
    fakeParliament();

    $this->artisan('vic:sync-proceedings');
    $divisionIds = Division::query()->pluck('id')->sort()->values()->all();
    $votes = Vote::query()->count();

    $this->artisan('vic:sync-proceedings')->expectsOutputToContain('unchanged')->assertSuccessful();
    $this->artisan('vic:sync-proceedings --force')->assertSuccessful();

    expect(Division::query()->pluck('id')->sort()->values()->all())->toBe($divisionIds)
        ->and(Vote::query()->count())->toBe($votes);
});

it('ignores documents from before the start of the parliament', function () {
    fakeParliament();

    $this->artisan('vic:sync-proceedings');

    expect(ProceedingsDocument::query()->where('source_key', 'house-paper-1')->exists())->toBeFalse();
    Http::assertNotSent(fn (Request $request) => str_contains($request->url(), '/old/'));
});

it('limits the sync to one house', function () {
    fakeParliament();

    $this->artisan('vic:sync-proceedings --house=assembly')->assertSuccessful();

    expect(Division::query()->count())->toBe(8);
});

it('marks a document as failed when it is not a valid .docx', function () {
    $broken = tempnam(sys_get_temp_dir(), 'broken');
    file_put_contents($broken, 'not a zip file');
    fakeParliament([COUNCIL_DOCX => $broken]);

    $this->artisan('vic:sync-proceedings --house=council')->assertFailed();

    expect(ProceedingsDocument::query()->where('source_key', 'house-paper-6925')->value('parse_error'))->not->toBeNull()
        ->and(Division::query()->count())->toBe(0);
});

it('refuses to fetch from hosts other than the Parliament', function () {
    expect(fn () => app(ParliamentClient::class)->url('https://example.com/evil.docx'))
        ->toThrow(RuntimeException::class, 'not allowlisted')
        ->and(fn () => app(ParliamentClient::class)->url('http://www.parliament.vic.gov.au/insecure'))
        ->toThrow(RuntimeException::class, 'not allowlisted');
});

it('passes the audit after a clean import', function () {
    fakeParliament();

    $this->artisan('vic:sync-proceedings');

    $this->artisan('vic:audit')->assertSuccessful();
});

it('fails the audit when recorded votes stop matching the printed totals', function () {
    fakeParliament();

    $this->artisan('vic:sync-proceedings --house=assembly');
    Division::query()->firstOrFail()->votes()->limit(1)->delete();

    $this->artisan('vic:audit')
        ->expectsOutputToContain('Recorded votes differ from printed totals: 1')
        ->assertFailed();
});

it('falls back to the PDF when a document has no .docx version', function () {
    Http::fake(function (Request $request) {
        $path = parse_url($request->url(), PHP_URL_PATH);

        if ($path === '/api/search/house-papers') {
            return Http::response(['result' => ['hits' => [[
                'id' => 'tile-159', 'category' => 'Legislative Assembly', 'title' => 'Votes and Proceedings No 159', 'meta' => '9 December 2025',
                'buttons' => [['suffix' => 'pdf', 'href' => ASSEMBLY_PDF_ONLY]],
            ]]]]);
        }

        return Http::response(file_get_contents(base_path('tests/Fixtures/VotesAndProceedings/assembly-2025-vp159-pdf-only.pdf')));
    });

    $this->artisan('vic:sync-proceedings --house=assembly')->assertSuccessful();

    $division = Division::query()->sole();

    expect($division->item_title)->toBe('APOLOGY TO THE FIRST PEOPLES OF VICTORIA')
        ->and($division->votes()->count())->toBe(56 + 27)
        ->and($division->needs_review)->toBeFalse();

    Storage::assertExists('proceedings/assembly/house-paper-7126.pdf');
});

it('re-imports from stored copies without downloading again', function () {
    fakeParliament();

    $this->artisan('vic:sync-proceedings --house=council');
    $votes = Vote::query()->count();

    Http::fake(['*' => Http::response('unavailable', 503)]);

    $this->artisan('vic:reparse')->assertSuccessful();

    expect(Vote::query()->count())->toBe($votes)
        ->and(Division::query()->count())->toBe(25);
    Http::assertNothingSent();
});
