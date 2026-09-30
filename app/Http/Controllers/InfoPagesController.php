<?php

namespace App\Http\Controllers;

use App\Domain\Stances\StanceSnapshots;
use App\Models\Division;
use App\Models\Policy;
use App\Models\Vote;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Carbon;

/**
 * The methodology, privacy and about pages.
 */
class InfoPagesController extends Controller
{
    public function methodology(StanceSnapshots $snapshots): View
    {
        $latest = Division::query()->max('sitting_date');

        return view('methodology', [
            'divisions' => Division::query()->count(),
            'votes' => Vote::query()->count(),
            'publishedPolicies' => Policy::query()->published()->count(),
            'dataAsOf' => $latest === null ? null : Carbon::parse((string) $latest),
            'snapshotHash' => $snapshots->current()?->hash,
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
