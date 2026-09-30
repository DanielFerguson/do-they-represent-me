<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

use function Illuminate\Support\defer;

/**
 * Passes the browser's anonymous analytics events on to PostHog, so the
 * browser only ever talks to this site. Only the capture paths pass, and
 * nothing that identifies the visitor goes with them: no cookies, no address.
 */
class IngestController extends Controller
{
    private const MAX_BODY_BYTES = 65536;

    public function __invoke(Request $request, string $path): JsonResponse
    {
        $key = config('services.posthog.key');

        abort_if(! is_string($key) || $key === '', 404);

        $body = $request->getContent();

        abort_if(strlen($body) > self::MAX_BODY_BYTES, 413);

        // PostHog's capture endpoints end in a slash, and its query string
        // (compression, version) tells it how to read the body.
        $url = rtrim((string) config('services.posthog.host'), '/').'/'.trim($path, '/').'/';
        $query = (string) $request->server('QUERY_STRING');

        if ($query !== '') {
            $url .= '?'.$query;
        }

        $headers = array_filter([
            'Content-Encoding' => $request->header('Content-Encoding'),
            'User-Agent' => $request->header('User-Agent'),
        ]);

        $contentType = $request->header('Content-Type') ?: 'text/plain';

        defer(function () use ($url, $body, $headers, $contentType): void {
            try {
                Http::withHeaders($headers)
                    ->withBody($body, $contentType)
                    ->connectTimeout(2)
                    ->timeout(3)
                    ->post($url);
            } catch (Throwable) {
                // Counting a visit is never worth an error. Drop the event.
            }
        });

        // PostHog's own reply. The browser library retries anything but a 200.
        return response()->json(['status' => 'Ok']);
    }
}
