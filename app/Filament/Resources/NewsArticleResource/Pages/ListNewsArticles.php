<?php

namespace App\Filament\Resources\NewsArticleResource\Pages;

use App\Filament\Resources\NewsArticleResource;
use App\Models\NewsApiConfig;
use App\Services\News\NewsIngestionService;
use Filament\Actions;
use Filament\Forms;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\ListRecords;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class ListNewsArticles extends ListRecords
{
    protected static string $resource = NewsArticleResource::class;

    /**
     * Component property storing fetched preview articles across Livewire renders.
     */
    public array $previewArticles = [];

    /**
     * Component property storing any error or status message from the last fetch attempt.
     */
    public ?string $fetchStatusMessage = null;

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('fetchNews')
                ->label('⚡ Fetch Trending News')
                ->color('warning')
                ->modalHeading('Interactive Cricket News Ingestion')
                ->modalDescription('Select the specific cricket articles you want to rewrite and publish. Only selected items will be imported as drafts.')
                ->modalSubmitActionLabel('Import Selected to Drafts')
                ->modalWidth('4xl')
                ->form([
                    Forms\Components\Select::make('provider_id')
                        ->label('News API Provider')
                        ->options(fn () => NewsApiConfig::where('is_active', true)->pluck('name', 'id'))
                        ->required()
                        ->searchable()
                        ->placeholder('Select an active provider')
                        ->live()
                        ->afterStateUpdated(function ($state, Forms\Set $set, NewsIngestionService $service) {
                            $set('selected_articles', []);
                            $this->previewArticles = [];
                            $this->fetchStatusMessage = null;

                            if (!$state) {
                                return;
                            }

                            $config = NewsApiConfig::find($state);
                            if (!$config) {
                                $this->fetchStatusMessage = 'Selected provider could not be found.';
                                return;
                            }

                            $articles = $service->fetchRawPreviews($config);
                            $this->previewArticles = $articles;

                            if (empty($articles)) {
                                $status = $service->getLastHttpStatus();
                                $err = $service->getLastErrorMessage();
                                $reason = $err ?: ($status ? "HTTP {$status}" : 'No articles returned by provider.');
                                $this->fetchStatusMessage = "Failed to load articles from [{$config->name}]: {$reason}";
                            }
                        })
                        ->helperText(function () {
                            $count = NewsApiConfig::where('is_active', true)->count();
                            return $count === 0 
                                ? 'No active providers configured. Please add one in News API Configs.' 
                                : "{$count} active provider(s) available. Selecting a provider immediately fetches trending headlines.";
                        }),

                    Forms\Components\Placeholder::make('no_articles_warning')
                        ->label('Fetch Status')
                        ->content(fn () => new HtmlString('<div class="p-3 text-sm text-amber-300 bg-amber-950/40 border border-amber-800/60 rounded-lg flex items-center gap-2"><span>⚠️</span> <span>' . e($this->fetchStatusMessage ?? 'No articles found or provider quota exceeded.') . '</span></div>'))
                        ->visible(fn (Forms\Get $get) => filled($get('provider_id')) && empty($this->previewArticles) && filled($this->fetchStatusMessage)),

                    Forms\Components\Placeholder::make('instructions')
                        ->label('Instructions')
                        ->content('Select the specific cricket articles you want to rewrite and publish. Only selected items will be imported as drafts.')
                        ->visible(fn () => !empty($this->previewArticles)),

                    Forms\Components\CheckboxList::make('selected_articles')
                        ->label('Available Headlines')
                        ->options(function (): array {
                            $options = [];
                            foreach ($this->previewArticles as $index => $article) {
                                $title = e($article['title'] ?? 'Untitled Article');
                                $summary = !empty($article['summary']) 
                                    ? ' — ' . e(Str::limit(strip_tags($article['summary']), 70)) 
                                    : '';
                                $image = !empty($article['image']) 
                                    ? '<img src="' . e($article['image']) . '" alt="Thumbnail" class="w-14 h-10 object-cover rounded shrink-0 border border-slate-700">'
                                    : '<div class="w-14 h-10 rounded bg-slate-800 flex items-center justify-center shrink-0 text-[10px] text-slate-500 font-bold border border-slate-700">NO IMG</div>';

                                $options[(string)$index] = new HtmlString('
                                    <div class="flex items-center gap-3 py-1 w-full">
                                        ' . $image . '
                                        <div class="min-w-0 flex-1">
                                            <div class="font-semibold text-sm text-slate-100 leading-snug line-clamp-2">' . $title . '<span class="font-normal text-xs text-slate-400">' . $summary . '</span></div>
                                        </div>
                                    </div>
                                ');
                            }
                            return $options;
                        })
                        ->visible(fn () => !empty($this->previewArticles))
                        ->columns(1)
                        ->bulkToggleable()
                        ->required()
                        ->helperText('Check the boxes of the articles you want to save to your drafts.'),
                ])
                ->action(function (array $data, NewsIngestionService $service): void {
                    $providerId = $data['provider_id'] ?? null;
                    $config = NewsApiConfig::find($providerId);

                    if (!$config) {
                        Notification::make()
                            ->title('Provider Not Found')
                            ->body('Selected provider configuration could not be loaded.')
                            ->danger()
                            ->send();
                        return;
                    }

                    $selectedIndices = $data['selected_articles'] ?? [];
                    $selectedArticles = [];

                    foreach ($selectedIndices as $index) {
                        if (isset($this->previewArticles[$index])) {
                            $selectedArticles[] = $this->previewArticles[$index];
                        }
                    }

                    if (empty($selectedArticles)) {
                        Notification::make()
                            ->title('No Articles Selected')
                            ->body('Please check at least one article to import.')
                            ->warning()
                            ->send();
                        return;
                    }

                    $importedCount = $service->importSelectedArticles($selectedArticles, $config->name);

                    // Reset component properties after import
                    $this->previewArticles = [];
                    $this->fetchStatusMessage = null;

                    if ($importedCount > 0) {
                        Notification::make()
                            ->title('Articles Saved to Drafts')
                            ->body("Successfully imported {$importedCount} of " . count($selectedArticles) . " selected article(s) into your drafts.")
                            ->success()
                            ->send();
                    } else {
                        Notification::make()
                            ->title('No New Articles Imported')
                            ->body('All selected articles already exist in drafts (duplicates skipped).')
                            ->info()
                            ->send();
                    }
                }),

            Actions\CreateAction::make(),
        ];
    }
}
