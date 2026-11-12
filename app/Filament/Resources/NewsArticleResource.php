<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NewsArticleResource\Pages;
use App\Models\NewsArticle;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Forms\Set;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class NewsArticleResource extends Resource
{
    protected static ?string $model = NewsArticle::class;

    protected static ?string $navigationIcon = 'heroicon-o-newspaper';
    protected static ?string $navigationLabel = 'Cricket News';
    protected static ?string $navigationGroup = 'Editorial';
    protected static ?int $navigationSort = 1;

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
                            ->afterStateUpdated(function (Set $set, ?string $state, ?string $operation) {
                                if ($operation === 'create' || empty($set('slug'))) {
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

                        Forms\Components\RichEditor::make('content')
                            ->label('Article Full Content')
                            ->toolbarButtons([
                                'h2',
                                'h3',
                                'bold',
                                'italic',
                                'link',
                                'bulletList',
                                'blockquote',
                                'undo',
                                'redo',
                            ])
                            ->nullable()
                            ->columnSpanFull(),
                    ])->columns(2),

                Forms\Components\Section::make('Publishing & Workflow')
                    ->schema([
                        Forms\Components\Select::make('status')
                            ->label('Publication Status')
                            ->options([
                                'draft' => 'Draft',
                                'published' => 'Published',
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
                    ])->columns(2),

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
                        'draft' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('source_name')
                    ->label('Source')
                    ->color('gray')
                    ->placeholder('Manual')
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
                        'draft' => 'Draft',
                        'published' => 'Published',
                    ]),
            ])
            ->actions([
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
