<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ManagedMatchResource\Pages;
use App\Models\ManagedMatch;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Collection;

class ManagedMatchResource extends Resource
{
    protected static ?string $model = ManagedMatch::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static ?string $navigationLabel = 'Managed Matches';
    protected static ?string $navigationGroup = 'Cricket Management';
    protected static ?int $navigationSort = 1;

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Match Information')
                    ->schema([
                        Forms\Components\TextInput::make('match_id')
                            ->label('Match ID')
                            ->required()
                            ->numeric()
                            ->disabledOn('edit'),
                        Forms\Components\TextInput::make('series_name')
                            ->label('Series / Tournament')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('match_desc')
                            ->label('Match Description')
                            ->maxLength(255),
                        Forms\Components\Select::make('format')
                            ->options([
                                'T20' => 'T20',
                                'ODI' => 'ODI',
                                'Test' => 'Test',
                            ])
                            ->default('ODI'),
                        Forms\Components\TextInput::make('team_a')
                            ->label('Team A')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('team_b')
                            ->label('Team B')
                            ->maxLength(255),
                        Forms\Components\Select::make('state')
                            ->options([
                                'Live' => 'Live',
                                'Scheduled' => 'Scheduled',
                                'Complete' => 'Complete',
                            ])
                            ->default('Scheduled'),
                        Forms\Components\TextInput::make('start_time')
                            ->label('Start Time / Date')
                            ->maxLength(255),
                        Forms\Components\Toggle::make('is_covered')
                            ->label('Cover / Show Match')
                            ->helperText('When turned off, this match is skipped from live polling and hidden on the frontend.')
                            ->default(true),
                        Forms\Components\Toggle::make('is_featured')
                            ->label('Featured Match')
                            ->default(false),
                    ])->columns(2),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('match_id')
                    ->label('Match ID')
                    ->sortable()
                    ->searchable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('series_name')
                    ->label('Series')
                    ->limit(25)
                    ->searchable(),

                Tables\Columns\TextColumn::make('teams')
                    ->label('Match')
                    ->state(fn (ManagedMatch $record): string => "{$record->team_a} vs {$record->team_b}")
                    ->searchable(['team_a', 'team_b']),

                Tables\Columns\BadgeColumn::make('format')
                    ->label('Format')
                    ->colors([
                        'warning' => 'T20',
                        'info' => 'ODI',
                        'success' => 'Test',
                    ]),

                Tables\Columns\BadgeColumn::make('state')
                    ->label('State')
                    ->colors([
                        'danger' => 'Live',
                        'warning' => 'Scheduled',
                        'gray' => 'Complete',
                    ]),

                Tables\Columns\ToggleColumn::make('is_covered')
                    ->label('Cover / Show Match')
                    ->sortable(),

                Tables\Columns\ToggleColumn::make('is_featured')
                    ->label('Featured')
                    ->sortable(),

                Tables\Columns\TextColumn::make('start_time')
                    ->label('Start Time')
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('id', 'desc')
            ->filters([
                Tables\Filters\SelectFilter::make('state')
                    ->options([
                        'Live' => 'Live',
                        'Scheduled' => 'Scheduled',
                        'Complete' => 'Complete',
                    ]),
                Tables\Filters\SelectFilter::make('format')
                    ->options([
                        'T20' => 'T20',
                        'ODI' => 'ODI',
                        'Test' => 'Test',
                    ]),
                Tables\Filters\TernaryFilter::make('is_covered')
                    ->label('Coverage Status'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
            ])
            ->bulkActions([
                Tables\Actions\BulkActionGroup::make([
                    Tables\Actions\BulkAction::make('coverSelected')
                        ->label('Cover Selected Matches')
                        ->icon('heroicon-o-check-circle')
                        ->color('success')
                        ->action(function (Collection $records) {
                            $records->each->update(['is_covered' => true]);
                            Notification::make()
                                ->title('Selected matches are now covered')
                                ->success()
                                ->send();
                        }),

                    Tables\Actions\BulkAction::make('skipSelected')
                        ->label('Skip Selected Matches')
                        ->icon('heroicon-o-x-circle')
                        ->color('danger')
                        ->action(function (Collection $records) {
                            $records->each->update(['is_covered' => false]);
                            Notification::make()
                                ->title('Selected matches skipped from polling & frontend')
                                ->warning()
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
            'index' => Pages\ListManagedMatches::route('/'),
            'create' => Pages\CreateManagedMatch::route('/create'),
            'edit' => Pages\EditManagedMatch::route('/{record}/edit'),
        ];
    }
}
