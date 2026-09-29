<?php

namespace App\Filament\Resources\Members;

use App\Filament\Resources\Concerns\IsReadOnly;
use App\Filament\Resources\Members\Pages\ListMembers;
use App\Filament\Resources\Members\Pages\ViewMember;
use App\Filament\Resources\Members\RelationManagers\MembershipsRelationManager;
use App\Filament\Resources\Members\RelationManagers\PolicyAgreementsRelationManager;
use App\Models\Member;
use App\Models\Membership;
use BackedEnum;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Members of the 60th Parliament, from database/data. Read-only: the
 * curated CSV files are the source.
 */
class MemberResource extends Resource
{
    use IsReadOnly;

    protected static ?string $model = Member::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedUserGroup;

    protected static ?string $recordTitleAttribute = 'display_name';

    protected static ?int $navigationSort = 3;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()->with(['memberships.house', 'memberships.party', 'memberships.electorate']);
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make()
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('display_name')->label('Name'),
                        TextEntry::make('latest_seat')->label('Latest seat')->state(fn (Member $record): string => self::latestSeat($record)),
                        TextEntry::make('profile_url')->label('Parliament profile')->url(fn (Member $record): ?string => $record->profile_url, shouldOpenInNewTab: true),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('last_name')
            ->columns([
                TextColumn::make('display_name')->label('Name')->searchable(['display_name', 'last_name'])->sortable(['last_name']),
                TextColumn::make('latest_seat')->label('Latest seat')->state(fn (Member $record): string => self::latestSeat($record)),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    public static function getRelations(): array
    {
        return [
            MembershipsRelationManager::class,
            PolicyAgreementsRelationManager::class,
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListMembers::route('/'),
            'view' => ViewMember::route('/{record}'),
        ];
    }

    /**
     * The member's most recent seat and party, e.g. "Assembly · Bendigo East · ALP".
     */
    private static function latestSeat(Member $member): string
    {
        /** @var Membership|null $membership */
        $membership = $member->memberships->sortByDesc('starts_on')->first();

        if ($membership === null) {
            return '—';
        }

        $seat = "{$membership->house->short_name} · {$membership->electorate->name} · {$membership->party->short_name}";

        return $membership->ends_on === null ? $seat : "{$seat} (until {$membership->ends_on->format('j M Y')})";
    }
}
