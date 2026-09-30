<?php

namespace App\Filament\Resources\ContactMessages;

use App\Enums\ContactTopic;
use App\Filament\Resources\Concerns\IsReadOnly;
use App\Filament\Resources\ContactMessages\Pages\ListContactMessages;
use App\Filament\Resources\ContactMessages\Pages\ViewContactMessage;
use App\Models\ContactMessage;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Actions\ViewAction;
use Filament\Infolists\Components\TextEntry;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\Filter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

/**
 * Messages sent through the public contact form. They can't be written or
 * edited here, only read and marked as handled. They are deleted a year
 * after they arrive.
 */
class ContactMessageResource extends Resource
{
    use IsReadOnly;

    protected static ?string $model = ContactMessage::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedEnvelope;

    protected static ?string $navigationLabel = 'Messages';

    protected static ?int $navigationSort = 10;

    protected static string|\Illuminate\Contracts\Support\Htmlable|null $navigationBadgeTooltip = 'Not yet handled';

    public static function getNavigationBadge(): ?string
    {
        $unhandled = ContactMessage::query()->whereNull('handled_at')->count();

        return $unhandled > 0 ? (string) $unhandled : null;
    }

    public static function infolist(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Message')
                    ->columns(3)
                    ->columnSpanFull()
                    ->schema([
                        TextEntry::make('topic')->badge()->formatStateUsing(fn (ContactTopic $state): string => $state->label()),
                        TextEntry::make('created_at')->label('Received')->dateTime('j M Y, g:ia', 'Australia/Melbourne'),
                        TextEntry::make('handled_at')->label('Handled')->dateTime('j M Y, g:ia', 'Australia/Melbourne')->placeholder('Not yet'),
                        TextEntry::make('name')->placeholder('Not given'),
                        TextEntry::make('email')->copyable(),
                        TextEntry::make('context')->label('About')->placeholder('Nothing in particular')->url(fn (ContactMessage $record): ?string => $record->context_url, shouldOpenInNewTab: true),
                        TextEntry::make('message')->columnSpanFull(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->defaultSort('created_at', 'desc')
            ->columns([
                TextColumn::make('created_at')->label('Received')->since('Australia/Melbourne')->sortable(),
                TextColumn::make('topic')->badge()->formatStateUsing(fn (ContactTopic $state): string => $state->label()),
                TextColumn::make('email')->searchable(),
                TextColumn::make('context')->label('About')->limit(60)->placeholder('—'),
                TextColumn::make('handled_at')->label('Handled')->since('Australia/Melbourne')->placeholder('Not yet'),
            ])
            ->filters([
                Filter::make('unhandled')
                    ->label('Not yet handled')
                    ->query(fn (Builder $query): Builder => $query->whereNull('handled_at'))
                    ->default(),
            ])
            ->recordActions([
                ViewAction::make(),
            ]);
    }

    /**
     * Records that a message has been dealt with, so it drops off the list.
     */
    public static function markHandledAction(): Action
    {
        return Action::make('markHandled')
            ->label('Mark handled')
            ->icon(Heroicon::OutlinedCheck)
            ->visible(fn (ContactMessage $record): bool => $record->handled_at === null)
            ->action(function (ContactMessage $record): void {
                $record->update(['handled_at' => now()]);
            });
    }

    public static function getPages(): array
    {
        return [
            'index' => ListContactMessages::route('/'),
            'view' => ViewContactMessage::route('/{record}'),
        ];
    }
}
