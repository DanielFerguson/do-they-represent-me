<?php

namespace App\Http\Controllers;

use App\Domain\Localities\LocalityDirectory;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * Serves the finder's list of localities. The URL carries the hash of the
 * content, so it can be cached anywhere for a year. A page loaded before
 * the localities last changed is sent on to the current version.
 */
class LocalityController extends Controller
{
    public function __invoke(LocalityDirectory $directory, string $hash): Response|RedirectResponse
    {
        if (! hash_equals($directory->hash(), $hash)) {
            return redirect($directory->url())->header('Cache-Control', 'no-cache');
        }

        return response($directory->body(), 200, [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
