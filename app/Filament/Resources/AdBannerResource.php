<?php

namespace App\Filament\Resources;

use App\Filament\Resources\AdBannerResource\Pages;
use App\Models\AdBanner;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Forms\Get;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class AdBannerResource extends Resource
{
    protected static ?string $model = AdBanner::class;

    protected static ?string $navigationIcon = 'heroicon-o-currency-dollar';
    protected static ?string $navigationLabel = 'Ad Banners';
    protected static ?string $navigationGroup = 'Monetization';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Ad Unit Details')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->placeholder('e.g. News Top Leaderboard or Stake Medium Banner')
                            ->required(),

                        Forms\Components\Select::make('placement')
                            ->label('Placement Slots')
                            ->multiple()
                            ->searchable()
                            ->preload()
                            ->options([
                                'news_top' => 'News Feed Top (728x90 Desktop / 320x50 Mobile)',
                                'news_in_feed' => 'News In-Feed Slot (300x250 or 320x100)',
                                'news_sidebar' => 'News Detail Sidebar Ad (300x250 / 300x600)',
                                'schedule_sidebar' => 'Schedule Page Sidebar Ad (300x250 / 300x600)',
                                'match_sidebar' => 'Match Detail Sidebar Ad (300x250 / 300x600)',
                                'upcoming_match' => 'Upcoming Match Screen (In-Feed)',
                                'home_top' => 'Home Page Top Banner',
                                'header' => 'Header Banner',
                                'pinned_match_below' => 'Below Pinned Match',
                                'match_detail_odds' => 'Match Detail Odds Widget',
                                'sidebar' => 'Sidebar Banner',
                                'desktop_right' => 'Desktop Right Sidebar Ad',
                                'desktop_left' => 'Desktop Left Sidebar Ad',
                                'sticky_footer' => 'Sticky Footer Banner',
                            ])
                            ->required()
                            ->helperText('Select one or more slots where this banner will appear.'),

                        Forms\Components\Select::make('type')
                            ->options([
                                'image_link' => 'Image + Target Link',
                                'html_script' => 'Custom HTML / Ad Network Script',
                                'telegram_cta' => 'Telegram Channel CTA',
                            ])
                            ->default('image_link')
                            ->reactive()
                            ->required(),

                        Forms\Components\FileUpload::make('image_path')
                            ->label('Upload Banner Image')
                            ->disk('public')
                            ->directory('ad-banners')
                            ->visibility('public')
                            ->image()
                            ->maxSize(512) // Maximum 500 KB (512 KB)
                            ->helperText('Strict maximum size: 500 KB. Ideal dimensions: 728x90 (Desktop Top), 320x50 (Mobile Top), or 300x250 (In-feed).')
                            ->visible(fn (Get $get) => in_array($get('type'), ['image_link', 'telegram_cta'])),

                        Forms\Components\TextInput::make('image_url')
                            ->label('Or External Image URL')
                            ->url()
                            ->placeholder('https://...')
                            ->visible(fn (Get $get) => in_array($get('type'), ['image_link', 'telegram_cta'])),

                        Forms\Components\TextInput::make('target_url')
                            ->label('Target / Affiliate URL')
                            ->url()
                            ->placeholder('https://...')
                            ->visible(fn (Get $get) => in_array($get('type'), ['image_link', 'telegram_cta'])),

                        Forms\Components\Textarea::make('script_code')
                            ->label('HTML / JavaScript Ad Tag')
                            ->rows(5)
                            ->columnSpanFull()
                            ->visible(fn (Get $get) => $get('type') === 'html_script'),

                        Forms\Components\TextInput::make('clicks_count')
                            ->label('Total Tracked Clicks')
                            ->numeric()
                            ->default(0)
                            ->disabled(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active / Visible')
                            ->default(true),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('image_path')
                    ->label('Banner')
                    ->disk('public')
                    ->visibility('public')
                    ->state(fn (AdBanner $record): ?string => $record->resolved_image_url)
                    ->defaultImageUrl(fn (AdBanner $record): ?string => $record->resolved_image_url),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('placement')
                    ->badge()
                    ->separator(',')
                    ->color(fn ($state): string => match ($state) {
                        'news_top', 'home_top' => 'warning',
                        'news_in_feed' => 'info',
                        'header' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('type')
                    ->badge(),

                Tables\Columns\TextColumn::make('clicks_count')
                    ->label('Clicks')
                    ->sortable(),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('placement')
                    ->options([
                        'news_top' => 'News Feed Top',
                        'news_in_feed' => 'News In-Feed Slot',
                        'news_sidebar' => 'News Detail Sidebar Ad',
                        'schedule_sidebar' => 'Schedule Page Sidebar Ad',
                        'match_sidebar' => 'Match Detail Sidebar Ad',
                        'upcoming_match' => 'Upcoming Match Screen',
                        'home_top' => 'Home Page Top Banner',
                        'header' => 'Header Banner',
                        'pinned_match_below' => 'Below Pinned Match',
                        'match_detail_odds' => 'Match Detail Odds Widget',
                        'sidebar' => 'Sidebar Banner',
                        'desktop_right' => 'Desktop Right Sidebar Ad',
                        'desktop_left' => 'Desktop Left Sidebar Ad',
                        'sticky_footer' => 'Sticky Footer Banner',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        if (empty($data['value'])) {
                            return $query;
                        }

                        $val = $data['value'];
                        return $query->where(function (Builder $q) use ($val) {
                            $q->where('placement', $val)
                              ->orWhere('placement', 'LIKE', '%"' . $val . '"%')
                              ->orWhere('placement', 'LIKE', '%' . $val . '%');
                        });
                    }),
                Tables\Filters\TernaryFilter::make('is_active'),
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
            'index' => Pages\ListAdBanners::route('/'),
            'create' => Pages\CreateAdBanner::route('/create'),
            'edit' => Pages\EditAdBanner::route('/{record}/edit'),
        ];
    }
}
