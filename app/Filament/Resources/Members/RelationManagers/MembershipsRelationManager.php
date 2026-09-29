<?php

namespace App\Filament\Resources\Members\RelationManagers;

use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Each continuous stint in one house for one party, from database/data/memberships.csv.
 */
class MembershipsRelationManager extends RelationManager
{
    protected static string $relationship = 'memberships';

    protected static ?string $title = 'Seats and parties';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('starts_on')
            ->columns([
                TextColumn::make('house.name'),
                TextColumn::make('electorate.name')->label('Electorate'),
                TextColumn::make('party.name'),
                TextColumn::make('starts_on')->date('j M Y'),
                TextColumn::make('ends_on')->date('j M Y')->placeholder('—'),
                TextColumn::make('end_reason')->placeholder('—'),
            ]);
    }
}
