<?php

namespace App\Http\Controllers;

use App\Domain\Policies\PolicyEvidence;
use App\Domain\Stances\StanceSnapshots;
use App\Enums\PolicyStatus;
use App\Models\Policy;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\URL;

/**
 * The quiz and results with policies still in review, for reviewers. Only
 * reachable through an expiring signed link created in the admin panel, and
 * never cached, indexed or leaked through the Referer header.
 */
class PreviewController extends Controller
{
    /**
     * @var array<string, string>
     */
    private const PRIVATE_HEADERS = [
        'Cache-Control' => 'no-store, private',
        'X-Robots-Tag' => 'noindex, nofollow',
        'Referrer-Policy' => 'no-referrer',
    ];

    public function __construct(private StanceSnapshots $snapshots, private PolicyEvidence $evidence) {}

    /**
     * A signed link to the preview quiz, valid until the given time.
     */
    public static function linkUntil(Carbon $expires): string
    {
        return url(URL::temporarySignedRoute('preview.quiz', $expires, absolute: false));
    }

    public function quiz(Request $request): Response
    {
        return response()->view('quiz', $this->pageData($request))->withHeaders(self::PRIVATE_HEADERS);
    }

    public function results(Request $request): Response
    {
        return response()->view('results', $this->pageData($request))->withHeaders(self::PRIVATE_HEADERS);
    }

    public function stances(Request $request): Response
    {
        $expires = self::expires($request);
        $body = $this->snapshots->previewBody(
            fn (Policy $policy): string => URL::temporarySignedRoute('preview.policies.show', $expires, ['policy' => $policy->slug], absolute: false),
        );

        return response($body, 200, ['Content-Type' => 'application/json', ...self::PRIVATE_HEADERS]);
    }

    /**
     * A policy's evidence page, including policies still in review.
     */
    public function policy(Request $request, string $policy): Response
    {
        $record = Policy::query()
            ->where('slug', $policy)
            ->whereIn('status', [PolicyStatus::Published, PolicyStatus::Review])
            ->firstOrFail();

        return response()->view('policies.show', [...$this->evidence->forPolicy($record), 'isPreview' => true])->withHeaders(self::PRIVATE_HEADERS);
    }

    /**
     * @return array{stancesUrl: string, quizUrl: string, resultsUrl: string, districtUrl: string, storageKey: string, isSample: bool, isPreview: bool}
     */
    private function pageData(Request $request): array
    {
        $expires = self::expires($request);
        $sign = fn (string $route): string => URL::temporarySignedRoute($route, $expires, absolute: false);

        return [
            'stancesUrl' => $sign('preview.stances'),
            'quizUrl' => $sign('preview.quiz'),
            'resultsUrl' => $sign('preview.results'),
            'districtUrl' => route('districts.show', '__district__', absolute: false),
            'storageKey' => 'dtrm-preview-answers',
            'isSample' => false,
            'isPreview' => true,
        ];
    }

    /**
     * When the link being used expires, so links made from it expire too.
     */
    private static function expires(Request $request): Carbon
    {
        return Carbon::createFromTimestamp($request->integer('expires'));
    }
}
