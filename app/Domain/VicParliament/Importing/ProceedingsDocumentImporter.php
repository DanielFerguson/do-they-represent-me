<?php

namespace App\Domain\VicParliament\Importing;

use App\Domain\VicParliament\Documents\DocxReader;
use App\Domain\VicParliament\Documents\PdfReader;
use App\Domain\VicParliament\Members\MemberResolver;
use App\Domain\VicParliament\Members\ResolvedVoter;
use App\Domain\VicParliament\ParliamentClient;
use App\Domain\VicParliament\Parsing\DivisionStageClassifier;
use App\Domain\VicParliament\Parsing\ParsedDivision;
use App\Domain\VicParliament\Parsing\ProceedingsParser;
use App\Enums\VoteValue;
use App\Models\Division;
use App\Models\House;
use App\Models\Parliament;
use App\Models\ProceedingsDocument;
use App\Models\UnresolvedName;
use App\Models\Vote;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Imports the divisions recorded in one Votes and Proceedings or Minutes
 * document: download, keep the raw file, parse, resolve voters to members
 * and persist. Re-importing an unchanged document is a no-op, and
 * re-importing a changed one replaces its divisions' votes in place.
 */
class ProceedingsDocumentImporter
{
    /**
     * @var array<string, ?Parliament>
     */
    private array $parliaments = [];

    public function __construct(
        private ParliamentClient $client,
        private DocxReader $docxReader,
        private PdfReader $pdfReader,
        private ProceedingsParser $parser,
        private DivisionStageClassifier $classifier,
        private MemberResolver $resolver,
        private DivisionDateCorrections $dateCorrections,
    ) {}

    public function import(ProceedingsDocument $document, bool $force = false): ImportResult
    {
        [$url, $format] = $document->docx_url !== '' ? [$document->docx_url, 'docx'] : [(string) $document->pdf_url, 'pdf'];

        if ($url === '') {
            $document->update(['fetched_at' => now(), 'parse_error' => 'No .docx or PDF version of this document is published.']);

            return new ImportResult(ImportResult::FAILED, error: 'No .docx or PDF version of this document is published.');
        }

        try {
            $bytes = $this->client->download($url);
            $sha256 = hash('sha256', $bytes);

            if (! $force && $document->parsed_at !== null && $document->sha256 === $sha256) {
                $document->update(['fetched_at' => now()]);

                return new ImportResult(ImportResult::UNCHANGED, divisions: $document->divisions_count);
            }

            $rawPath = "proceedings/{$document->house->slug}/{$document->source_key}.{$format}";
            Storage::put($rawPath, $bytes);

            return $this->process($document, $bytes, $format, $rawPath, $sha256);
        } catch (Throwable $exception) {
            $document->update(['fetched_at' => now(), 'parse_error' => $exception->getMessage()]);

            return new ImportResult(ImportResult::FAILED, error: $exception->getMessage());
        }
    }

    /**
     * Re-import from the raw file kept in storage, without contacting the
     * Parliament's website. Used after parser, alias or membership changes,
     * and to rebuild the database from storage.
     */
    public function reimportFromStorage(ProceedingsDocument $document): ImportResult
    {
        if ($document->raw_path === null || ! Storage::exists($document->raw_path)) {
            return new ImportResult(ImportResult::FAILED, error: 'No stored copy of this document.');
        }

        try {
            $bytes = (string) Storage::get($document->raw_path);

            return $this->process($document, $bytes, pathinfo($document->raw_path, PATHINFO_EXTENSION), $document->raw_path, hash('sha256', $bytes));
        } catch (Throwable $exception) {
            $document->update(['parse_error' => $exception->getMessage()]);

            return new ImportResult(ImportResult::FAILED, error: $exception->getMessage());
        }
    }

    private function process(ProceedingsDocument $document, string $bytes, string $format, string $rawPath, string $sha256): ImportResult
    {
        $this->resolver->flush();

        $parsed = $this->parse($bytes, $format);
        $result = DB::transaction(fn (): ImportResult => $this->persist($document, $parsed));

        $document->update([
            'raw_path' => $rawPath,
            'sha256' => $sha256,
            'fetched_at' => now(),
            'parsed_at' => now(),
            'parse_error' => null,
            'divisions_count' => $result->divisions,
        ]);

        return $result;
    }

    /**
     * @return list<ParsedDivision>
     */
    private function parse(string $bytes, string $format): array
    {
        $path = tempnam(sys_get_temp_dir(), 'proceedings-');
        file_put_contents($path, $bytes);

        try {
            $paragraphs = $format === 'pdf' ? $this->pdfReader->paragraphs($path) : $this->docxReader->paragraphs($path);

            return $this->parser->parse($paragraphs);
        } finally {
            unlink($path);
        }
    }

