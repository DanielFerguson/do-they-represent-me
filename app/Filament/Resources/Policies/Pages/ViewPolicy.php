<?php

namespace App\Filament\Resources\Policies\Pages;

use App\Filament\Resources\Concerns\HasNumericRecordRoute;
use App\Filament\Resources\Policies\PolicyResource;
use Filament\Resources\Pages\ViewRecord;

class ViewPolicy extends ViewRecord
{
    use HasNumericRecordRoute;

    protected static string $resource = PolicyResource::class;
}
