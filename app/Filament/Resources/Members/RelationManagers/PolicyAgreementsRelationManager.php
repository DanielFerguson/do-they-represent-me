<?php

namespace App\Filament\Resources\Members\RelationManagers;

use App\Enums\AgreementCategory;
use App\Filament\Resources\Policies\PolicyResource;
use App\Models\PolicyAgreement;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * How the member's own votes scored against each policy.
 */
class PolicyAgreementsRelationManager extends RelationManager
{
    protected static string $relationship = 'policyAgreements';

    protected static ?string $title = 'Scores';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('policy'))
            ->columns([
                TextColumn::make('policy.number')->label('Policy')->formatStateUsing(fn (int $state): string => PolicyResource::code($state))->sortable(),
                TextColumn::make('policy.title')->label('Title')->wrap(),
                TextColumn::make('category')->label('Voted')->formatStateUsing(fn (string $state): string => AgreementCategory::from($state)->label())->badge(),
                TextColumn::make('agreement')->formatStateUsing(fn (?float $state): string => $state === null ? '—' : round($state * 100).'%')->placeholder('—'),
                TextColumn::make('same')->label('Same (strong)')->state(fn (PolicyAgreement $record): string => "{$record->votes_same} ({$record->votes_same_strong})"),
                TextColumn::make('differ')->label('Differ (strong)')->state(fn (PolicyAgreement $record): string => "{$record->votes_differ} ({$record->votes_differ_strong})"),
                TextColumn::make('absent')->label('Did not vote (strong)')->state(fn (PolicyAgreement $record): string => "{$record->votes_absent} ({$record->votes_absent_strong})"),
            ]);
    }
}
