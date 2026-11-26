<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NewsArticleResource\Pages;
use App\Models\NewsArticle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\HtmlString;
use Illuminate\Support\Str;

class NewsArticleResource extends Resource
{
    protected static ?string $model = NewsArticle::class;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';
    protected static ?string $navigationLabel = 'Cricket News';
    protected static ?string $navigationGroup = 'Editorial';
    protected static ?int $navigationSort = 1;

    public static function getNavigationBadge(): ?string
    {
        $count = NewsArticle::where('status', 'pending_review')->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Article Content')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(255)
                            ->live(onBlur: true)
                            ->afterStateUpdated(function (Set $set, Get $get, ?string $state, ?string $operation) {
                                if ($operation === 'create' || empty($get('slug'))) {
                                    $set('slug', Str::slug($state));
                                }
                            }),

                        Forms\Components\TextInput::make('slug')
                            ->required()
                            ->unique(ignoreRecord: true)
                            ->disabled()
                            ->dehydrated()
                            ->maxLength(255)
                            ->helperText('Auto-generated URL-safe identifier'),

                        Forms\Components\Select::make('category')
                            ->options([
                                'news' => 'Cricket News',
                                'pitch_report' => 'Pitch Report',
                                'prediction' => 'Match Prediction',
                                'fantasy_tips' => 'Fantasy Tips',
                            ])
                            ->default('news')
                            ->required(),

                        Forms\Components\FileUpload::make('featured_image')
                            ->label('Featured Image')
                            ->image()
                            ->disk('public')
                            ->directory('news')
                            ->maxSize(2048) // max 2MB
                            ->nullable()
                            ->columnSpanFull(),

                        Forms\Components\Textarea::make('summary')
                            ->label('Summary / Short Excerpt')
                            ->rows(2)
                            ->nullable()
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Article Full Content')
                    ->description('Write, rewrite, format headings, embed inline images, and insert CTAs just like WordPress.')
                    ->schema([
                        Forms\Components\RichEditor::make('content')
                            ->label('Article Full Content')
                            ->required()
                            ->columnSpanFull()
                            ->fileAttachmentsDisk('public')
                            ->fileAttachmentsDirectory('news-content')
                            ->fileAttachmentsVisibility('public')
                            ->toolbarButtons([
                                'attachFiles',
                                'blockquote',
                                'bold',
                                'bulletList',
                                'codeBlock',
                                'h2',
                                'h3',
                                'italic',
                                'link',
                                'orderedList',
                                'redo',
                                'strike',
                                'underline',
                                'undo',
                            ])
                            ->extraInputAttributes([
                                'style' => 'min-height: 450px; font-size: 16px; line-height: 1.75;',
                            ]),

                        // Quick WordPress-style Custom CTA / Shortcode Inserter Helper
                        Forms\Components\Actions::make([
                            Forms\Components\Actions\Action::make('insertTelegramCta')
                                ->label('➕ Insert Telegram Channel CTA')
                                ->icon('heroicon-o-paper-airplane')
                                ->color('info')
                                ->size('sm')
                                ->action(function (Forms\Get $get, Forms\Set $set) {
                                    $current = $get('content') ?? '';
                                    $ctaHtml = '<div style="margin: 24px 0; padding: 16px 20px; border-radius: 12px; background: rgba(14, 165, 233, 0.12); border: 1px solid rgba(14, 165, 233, 0.35);">'
                                        . '<p style="margin: 0; font-weight: 700; color: #38bdf8;">📢 Join Cricwire Official on Telegram!</p>'
                                        . '<p style="margin: 6px 0 0 0; font-size: 14px;">Get fastest ball-by-ball updates, toss alerts & breaking cricket news: <a href="https://t.me/cricwireofficial" target="_blank" rel="noopener noreferrer"><strong>Join @cricwireofficial →</strong></a></p>'
                                        . '</div><p></p>';
                                    $set('content', $current . $ctaHtml);
                                }),

                            Forms\Components\Actions\Action::make('insertLiveScoreCta')
                                ->label('🏏 Insert Live Score CTA')
                                ->icon('heroicon-o-bolt')
                                ->color('warning')
                                ->size('sm')
                                ->action(function (Forms\Get $get, Forms\Set $set) {
                                    $current = $get('content') ?? '';
                                    $ctaHtml = '<div style="margin: 24px 0; padding: 16px 20px; border-radius: 12px; background: rgba(245, 158, 11, 0.12); border: 1px solid rgba(245, 158, 11, 0.35);">'
                                        . '<p style="margin: 0; font-weight: 700; color: #fbbf24;">⚡ Catch Ball-by-Ball Live Cricket Scores</p>'
                                        . '<p style="margin: 6px 0 0 0; font-size: 14px;">Follow live match commentary, session stats & full scorecards on <a href="/"><strong>Cricwire Live Match Center →</strong></a></p>'
                                        . '</div><p></p>';
                                    $set('content', $current . $ctaHtml);
                                }),
                        ]),
                    ])
                    ->columnSpanFull(),

                Forms\Components\Section::make('Publishing & Workflow')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Publication Status')
                            ->options([
                                'draft' => 'Draft',
                                'pending_review' => 'Pending Review',
                                'published' => 'Published',
                                'rejected' => 'Rejected',
                            ])
                            ->default('draft')
                            ->required()
                            ->reactive()
                            ->afterStateUpdated(function (Set $set, ?string $state, Get $get) {
                                if ($state === 'published' && empty($get('published_at'))) {
                                    $set('published_at', now());
                                }
                                $set('is_published', $state === 'published');
                            }),

                        Forms\Components\DateTimePicker::make('published_at')
                            ->label('Publish Date & Time')
                            ->default(now())
                            ->nullable(),

                        Forms\Components\Toggle::make('is_featured')
                            ->label('Mark as Featured Editorial')
                            ->default(false)
                            ->helperText('Featured articles are prominently showcased on upcoming match screens and high-priority sections.'),

                        Forms\Components\Toggle::make('send_push_notification')
                            ->label('🔔 Send Breaking News Push to All Users')
                            ->helperText('Dispatches push notification broadcast to all subscribers on publish')
                            ->default(false)
                            ->dehydrated(false)
                            ->visible(fn (Get $get) => $get('status') === 'published'),

                        Forms\Components\TextInput::make('source_name')
                            ->label('Source Provider')
                            ->default('CricWire News Agency')
                            ->placeholder('e.g. CricWire News Agency, Cricbuzz')
                            ->maxLength(255)
                            ->nullable(),

                        Forms\Components\TextInput::make('source_url')
                            ->label('Source URL')
                            ->url()
                            ->placeholder('https://...')
                            ->maxLength(500)
                            ->nullable(),

                        Forms\Components\Select::make('series_slug')
                            ->label('Related Cricket Series (Optional)')
                            ->options(fn () => \App\Models\SkippedSeries::where('is_skipped', false)
                                ->orderBy('series_name')
                                ->pluck('series_name', 'series_name')
                                ->mapWithKeys(fn ($name) => [\Illuminate\Support\Str::slug($name) => $name])
                                ->toArray())
                            ->searchable()
                            ->nullable()
                            ->live()
                            ->afterStateUpdated(fn ($state, Forms\Set $set) => $set(
                                'series_name',
                                $state ? (\App\Models\SkippedSeries::where('is_skipped', false)->get()->first(fn ($s) => \Illuminate\Support\Str::slug($s->series_name) === $state)?->series_name) : null
                            )),

                        Forms\Components\Hidden::make('series_name')
                            ->dehydrated(),
                    ])->columns(2),

                Forms\Components\Section::make('Guest Author Details')
                    ->description('Community contributor attribution and moderation notes for guest submissions.')
                    ->schema([
                        Forms\Components\Toggle::make('is_guest_post')
                            ->label('Guest Contributor Article')
                            ->default(false),

                        Forms\Components\TextInput::make('author_name')
                            ->label('Author Display Name')
                            ->maxLength(100)
                            ->nullable(),

                        Forms\Components\TextInput::make('author_social_url')
                            ->label('Author Social / Profile URL')
                            ->url()
                            ->maxLength(255)
                            ->nullable(),

                        Forms\Components\TextInput::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->maxLength(255)
                            ->nullable(),

                        Forms\Components\Textarea::make('author_bio')
                            ->label('Author Bio')
                            ->rows(2)
                            ->maxLength(250)
                            ->nullable()
                            ->columnSpanFull(),
                    ])->columns(2)->collapsible(),

                Forms\Components\Section::make('SEO & Metadata')
                    ->description('Search engine optimization fields for Google News and search ranking.')
                    ->schema([
                        Forms\Components\TextInput::make('seo_meta_title')
                            ->label('SEO Meta Title')
                            ->maxLength(255)
                            ->placeholder('Keep under 60 characters for best display'),

                        Forms\Components\Textarea::make('seo_meta_description')
                            ->label('SEO Meta Description')
                            ->rows(3)
                            ->placeholder('Compelling summary for SERP previews (under 160 characters)'),
                    ])->columns(2)->collapsible(),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('featured_image')
                    ->disk('public')
                    ->circular()
                    ->defaultImageUrl(fn (NewsArticle $record): ?string => $record->featured_image && filter_var($record->featured_image, FILTER_VALIDATE_URL) ? $record->featured_image : null),

                Tables\Columns\TextColumn::make('title')
                    ->searchable()
                    ->sortable()
                    ->weight('bold')
                    ->limit(45),

                Tables\Columns\IconColumn::make('is_featured')
                    ->label('Featured')
                    ->boolean()
                    ->sortable(),

                Tables\Columns\TextColumn::make('category')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'news' => 'info',
                        'pitch_report' => 'warning',
                        'prediction' => 'success',
                        'fantasy_tips' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'published' => 'success',
                        'pending_review' => 'warning',
                        'rejected' => 'danger',
                        'draft' => 'gray',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn (string $state): string => match ($state) {
                        'pending_review' => 'Pending Review',
                        'published' => 'Published',
                        'rejected' => 'Rejected',
                        'draft' => 'Draft',
                        default => ucfirst($state),
                    }),

                Tables\Columns\TextColumn::make('source_name')
                    ->label('Source / Author')
                    ->color('gray')
                    ->placeholder('Manual')
                    ->formatStateUsing(function (?string $state, NewsArticle $record): HtmlString {
                        $label = e($record->is_guest_post && $record->author_name ? $record->author_name : ($state ?: 'Manual'));
                        if ($record->is_guest_post) {
                            return new HtmlString(
                                '<span class="inline-flex items-center gap-1.5">'
                                . '<span>' . $label . '</span>'
                                . '<span class="px-1.5 py-0.5 rounded text-[10px] font-bold bg-amber-500/20 text-amber-400 border border-amber-500/30">Guest</span>'
                                . '</span>'
                            );
                        }

                        return new HtmlString('<span>' . $label . '</span>');
                    })
                    ->searchable(['source_name', 'author_name'])
                    ->sortable(),

                Tables\Columns\TextColumn::make('published_at')
                    ->label('Published At')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('Unpublished'),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('category')
                    ->options([
                        'news' => 'Cricket News',
                        'pitch_report' => 'Pitch Report',
                        'prediction' => 'Match Prediction',
                        'fantasy_tips' => 'Fantasy Tips',
                    ]),
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'pending_review' => 'Pending Review',
                        'draft' => 'Draft',
                        'published' => 'Published',
                        'rejected' => 'Rejected',
                    ]),
                Tables\Filters\TernaryFilter::make('is_guest_post')
                    ->label('Guest Posts')
                    ->trueLabel('Guest Contributors Only')
                    ->falseLabel('Editorial Staff Only'),
            ])
            ->actions([
                Tables\Actions\Action::make('approvePublish')
                    ->label('✅ Approve & Publish')
                    ->color('success')
                    ->requiresConfirmation()
                    ->modalHeading('Approve & Publish Guest Article')
                    ->modalDescription('External links will remain protected with rel="nofollow noopener noreferrer" and this article will be published live.')
                    ->visible(fn (NewsArticle $record): bool => $record->status === 'pending_review')
                    ->action(function (NewsArticle $record): void {
                        $record->update([
                            'status' => 'published',
                            'is_published' => true,
                            'published_at' => now(),
                            'rejection_reason' => null,
                        ]);

                        Notification::make()
                            ->title('Article Approved & Published')
                            ->body("\"{$record->title}\" is now live.")
                            ->success()
                            ->send();
                    }),

                Tables\Actions\Action::make('rejectGuestPost')
                    ->label('❌ Reject')
                    ->color('danger')
                    ->visible(fn (NewsArticle $record): bool => $record->status === 'pending_review')
                    ->form([
                        Forms\Components\Textarea::make('rejection_reason')
                            ->label('Rejection Reason')
                            ->required()
                            ->maxLength(255)
                            ->placeholder('Explain why this submission was rejected (e.g., off-topic, promotional links, low depth).'),
                    ])
                    ->action(function (NewsArticle $record, array $data): void {
                        $record->update([
                            'status' => 'rejected',
                            'is_published' => false,
                            'rejection_reason' => $data['rejection_reason'],
                        ]);

                        Notification::make()
                            ->title('Guest Post Rejected')
                            ->body('Rejection reason saved for the contributor.')
                            ->warning()
                            ->send();
                    }),

                Tables\Actions\EditAction::make(),
                Tables\Actions\DeleteAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\DeleteBulkAction::make(),
                ]),
            ]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListNewsArticles::route('/'),
            'create' => Pages\CreateNewsArticle::route('/create'),
            'edit' => Pages\EditNewsArticle::route('/{record}/edit'),
        ];
    }
}
