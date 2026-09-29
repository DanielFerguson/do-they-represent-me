<?php

namespace App\Filament\Resources\Policies;

use App\Enums\PolicyStatus;
use App\Filament\Resources\Concerns\IsReadOnly;
use App\Filament\Resources\Policies\Pages\ListPolicies;
use App\Filament\Resources\Policies\Pages\ViewPolicy;
use App\Filament\Resources\Policies\RelationManagers\AgreementsRelationManager;
use App\Filament\Resources\Policies\RelationManagers\PolicyDivisionsRelationManager;
use App\Models\Member;
use App\Models\Party;
use App\Models\Policy;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\RepeatableEntry;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;

/**
 * Policies as imported from the curation workbook. Read-only: policy text
 * is only ever edited in the workbook, then uploaded on the Policy workbook
 * page.
 */
class PolicyResource extends Resource
{
    use IsReadOnly;

    protected static ?string $model = Policy::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedQuestionMarkCircle;

    protected static ?string $recordTitleAttribute = 'title';

    protected static ?int $navigationSort = 1;

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Question')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('number')->label('ID')->formatStateUsing(fn (int $state): string => self::code($state)),
                        TextEntry::make('status')->badge()->formatStateUsing(fn (PolicyStatus $state): string => ucfirst($state->value)),
                        TextEntry::make('topic'),
                        TextEntry::make('question')->columnSpanFull(),
                        TextEntry::make('agree_means')->label('"Agree" means')->columnSpanFull(),
                        TextEntry::make('description')->columnSpanFull(),
                    ]),
                Section::make('Arguments and sources')
                    ->columnSpanFull()
                    ->collapsible()
                    ->schema([
                        TextEntry::make('arguments_for'),
                        TextEntry::make('arguments_against'),
                        TextEntry::make('sources'),
                        TextEntry::make('rationale')->label('Why this question'),
                    ]),
                Section::make('Review')
                    ->description('Not published. Shown to curators only.')
                    ->columnSpanFull()
                    ->collapsible()
                    ->schema([
                        TextEntry::make('verification_notes')->label('To verify before publishing'),
                        TextEntry::make('reviewer_notes'),
                        RepeatableEntry::make('display_notes')
                            ->label('Display notes (shown instead of a match figure)')
                            ->placeholder('None')
                            ->schema([
                                TextEntry::make('subject_id')->label('Party or MP')->formatStateUsing(fn (int $state, Get $get): string => self::subjectName((string) $get('subject_type'), $state)),
                                TextEntry::make('note'),
                            ])
                            ->columns(2),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->recordTitleAttribute('title')
            ->defaultSort('number')
            ->columns([
                TextColumn::make('number')->label('ID')->sortable()->formatStateUsing(fn (int $state): string => self::code($state)),
                TextColumn::make('title')->searchable()->wrap(),
                TextColumn::make('topic')->sortable()->toggleable(),
                TextColumn::make('status')->badge()->formatStateUsing(fn (PolicyStatus $state): string => ucfirst($state->value)),
                TextColumn::make('policy_divisions_count')->counts('policyDivisions')->label('Linked votes')->numeric(),
            ])
            ->filters([
                SelectFilter::make('status')->options(collect(PolicyStatus::cases())->mapWithKeys(fn (PolicyStatus $status): array => [$status->value => ucfirst($status->value)])),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            PolicyDivisionsRelationManager::class,
            AgreementsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListPolicies::route('/'),
            'view' => ViewPolicy::route('/{record}'),
        ];
    }

    /**
     * The policy's ID as the workbook writes it, e.g. P07.
     */
    public static function code(int $number): string
    {
        return sprintf('P%02d', $number);
    }

    public static function subjectName(string $type, int $id): string
    {
        return $type === 'party'
            ? (string) Party::query()->whereKey($id)->value('name')
            : (string) Member::query()->whereKey($id)->value('display_name');
    }
}
