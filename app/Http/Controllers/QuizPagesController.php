<?php

namespace App\Http\Controllers;

use App\Domain\Localities\LocalityDirectory;
use App\Domain\Stances\StanceSnapshots;
use Illuminate\Contracts\View\View;

/**
 * The public home, quiz and results pages. They use the live published
 * quiz data, or the labelled prototype data until a policy is published.
 */
class QuizPagesController extends Controller
{
    public function __construct(private StanceSnapshots $snapshots) {}

    public function home(LocalityDirectory $localities): View
    {
        return view('home', [...$this->pageData(), 'localitiesUrl' => $localities->url()]);
    }

    public function quiz(): View
    {
        return view('quiz', $this->pageData());
    }

    public function results(): View
    {
        return view('results', $this->pageData());
    }

    /**
     * @return array{stancesUrl: string, quizUrl: string, resultsUrl: string, districtUrl: string, storageKey: string, isSample: bool, isPreview: bool}
     */
    private function pageData(): array
    {
        $snapshot = $this->snapshots->current();

        return [
            'stancesUrl' => $snapshot === null ? asset('stances/sample.json') : route('stances.show', $snapshot->hash),
            'quizUrl' => route('quiz'),
            'resultsUrl' => route('results'),
            'districtUrl' => route('districts.show', '__district__', absolute: false),
            'storageKey' => 'dtrm-answers',
            'isSample' => $snapshot === null,
            'isPreview' => false,
        ];
    }
}
