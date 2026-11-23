<?php

namespace App\Filament\Resources;

use App\Filament\Resources\SeriesCoverageResource\Pages;
use App\Models\SkippedSeries;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class SeriesCoverageResource extends Resource
{
    protected static ?string $model = SkippedSeries::class;

    protected static ?string $navigationGroup = 'Cricket Engine';

    protected static ?string $navigationLabel = 'Series Manager';

    protected static ?string $modelLabel = 'Series';

    protected static ?string $pluralModelLabel = 'Series Manager';

    protected static ?string $navigationIcon = 'heroicon-o-trophy';

    protected static ?int $navigationSort = 15;

    public static function getNavigationBadge(): ?string
    {
        $count = SkippedSeries::where('is_skipped', false)->count();
        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'success';
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\TextInput::make('series_name')
                    ->label('Series Name')
                    ->required()
                    ->maxLength(255),

                Forms\Components\TextInput::make('series_id')
                    ->label('Series ID')
                    ->maxLength(255),

                Forms\Components\TextInput::make('series_slug')
                    ->label('Series Slug')
                    ->disabled()
                    ->dehydrated(false),

                Forms\Components\Select::make('format')
                    ->label('Format')
                    ->options([
                        'T20' => 'T20',
                        'ODI' => 'ODI',
                        'TEST' => 'TEST',
                        'MIXED' => 'MIXED',
                    ]),

                Forms\Components\Toggle::make('is_skipped')
                    ->label('Skip Series')
                    ->helperText('When enabled, this series and all its matches are hidden from frontend feeds.')
                    ->default(false),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('series_name')
                    ->label('Series Name')
                    ->weight('bold')
                    ->searchable()
                    ->sortable()
                    ->description(fn (SkippedSeries $record): ?string => $record->series_id ? "ID: {$record->series_id}" : null),

                Tables\Columns\TextColumn::make('format')
                    ->label('Format')
                    ->badge()
                    ->color(fn (?string $state): string => match (strtoupper($state ?? '')) {
                        'T20' => 'warning',
                        'ODI' => 'info',
                        'TEST' => 'success',
                        'MIXED' => 'purple',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('live_matches_count')
                    ->label('Live')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'success' : 'gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('upcoming_matches_count')
                    ->label('Upcoming')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'warning' : 'gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('recent_matches_count')
                    ->label('Recent')
                    ->badge()
                    ->color(fn (int $state): string => $state > 0 ? 'info' : 'gray')
                    ->sortable(),

                Tables\Columns\TextColumn::make('status')
                    ->label('Status')
                    ->badge()
                    ->state(fn (SkippedSeries $record): string => $record->is_skipped ? 'SKIPPED' : 'COVERED')
                    ->color(fn (string $state): string => $state === 'COVERED' ? 'success' : 'danger'),

                Tables\Columns\ToggleColumn::make('is_skipped')
                    ->label('Skip Series')
                    ->onColor('danger')
                    ->offColor('success')
                    ->sortable(),
            ])
            ->defaultSort('live_matches_count', 'desc')
            ->filters([
                Tables\Filters\TernaryFilter::make('is_skipped')
                    ->label('Coverage Status')
                    ->placeholder('All Series')
                    ->trueLabel('Skipped Series')
                    ->falseLabel('Covered Series'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('skipSelectedSeries')
                        ->label('🚫 Skip Selected Series')
                        ->icon('heroicon-o-no-symbol')
                        ->color('danger')
                        ->requiresConfirmation()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            foreach ($records as $record) {
                                $record->update(['is_skipped' => true]);
                            }
                            \Filament\Notifications\Notification::make()
                                ->title('Selected series marked as skipped')
                                ->danger()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('coverSelectedSeries')
                        ->label('✅ Cover Selected Series')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->requiresConfirmation()
                        ->action(function (\Illuminate\Database\Eloquent\Collection $records) {
                            foreach ($records as $record) {
                                $record->update(['is_skipped' => false]);
                            }
                            \Filament\Notifications\Notification::make()
                                ->title('Selected series marked as covered')
                                ->success()
                                ->send();
                        }),

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
            'index' => Pages\ListSeriesCoverages::route('/'),
        ];
    }
}