    /**
     * @param  list<ParsedDivision>  $parsed
     */
    private function persist(ProceedingsDocument $document, array $parsed): ImportResult
    {
        $house = $document->house;
        $divisions = 0;
        $needingReview = 0;
        $unresolved = 0;

        foreach ($parsed as $division) {
            $parliament = $this->parliamentOn($division);

            if ($parliament === null) {
                continue;
            }

            $correctedDate = $this->dateCorrections->dateFor(
                Division::formatReference($house->short_name, $parliament->number, $division->sittingNumber, $division->sequence),
            );

            if ($correctedDate !== null) {
                $division = $division->withSittingDate($correctedDate);
            }

            $ayes = $this->resolver->resolveMany($division->ayes, $house, $division->sittingDate);
            $noes = $this->resolver->resolveMany($division->noes, $house, $division->sittingDate);
            $voters = $this->votesByMember($ayes['resolved'], $noes['resolved'], $division->tellers);
            $unresolvedNames = [
                ...array_map(fn (string $name): array => [$name, VoteValue::Aye], $ayes['unresolved']),
                ...array_map(fn (string $name): array => [$name, VoteValue::No], $noes['unresolved']),
            ];

            $recordedAyes = count(array_filter($voters, fn (array $vote): bool => $vote['vote'] === VoteValue::Aye->value));

            $needsReview = $unresolvedNames !== []
                || $recordedAyes !== $division->ayesCount
                || count($voters) - $recordedAyes !== $division->noesCount;

            $record = Division::query()->updateOrCreate([
                'parliament_id' => $parliament->id,
                'house_id' => $house->id,
                'sitting_number' => $division->sittingNumber,
                'sequence' => $division->sequence,
            ], [
                'proceedings_document_id' => $document->id,
                'sitting_date' => $division->sittingDate,
                'body' => $division->body,
                'item_number' => $division->itemNumber,
                'item_title' => $division->itemTitle,
                'question' => $division->question,
                'stage' => $this->classifier->classify($division),
                'presiding_role' => $division->presidingRole,
                'presiding_member_id' => $this->presidingMember($division, $house),
                'result' => $division->result,
                'ayes_count' => $division->ayesCount,
                'noes_count' => $division->noesCount,
                'needs_review' => $needsReview,
            ]);

            $record->votes()->delete();
            $record->unresolvedNames()->delete();

            Vote::query()->insert(array_map(fn (array $vote): array => [...$vote, 'division_id' => $record->id], array_values($voters)));

            foreach ($unresolvedNames as [$name, $side]) {
                UnresolvedName::query()->create(['division_id' => $record->id, 'raw_name' => $name, 'side' => $side]);
            }

            $divisions++;
            $needingReview += (int) $needsReview;
            $unresolved += count($unresolvedNames);
        }

        return new ImportResult(ImportResult::IMPORTED, $divisions, $needingReview, $unresolved);
    }

    /**
     * Key votes by member. A member appearing twice (a printing error, or two
     * printed names resolving to one person) keeps their first vote, and the
     * division is flagged for review by the caller.
     *
     * @param  list<ResolvedVoter>  $ayes
     * @param  list<ResolvedVoter>  $noes
     * @param  list<string>  $tellers
     * @return array<int, array{member_id: int, party_id: int, vote: string, is_teller: bool}>
     */
    private function votesByMember(array $ayes, array $noes, array $tellers): array
    {
        $votes = [];

        foreach ([[VoteValue::Aye, $ayes], [VoteValue::No, $noes]] as [$side, $voters]) {
            foreach ($voters as $voter) {
                $votes[$voter->memberId] ??= [
                    'member_id' => $voter->memberId,
                    'party_id' => $voter->partyId,
                    'vote' => $side->value,
                    'is_teller' => in_array($voter->printedName, $tellers, true),
                ];
            }
        }

        return $votes;
    }

    private function presidingMember(ParsedDivision $division, House $house): ?int
    {
        if ($division->presidingOfficer === null) {
            return null;
        }

        return $this->resolver->resolve($division->presidingOfficer, $house, $division->sittingDate)?->memberId;
    }

    private function parliamentOn(ParsedDivision $division): ?Parliament
    {
        $day = $division->sittingDate->toDateString();

        if (! array_key_exists($day, $this->parliaments)) {
            $this->parliaments[$day] = Parliament::onDate($division->sittingDate);
        }

        return $this->parliaments[$day];
    }
}
