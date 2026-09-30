<?php

namespace App\Http\Controllers;

use App\Domain\Policies\PolicyEvidence;
use App\Models\Policy;
use Illuminate\Contracts\View\View;

/**
 * The published quiz questions and the evidence behind each one: every
 * linked division, how each party voted and the sources. Policies still in
 * review are only shown through reviewers' preview links.
 */
class PolicyController extends Controller
{
    public function __construct(private PolicyEvidence $evidence) {}

    public function index(): View
    {
        $topics = Policy::query()
            ->published()
            ->orderBy('number')
            ->get()
            ->groupBy(fn (Policy $policy): string => $policy->topic ?? 'Other')
            ->sortKeys();

        return view('policies.index', ['topics' => $topics]);
    }

    public function show(string $policy): View
    {
        $record = Policy::query()->published()->where('slug', $policy)->firstOrFail();

        return view('policies.show', [...$this->evidence->forPolicy($record), 'isPreview' => false]);
    }
}
