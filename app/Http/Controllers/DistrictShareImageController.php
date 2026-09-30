<?php

namespace App\Http\Controllers;

use App\Domain\Districts\DistrictShareImage;
use App\Enums\ElectorateKind;
use App\Models\Electorate;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Response;

/**
 * The picture for a district page's link preview. Its address carries a hash
 * of everything drawn, so it can be cached anywhere for a year. A link
 * made before the district last changed is sent on to the current picture.
 */
class DistrictShareImageController extends Controller
{
    public function __invoke(DistrictShareImage $images, string $district, string $hash): Response|RedirectResponse
    {
        $record = Electorate::query()
            ->where('kind', ElectorateKind::District)
            ->where('slug', $district)
            ->with('region')
            ->firstOrFail();

        $card = $images->cardFor($record);

        if (! hash_equals($images->hash($card), $hash)) {
            return redirect($images->url($record, $card))->header('Cache-Control', 'no-cache');
        }

        return response($images->render($card), 200, [
            'Content-Type' => 'image/png',
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}
