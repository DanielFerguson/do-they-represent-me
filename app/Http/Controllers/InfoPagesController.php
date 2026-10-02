<?php

namespace App\Http\Controllers;

use App\Domain\Stances\StanceSnapshots;
use App\Enums\AgreementCategory;
use App\Models\Division;
use App\Models\Party;
use App\Models\Policy;
use App\Models\Vote;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

/**
 * The methodology, privacy and about pages.
 */
class InfoPagesController extends Controller
{
    /**
     * The parties the question set was balanced across: Labor, the Coalition
     * (Liberal and Nationals) and the Greens. The methodology's common
     * questions say how often each is on the "agree" side.
     */
    private const array BALANCED_PARTIES = ['ALP', 'GRN', 'LIB', 'NAT'];

    public function methodology(StanceSnapshots $snapshots): View
    {
        $latest = Division::query()->max('sitting_date');

        return view('methodology', [
            'divisions' => Division::query()->count(),
            'votes' => Vote::query()->count(),
            'publishedPolicies' => Policy::query()->published()->count(),
            'dataAsOf' => $latest === null ? null : Carbon::parse((string) $latest),
            'snapshotHash' => $snapshots->current()?->hash,
            'agreeSides' => Party::query()
                ->whereIn('short_name', self::BALANCED_PARTIES)
                ->withCount(['policyAgreements as agree_side_count' => fn (Builder $query) => $query
                    ->whereIn('category', [AgreementCategory::For3->value, AgreementCategory::For2->value, AgreementCategory::For1->value])
                    ->whereIn('policy_id', Policy::query()->published()->select('id'))])
                ->orderBy('display_name')
                ->get(),
        ]);
    }

    public function privacy(): View
    {
        return view('privacy');
    }

    public function about(): View
    {
        return view('about');
    }
}
