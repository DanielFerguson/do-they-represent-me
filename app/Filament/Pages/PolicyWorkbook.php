<?php

namespace App\Filament\Pages;

use App\Domain\Policies\Importing\ImportPolicyWorkbook;
use App\Domain\Policies\Importing\InvalidPolicyWorkbook;
use App\Http\Controllers\PreviewController;
use App\Models\PolicyImport;
use App\Models\User;
use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\TextInput;
use Filament\Infolists\Components\TextEntry;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Schemas\Components\Actions;
use Filament\Schemas\Components\EmbeddedSchema;
use Filament\Schemas\Components\Form;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Illuminate\Support\Arr;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use RuntimeException;

/**
 * The one place the admin panel changes data: uploading a new version of the
 * policy workbook after reviewers have edited it. The whole workbook is
 * checked first, and nothing changes unless every check passes.
 *
 * @property-read Schema $form
 */
class PolicyWorkbook extends Page
{
    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedDocumentArrowUp;

    protected static ?string $title = 'Policy workbook';

    protected static ?int $navigationSort = 0;

    /**
     * @var array<string, mixed>|null
     */
    public ?array $data = [];

    /**
     * Problems found in the last workbook uploaded, if it was rejected.
     *
     * @var list<string>
     */
    public array $problems = [];

    public function mount(): void
    {
        $this->form->fill();
    }

    public function form(Schema $schema): Schema
    {
        return $schema
            ->components([
                FileUpload::make('workbook')
                    ->label('Workbook (.xlsx)')
                    ->helperText('Upload the curation workbook after reviewers have edited it. Every tab is checked first; nothing changes unless all the checks pass.')
                    ->acceptedFileTypes([
                        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'application/zip',
                        'application/octet-stream',
                    ])
                    ->maxSize(10240)
                    ->storeFiles(false)
                    ->required(),
            ])
            ->statePath('data');
    }

    public function content(Schema $schema): Schema
    {
        return $schema
            ->components([
                Section::make('Problems found')
                    ->description('The workbook was not imported. Fix these in the workbook and upload it again.')
                    ->visible(fn (): bool => $this->problems !== [])
                    ->schema([
                        TextEntry::make('problems')->hiddenLabel()->state(fn (): array => $this->problems)->bulleted()->listWithLineBreaks(),
                    ]),
                Section::make('Latest import')
                    ->columns(2)
                    ->schema($this->latestImportEntries()),
                Form::make([EmbeddedSchema::make('form')])
                    ->id('form')
                    ->livewireSubmitHandler('import')
                    ->footer([
                        Actions::make([
                            Action::make('import')->label('Check and import')->submit('import'),
                        ]),
                    ]),
            ]);
    }

    public function import(ImportPolicyWorkbook $importer): void
    {
        $upload = Arr::first(Arr::wrap($this->form->getState()['workbook'] ?? null));

        if (! $upload instanceof TemporaryUploadedFile) {
            return;
        }

        /** @var User|null $user */
        $user = auth()->user();
        $this->problems = [];

        try {
            ['import' => $import, 'scores' => $scores] = $importer->fromBytes((string) $upload->get(), $user);
        } catch (InvalidPolicyWorkbook $exception) {
            $this->problems = $exception->errors;
            Notification::make()->danger()->title('The workbook was not imported')->body('See the problems listed on this page.')->send();

            return;
        } catch (RuntimeException $exception) {
            $this->problems = ["The file could not be read as a workbook: {$exception->getMessage()}"];
            Notification::make()->danger()->title('The workbook was not imported')->send();

            return;
        } finally {
            $upload->delete();
            $this->form->fill();
        }

        Notification::make()
            ->success()
            ->title('Workbook imported')
            ->body(sprintf(
                '%s. %d linked votes. %s',
                self::describeCounts($import->summary['policies_by_status']),
                $import->summary['links'],
                $scores['snapshot'] === null ? 'No policies are published yet.' : 'The published quiz data has been updated.',
            ))
            ->send();
    }

    /**
     * @return array<Action>
     */
    protected function getHeaderActions(): array
    {
        return [
            Action::make('previewLink')
                ->label('Create preview link')
                ->icon(Heroicon::OutlinedLink)
                ->modalHeading('Preview link for reviewers')
                ->modalDescription('Anyone with this link can try the quiz with the policies still in review, for 14 days. Search engines won\'t index it.')
                ->schema([
                    TextInput::make('url')
                        ->label('Link')
                        ->default(fn (): string => PreviewController::linkUntil(now()->addDays(14)))
                        ->readOnly()
                        ->copyable(),
                ])
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('Done'),
        ];
    }

    /**
     * @return array<TextEntry>
     */
    private function latestImportEntries(): array
    {
        $import = PolicyImport::query()->with('user')->latest('id')->first();

        if ($import === null) {
            return [TextEntry::make('none')->hiddenLabel()->state('No workbook has been imported yet.')];
        }

        return [
            TextEntry::make('imported_at')->label('Imported')->state($import->created_at->timezone('Australia/Melbourne')->format('j M Y, g:ia')),
            TextEntry::make('imported_by')->label('By')->state($import->user->email ?? 'Command line'),
            TextEntry::make('policies')->state(self::describeCounts($import->summary['policies_by_status'])),
            TextEntry::make('links')->label('Linked votes')->state((string) $import->summary['links']),
            TextEntry::make('sha256')->label('SHA-256')->state($import->sha256)->copyable()->columnSpanFull(),
        ];
    }

    /**
     * @param  array<string, int>  $counts
     */
    private static function describeCounts(array $counts): string
    {
        return collect($counts)->map(fn (int $count, string $status): string => "{$count} {$status}")->implode(', ');
    }
}
