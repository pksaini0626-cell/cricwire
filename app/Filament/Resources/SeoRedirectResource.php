<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SeoRedirectResource\Pages;
use App\Models\SeoRedirect;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SeoRedirectResource extends Resource
{
    protected static ?string $model = SeoRedirect::class;

    protected static ?string $navigationIcon = 'heroicon-o-arrows-right-left';
    protected static ?string $navigationLabel = 'URL Redirects (301/302)';
    protected static ?string $navigationGroup = 'Technical SEO';
    protected static ?int $navigationSort = 2;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Redirect Rule Details')
                    ->description('Set up instantaneous 301 Permanent or 302 Temporary redirects for legacy or modified URLs.')
                    ->schema([
                        Forms\Components\TextInput::make('old_path')
                            ->label('Source URL / Path')
                            ->placeholder('/live-cricket-score/12345 or /old-news-slug')
                            ->required()
                            ->maxLength(255)
                            ->unique(ignoreRecord: true)
                            ->helperText('Relative URL path starting with a slash.'),

                        Forms\Components\TextInput::make('new_path')
                            ->label('Target Destination URL')
                            ->placeholder('/match-detail/ipl-2026/live-line/csk-vs-mi-12345')
                            ->required()
                            ->maxLength(255)
                            ->helperText('Target relative path or absolute URL.'),

                        Forms\Components\Select::make('status_code')
                            ->label('HTTP Status Code')
                            ->options([
                                301 => '301 - Permanent Redirect (Passes SEO Link Equity)',
                                302 => '302 - Temporary Redirect (Found)',
                            ])
                            ->default(301)
                            ->required(),

                        Forms\Components\Toggle::make('is_active')
                            ->label('Active Rule')
                            ->default(true)
                            ->helperText('Disable rule temporarily without deleting it.'),

                        Forms\Components\TextInput::make('hits_count')
                            ->label('Total Hits Count')
                            ->disabled()
                            ->dehydrated(false)
                            ->default(0),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('old_path')
                    ->label('Old Path')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->weight('bold'),

                Tables\Columns\TextColumn::make('new_path')
                    ->label('Destination')
                    ->searchable()
                    ->sortable()
                    ->copyable()
                    ->color('info'),

                Tables\Columns\BadgeColumn::make('status_code')
                    ->label('Status')
                    ->colors([
                        'success' => 301,
                        'warning' => 302,
                    ])
                    ->sortable(),

                Tables\Columns\TextColumn::make('hits_count')
                    ->label('Hits')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\ToggleColumn::make('is_active')
                    ->label('Active'),

                Tables\Columns\TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status_code')
                    ->options([
                        301 => '301 Permanent',
                        302 => '302 Temporary',
                    ]),
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
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListSeoRedirects::route('/'),
            'create' => Pages\CreateSeoRedirect::route('/create'),
            'edit' => Pages\EditSeoRedirect::route('/{record}/edit'),
        ];
    }
}
