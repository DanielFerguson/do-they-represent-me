<?php

namespace App\Filament\Resources\Divisions\RelationManagers;

use App\Enums\VoteValue;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Each member's recorded vote, with their party on the day.
 */
class VotesRelationManager extends RelationManager
{
    protected static string $relationship = 'votes';

    protected static ?string $title = 'Votes';

    public function table(Table $table): Table
    {
        return $table
            ->defaultSort('vote')
            ->columns([
                TextColumn::make('member.display_name')->label('Member')->searchable(),
                TextColumn::make('party.short_name')->label('Party on the day'),
                TextColumn::make('vote')->formatStateUsing(fn (VoteValue $state): string => ucfirst($state->value)),
                TextColumn::make('is_teller')->label('Teller')->formatStateUsing(fn (bool $state): string => $state ? 'Yes' : ''),
            ]);
    }
}
