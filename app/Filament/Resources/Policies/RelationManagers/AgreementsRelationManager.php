<?php

namespace App\Filament\Resources\Policies\RelationManagers;

use App\Enums\AgreementCategory;
use App\Models\Member;
use App\Models\Party;
use App\Models\PolicyAgreement;
use Filament\Resources\RelationManagers\RelationManager;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * How each party and MP scored against the policy. Display notes, where given, replace the party figure in the published quiz.
 */
class AgreementsRelationManager extends RelationManager
{
    protected static string $relationship = 'agreements';

    protected static ?string $title = 'Scores';

    public function table(Table $table): Table
    {
        return $table
            ->modifyQueryUsing(fn (Builder $query) => $query->with('subject')->orderBy('subject_type', 'desc'))
            ->columns([
                TextColumn::make('subject_type')->label('Type')->formatStateUsing(fn (string $state): string => $state === 'party' ? 'Party' : 'MP'),
                TextColumn::make('subject')->label('Party or MP')->state(fn (PolicyAgreement $record): string => match (true) {
                    $record->subject instanceof Party => $record->subject->name,
                    $record->subject instanceof Member => $record->subject->display_name,
                    default => '—',
                }),
                TextColumn::make('category')->label('Voted')->formatStateUsing(fn (string $state): string => AgreementCategory::from($state)->label())->badge(),
                TextColumn::make('agreement')->formatStateUsing(fn (?float $state): string => $state === null ? '—' : round($state * 100).'%')->placeholder('—'),
                TextColumn::make('same')->label('Same (strong)')->state(fn (PolicyAgreement $record): string => "{$record->votes_same} ({$record->votes_same_strong})"),
                TextColumn::make('differ')->label('Differ (strong)')->state(fn (PolicyAgreement $record): string => "{$record->votes_differ} ({$record->votes_differ_strong})"),
                TextColumn::make('absent')->label('Did not vote (strong)')->state(fn (PolicyAgreement $record): string => "{$record->votes_absent} ({$record->votes_absent_strong})"),
            ])
            ->filters([
                SelectFilter::make('subject_type')->label('Type')->options(['party' => 'Parties', 'member' => 'MPs'])->default('party'),
            ]);
    }
}
