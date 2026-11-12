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

    protected function getHeaderActions(): array
    {
        return [
            Actions\Action::make('fetchNews')
                ->label('⚡ Fetch Trending News')
                ->color('warning')
                ->modalHeading('Interactive Cricket News Ingestion')
                ->modalDescription('Fetch live trending headlines and pick only the stories you want to draft.')
                ->modalSubmitActionLabel('Import Selected to Drafts')
                ->modalWidth('4xl')
                ->steps([
                    Forms\Components\Wizard\Step::make('Select Provider')
                        ->description('Pick an active cricket news API source')
                        ->schema([
                            Forms\Components\Select::make('provider_id')
                                ->label('News API Provider')
                                ->options(fn () => NewsApiConfig::where('is_active', true)->pluck('name', 'id'))
                                ->required()
                                ->searchable()
                                ->placeholder('Select an active provider')
                                ->helperText(function () {
                                    $count = NewsApiConfig::where('is_active', true)->count();
                                    return $count === 0 
                                        ? 'No active providers configured. Please add one in News API Configs.' 
                                        : "{$count} active provider(s) available.";
                                }),
                        ]),

                    Forms\Components\Wizard\Step::make('Review & Select Articles')
                        ->description('Check only the articles you wish to import')
                        ->schema([
                            Forms\Components\Placeholder::make('instructions')
                                ->label('Instructions')
                                ->content('Select the specific cricket articles you want to rewrite and publish. Only selected items will be imported as drafts.'),

                            Forms\Components\CheckboxList::make('selected_article_indices')
                                ->label('Available Headlines')
                                ->options(function (Forms\Get $get, Forms\Set $set, NewsIngestionService $service): array {
                                    $providerId = $get('provider_id');
                                    if (!$providerId) {
                                        return [];
                                    }

                                    $config = NewsApiConfig::find($providerId);
                                    if (!$config) {
                                        return [];
                                    }

                                    // Check if articles are already loaded in state
                                    $cachedArticles = $get('fetched_articles_payload');
                                    if (empty($cachedArticles)) {
                                        $articles = $service->fetchRawPreviews($config);
                                        $set('fetched_articles_payload', $articles);
                                    } else {
                                        $articles = $cachedArticles;
                                    }

                                    if (empty($articles)) {
                                        return [];
                                    }

                                    $options = [];
                                    foreach ($articles as $index => $article) {
                                        $title = e($article['title'] ?? 'Untitled Article');
                                        $summary = e(Str::limit($article['summary'] ?? 'No summary provided.', 140));
                                        $image = !empty($article['image']) 
                                            ? '<img src="' . e($article['image']) . '" alt="Thumbnail" class="w-16 h-12 object-cover rounded-md shrink-0 border border-slate-700">'
                                            : '<div class="w-16 h-12 rounded-md bg-slate-800 flex items-center justify-center shrink-0 text-xs text-slate-500 font-bold border border-slate-700">NO IMG</div>';

                                        $options[$index] = new HtmlString('
                                            <div class="flex items-start gap-3 py-1.5 w-full">
                                                ' . $image . '
                                                <div class="min-w-0 flex-1">
                                                    <div class="font-bold text-sm text-slate-100 leading-snug line-clamp-2">' . $title . '</div>
                                                    <div class="text-xs text-slate-400 mt-1 line-clamp-2 leading-relaxed">' . $summary . '</div>
                                                </div>
                                            </div>
                                        ');
                                    }

                                    return $options;
                                })
                                ->descriptions(function (Forms\Get $get): array {
                                    return [];
                                })
                                ->columns(1)
                                ->bulkToggleable()
                                ->required()
                                ->helperText('Check the boxes of the articles you want to save to your drafts.'),

                            Forms\Components\Hidden::make('fetched_articles_payload'),
                        ]),
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

                    $allArticles = $data['fetched_articles_payload'] ?? [];
                    if (empty($allArticles)) {
                        $allArticles = $service->fetchRawPreviews($config);
                    }

                    $selectedIndices = $data['selected_article_indices'] ?? [];
                    $selectedArticles = [];

                    foreach ($selectedIndices as $index) {
                        if (isset($allArticles[$index])) {
                            $selectedArticles[] = $allArticles[$index];
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
