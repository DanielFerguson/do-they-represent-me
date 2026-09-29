<?php

namespace App\Filament\Resources\Divisions;

use App\Enums\DivisionStage;
use App\Filament\Resources\Concerns\IsReadOnly;
use App\Filament\Resources\Divisions\Pages\ListDivisions;
use App\Filament\Resources\Divisions\Pages\ViewDivision;
use App\Filament\Resources\Divisions\RelationManagers\PartyPositionsRelationManager;
use App\Filament\Resources\Divisions\RelationManagers\VotesRelationManager;
use App\Models\Division;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TernaryFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Divisions as imported from the Votes and Proceedings and Minutes.
 * Read-only: they are rebuilt from the stored documents.
 */
class DivisionResource extends Resource
{
    use IsReadOnly;

    protected static ?string $model = Division::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedScale;

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['house', 'parliament']);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('reference')->state(fn (Division $record): string => $record->reference()),
                        TextEntry::make('sitting_date')->label('Date')->date('j M Y'),
                        TextEntry::make('house.name'),
                        TextEntry::make('item_title')->label('Bill or motion')->columnSpanFull(),
                        TextEntry::make('question')->label('Question put')->columnSpanFull(),
                        TextEntry::make('stage')->formatStateUsing(fn (?DivisionStage $state): string => $state?->label() ?? '—'),
                        TextEntry::make('result'),
                        TextEntry::make('tally')->label('Ayes–Noes')->state(fn (Division $record): string => "{$record->ayes_count}–{$record->noes_count}"),
                        TextEntry::make('presidingMember.display_name')->label('In the chair')->placeholder('Not recorded'),
                        TextEntry::make('body'),
                        TextEntry::make('proceedingsDocument.title')->label('Source document')
                            ->url(fn (Division $record): ?string => $record->proceedingsDocument->docx_url ?: $record->proceedingsDocument->pdf_url, shouldOpenInNewTab: true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('sitting_date', 'desc')
            ->columns([
                TextColumn::make('reference')->state(fn (Division $record): string => $record->reference()),
                TextColumn::make('sitting_date')->label('Date')->date('j M Y')->sortable(),
                TextColumn::make('house.short_name')->label('House'),
                TextColumn::make('stage')->formatStateUsing(fn (?DivisionStage $state): string => $state?->label() ?? '—'),
                TextColumn::make('item_title')->label('Bill or motion')->searchable()->wrap()->limit(80),
                TextColumn::make('tally')->label('Ayes–Noes')->state(fn (Division $record): string => "{$record->ayes_count}–{$record->noes_count}"),
            ])
            ->filters([
                SelectFilter::make('house')->relationship('house', 'name'),
                SelectFilter::make('stage')->options(collect(DivisionStage::cases())->mapWithKeys(fn (DivisionStage $stage): array => [$stage->value => $stage->label()])),
                TernaryFilter::make('needs_review')->label('Needs review'),
                TernaryFilter::make('linked')
                    ->label('Linked to a policy')
                    ->queries(
                        true: fn (Builder $query) => $query->whereHas('policyDivisions'),
                        false: fn (Builder $query) => $query->whereDoesntHave('policyDivisions'),
                    ),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            PartyPositionsRelationManager::class,
            VotesRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListDivisions::route('/'),
            'view' => ViewDivision::route('/{record}'),
        ];
    }
}
