<?php

namespace App\Filament\Resources;

use App\Filament\Resources\NewsApiConfigResource\Pages;
use App\Models\NewsApiConfig;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class NewsApiConfigResource extends Resource
{
    protected static ?string $model = NewsApiConfig::class;

    protected static ?string $navigationIcon = 'heroicon-o-cloud-arrow-down';
    protected static ?string $navigationLabel = 'News API Configs';
    protected static ?string $navigationGroup = 'Editorial';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('API Endpoint Configuration')
                    ->description('Configure external cricket news feeds and upstream authentication.')
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->label('Provider Name')
                            ->placeholder('e.g. RapidAPI Live Line News, Cricbuzz Feed')
                            ->required()
                            ->maxLength(255),

                        Forms\Components\TextInput::make('endpoint_url')
                            ->label('Endpoint URL')
                            ->url()
                            ->placeholder('https://cricket-live-line1.p.rapidapi.com/news')
                            ->required()
                            ->maxLength(500),

                        Forms\Components\Select::make('auth_type')
                            ->label('Authentication Type')
                            ->options([
                                'header' => 'Header (e.g. x-rapidapi-key)',
                                'bearer' => 'Bearer Token',
                                'query_param' => 'Query Parameter (?api_key=...)',
                                'none' => 'None / Public Endpoint',
                            ])
                            ->default('header')
                            ->required()
                            ->reactive(),

                        Forms\Components\TextInput::make('api_key_header')
                            ->label('Header / Param Name')
                            ->placeholder('e.g. x-rapidapi-key')
                            ->visible(fn (Forms\Get $get) => in_array($get('auth_type'), ['header', 'query_param']))
                            ->maxLength(255),

                        Forms\Components\TextInput::make('api_key_value')
                            ->label('API Key / Secret Token')
                            ->password()
                            ->revealable()
                            ->maxLength(1000)
                            ->visible(fn (Forms\Get $get) => $get('auth_type') !== 'none'),

                        Forms\Components\KeyValue::make('additional_headers')
                            ->label('Additional HTTP Headers')
                            ->keyLabel('Header Name')
                            ->valueLabel('Header Value')
                            ->reorderable()
                            ->columnSpanFull()
                            ->helperText('Custom headers sent with every request, e.g. "x-rapidapi-host" => "cricket-live-line1.p.rapidapi.com"'),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active for Ingestion')
                            ->default(true)
                            ->inline(false),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('name')
                    ->label('Provider Name')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('endpoint_url')
                    ->label('Endpoint URL')
                    ->limit(45)
                    ->tooltip(fn (NewsApiConfig $record): string => $record->endpoint_url)
                    ->color('gray'),

                Tables\Columns\TextColumn::make('auth_type')
                    ->label('Auth Type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'header' => 'info',
                        'bearer' => 'success',
                        'query_param' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active'),

                Tables\Columns\TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: false),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active')
                    ->label('Active Status'),
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
            'index' => Pages\ListNewsApiConfigs::route('/'),
            'create' => Pages\CreateNewsApiConfig::route('/create'),
            'edit' => Pages\EditNewsApiConfig::route('/{record}/edit'),
        ];
    }
}

