<?php

namespace App\Http\Controllers;

use App\Domain\Districts\DistrictProfile;
use App\Domain\Localities\LocalityDirectory;
use App\Enums\ElectorateKind;
use App\Models\Electorate;
use Illuminate\Contracts\View\View;

/**
 * The 88 Assembly districts, each with its MLA, its region's MLCs and how
 * they voted on the published questions.
 */
class DistrictController extends Controller
{
    public function __construct(private DistrictProfile $profile) {}

    public function index(LocalityDirectory $localities): View
    {
        $regions = Electorate::query()
            ->where('kind', ElectorateKind::Region)
            ->with(['districts' => fn ($query) => $query->orderBy('name')])
            ->orderBy('name')
            ->get();

        return view('districts.index', ['regions' => $regions, 'localitiesUrl' => $localities->url()]);
    }

    public function show(string $district): View
    {
        $record = Electorate::query()
            ->where('kind', ElectorateKind::District)
            ->where('slug', $district)
            ->with('region')
            ->firstOrFail();

        return view('districts.show', $this->profile->for($record));
    }
}
