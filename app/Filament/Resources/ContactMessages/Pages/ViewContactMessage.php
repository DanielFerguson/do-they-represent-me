<?php

namespace App\Filament\Resources\ContactMessages\Pages;

use App\Filament\Resources\Concerns\HasNumericRecordRoute;
use App\Filament\Resources\ContactMessages\ContactMessageResource;
use Filament\Resources\Pages\ViewRecord;

class ViewContactMessage extends ViewRecord
{
    use HasNumericRecordRoute;

    protected static string $resource = ContactMessageResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ContactMessageResource::markHandledAction(),
        ];
    }
}
