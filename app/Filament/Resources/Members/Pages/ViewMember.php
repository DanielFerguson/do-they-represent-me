<?php

namespace App\Filament\Resources\Members\Pages;

use App\Filament\Resources\Concerns\HasNumericRecordRoute;
use App\Filament\Resources\Members\MemberResource;
use Filament\Resources\Pages\ViewRecord;

class ViewMember extends ViewRecord
{
    use HasNumericRecordRoute;

    protected static string $resource = MemberResource::class;
}
