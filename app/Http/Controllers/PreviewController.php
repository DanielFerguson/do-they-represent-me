<?php

namespace App\Http\Controllers;

use App\Domain\Stances\StanceSnapshots;
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

    public function __construct(private StanceSnapshots $snapshots) {}

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

    public function stances(): Response
    {
        return response($this->snapshots->previewBody(), 200, ['Content-Type' => 'application/json', ...self::PRIVATE_HEADERS]);
    }

    /**
     * @return array{stancesUrl: string, quizUrl: string, resultsUrl: string, storageKey: string, isSample: bool, isPreview: bool}
     */
    private function pageData(Request $request): array
    {
        $expires = Carbon::createFromTimestamp($request->integer('expires'));
        $sign = fn (string $route): string => URL::temporarySignedRoute($route, $expires, absolute: false);

        return [
            'stancesUrl' => $sign('preview.stances'),
            'quizUrl' => $sign('preview.quiz'),
            'resultsUrl' => $sign('preview.results'),
            'storageKey' => 'dtrm-preview-answers',
            'isSample' => false,
            'isPreview' => true,
        ];
    }
}
