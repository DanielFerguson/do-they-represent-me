<?php

namespace App\Http\Controllers;

use App\Models\StanceSnapshot;
use Illuminate\Http\Response;

/**
 * Serves one version of the quiz data. Versions never change once stored,
 * so they can be cached anywhere for a year.
 */
class StanceController extends Controller
{
    public function __invoke(string $hash): Response
    {
        $snapshot = StanceSnapshot::query()->where('hash', $hash)->firstOrFail();

        return response($snapshot->payload, 200, [
            'Content-Type' => 'application/json',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
