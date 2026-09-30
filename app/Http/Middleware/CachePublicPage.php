<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets browsers and the edge network cache a public page that rendered
 * successfully: browsers for a minute, the edge for five, serving a stale
 * copy while it refreshes. Public pages set no cookies and are the same
 * for everyone, so this is safe. Errors are never cached.
 */
class CachePublicPage
{
    public const BROWSER_SECONDS = 60;

    public const EDGE_SECONDS = 300;

    public const STALE_SECONDS = 86400;

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        if (! $request->isMethodCacheable() || $response->getStatusCode() !== 200) {
            return $response;
        }

        $response->setCache([
            'public' => true,
            'max_age' => self::BROWSER_SECONDS,
            's_maxage' => self::EDGE_SECONDS,
            'stale_while_revalidate' => self::STALE_SECONDS,
        ]);

        // HEAD responses have no body at this point, so only GET gets an ETag.
        if ($request->isMethod('GET')) {
            $response->setEtag(md5((string) $response->getContent()));
            $response->isNotModified($request);
        }

        return $response;
    }
}
