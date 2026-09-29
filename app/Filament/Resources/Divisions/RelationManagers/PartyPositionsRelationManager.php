<?php

namespace App\Filament\Resources\Divisions\RelationManagers;

use App\Enums\PartyPosition;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * How each party voted, by strict majority of its members who voted, using each member's party on the day.
 */
class PartyPositionsRelationManager extends RelationManager
{
    protected static string $relationship = 'partyPositions';

    protected static ?string $title = 'Party positions';

    public function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('party.name')->label('Party'),
                TextColumn::make('position')->badge()->formatStateUsing(fn (PartyPosition $state): string => ucfirst($state->value)),
                TextColumn::make('ayes')->numeric(),
                TextColumn::make('noes')->numeric(),
                TextColumn::make('eligible')->label('Could vote')->numeric(),
            ]);
    }
}
