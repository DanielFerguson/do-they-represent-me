<?php

namespace App\Filament\Resources\Divisions\Pages;

use App\Filament\Resources\Concerns\HasNumericRecordRoute;
use App\Filament\Resources\Divisions\DivisionResource;
use Filament\Resources\Pages\ViewRecord;

class ViewDivision extends ViewRecord
{
    use HasNumericRecordRoute;

    protected static string $resource = DivisionResource::class;
}
