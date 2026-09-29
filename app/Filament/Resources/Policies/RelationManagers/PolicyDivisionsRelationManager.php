<?php

namespace App\Filament\Resources\Policies\RelationManagers;

use App\Enums\VoteValue;
use App\Models\PolicyDivision;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * The divisions linked to a policy, with the direction and weight the curators gave each.
 */
class PolicyDivisionsRelationManager extends RelationManager
{
    protected static string $relationship = 'policyDivisions';

    protected static ?string $title = 'Linked votes';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with(['division.house', 'division.parliament']))
            ->columns([
                TextColumn::make('division.reference')->label('Division')->state(fn (PolicyDivision $record): string => $record->division->reference()),
                TextColumn::make('division.sitting_date')->label('Date')->date('j M Y'),
                TextColumn::make('division.stage')->label('Stage')->formatStateUsing(fn ($state): string => $state?->label() ?? '—'),
                TextColumn::make('direction')->label('Agree when')->formatStateUsing(fn (VoteValue $state): string => ucfirst($state->value)),
                TextColumn::make('is_strong')->label('Weight')->formatStateUsing(fn (bool $state): string => $state ? 'Strong' : 'Normal'),
                TextColumn::make('rationale')->wrap(),
            ]);
    }
}
