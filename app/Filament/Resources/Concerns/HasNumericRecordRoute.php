<?php

namespace App\Filament\Resources\Concerns;

use Filament\Panel;
use Filament\Resources\Pages\PageRegistration;
use Illuminate\Routing\Route;

/**
 * Only match numeric record IDs, so a path like /admin/policies/create is a
 * 404 rather than a failed database lookup.
 */
trait HasNumericRecordRoute
{
    public static function route(string $path): PageRegistration
    {
        $registration = parent::route($path);

        return new PageRegistration(static::class, fn (Panel $panel): ?Route => $registration->registerRoute($panel)?->whereNumber('record'));
    }
}
