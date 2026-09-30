<?php

namespace App\Console\Commands;

use App\Domain\Stances\StanceSnapshots;
use App\Enums\ElectorateKind;
use App\Models\Division;
use App\Models\Election;
use App\Models\Electorate;
use App\Models\Policy;
use App\Models\PolicyImport;
use App\Models\ProceedingsDocument;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('vic:launch-check {--launch : Also check the final conditions: candidates in every electorate, and frozen data}')]
#[Description('Go/no-go checks for the soft launch and the public launch')]
class LaunchCheck extends Command
{
    public const MINIMUM_PUBLISHED = 15;

    /**
     * Execute the console command.
     */
    public function handle(StanceSnapshots $snapshots): int
    {
        $checks = [
            'At least '.self::MINIMUM_PUBLISHED.' published questions' => $this->publishedShortfall(),
            'The data audit passes' => $this->callSilently('vic:audit') === self::SUCCESS ? [] : ['vic:audit failed; run it for details'],
            'The live quiz data is up to date' => $snapshots->isStale() ? ['the published data has changed since it was stored; run vic:score'] : [],
            'Site settings' => $this->settingsProblems(),
            'Every district has suburbs' => Electorate::query()->where('kind', ElectorateKind::District)->doesntHave('localities')->orderBy('name')->pluck('name')->all(),
        ];

        if ($this->option('launch')) {
            $checks['Candidates for the next election in every district and region'] = $this->electoratesWithoutCandidates();
            $checks['The scheduled sync is frozen'] = config('services.parliament_vic.sync_frozen') ? [] : ['set PARLIAMENT_VIC_SYNC_FROZEN=true after the final sync'];
        }

        // Worth knowing, but not a reason to hold a launch.
        $notices = [
            'Visit counts are switched on' => filled(config('services.posthog.key')) ? [] : ['POSTHOG_KEY is not set, so no visits are counted'],
        ];

        $failed = 0;

        foreach ($checks as $check => $problems) {
            if ($problems === []) {
                $this->line("  ✓ {$check}");

                continue;
            }

            $failed++;
            $this->warn("  ✗ {$check}: ".count($problems));
            collect($problems)->take(10)->each(fn (string $problem) => $this->line("      {$problem}"));
        }

        foreach ($notices as $notice => $problems) {
            $this->line($problems === [] ? "  ✓ {$notice}" : "  ! {$notice}: ".implode(', ', $problems));
        }

        $this->newLine();
        $this->recordFacts($snapshots);

        return $failed === 0 ? self::SUCCESS : self::FAILURE;
    }

    /**
     * @return list<string>
     */
    private function publishedShortfall(): array
    {
        $published = Policy::query()->published()->count();

        return $published >= self::MINIMUM_PUBLISHED ? [] : ["{$published} published"];
    }

    /**
     * @return list<string>
     */
    private function settingsProblems(): array
    {
        return array_values(array_filter([
            filled(config('site.contact_email')) ? null : 'SITE_CONTACT_EMAIL is not set',
            filled(config('site.authorisation')) ? null : 'SITE_AUTHORISATION is not set',
            str_starts_with((string) config('app.url'), 'https://') ? null : 'APP_URL is not https',
            app()->isProduction() && config('app.debug') ? 'APP_DEBUG is on in production' : null,
        ]));
    }

    /**
     * @return list<string>
     */
    private function electoratesWithoutCandidates(): array
    {
        $election = Election::upcoming();

        if ($election === null) {
            return ['no upcoming election'];
        }

        return Electorate::query()
            ->whereIn('kind', [ElectorateKind::District, ElectorateKind::Region])
            ->whereDoesntHave('candidates', fn ($query) => $query->where('election_id', $election->id))
            ->orderBy('name')
            ->pluck('name')
            ->all();
    }

    /**
     * The facts to write down at each go/no-go point.
     */
    private function recordFacts(StanceSnapshots $snapshots): void
    {
        $election = Election::upcoming();

        $this->table(['Fact', 'Value'], [
            ['Published questions', Policy::query()->published()->count()],
            ['Quiz data version', $snapshots->current()->hash ?? '—'],
            ['Workbook (latest import)', PolicyImport::query()->latest('id')->value('sha256') ?? '—'],
            ['Divisions', Division::query()->count()],
            ['Votes', DB::table('votes')->count()],
            ['Documents', ProceedingsDocument::query()->count()],
            ['Latest sitting', Division::query()->max('sitting_date') ?? '—'],
            ['Candidates for the next election', $election?->candidates()->count() ?? '—'],
        ]);
    }
}
