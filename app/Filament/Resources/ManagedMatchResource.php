<?php

namespace App\Filament\Resources;

use App\Filament\Resources\ManagedMatchResource\Pages;
use App\Jobs\SendPushNotificationJob;
use App\Models\ManagedMatch;
use App\Models\PushNotification;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Str;

class ManagedMatchResource extends Resource
{
    protected static ?string $model = ManagedMatch::class;

    protected static ?string $navigationIcon = 'heroicon-o-adjustments-horizontal';
    protected static ?string $navigationLabel = 'Managed Matches';
    protected static ?string $navigationGroup = 'Cricket Management';
    protected static ?int $navigationSort = 1;

    /**
     * Scope query to strictly Live and Upcoming matches.
     * Order by Live first, then start_time ascending.
     */
    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->whereNotIn('state', ['Complete', 'Completed', 'Finished', 'Result'])
            ->orderByRaw("CASE WHEN LOWER(state) LIKE '%live%' OR LOWER(state) LIKE '%progress%' THEN 0 ELSE 1 END")
            ->orderBy('start_time', 'asc')
            ->orderBy('id', 'desc');
    }

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
                                'Preview' => 'Preview',
                            ])
                            ->default('Scheduled'),
                        Forms\Components\TextInput::make('start_time')
                            ->label('Start Time / Date')
                            ->maxLength(255),
                        Forms\Components\Toggle::make('is_covered')
                            ->label('Cover Match')
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
                    ->wrap()
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('teams')
                    ->label('Match')
                    ->state(fn (ManagedMatch $record): string => "{$record->team_a} vs {$record->team_b}" . ($record->match_desc ? " ({$record->match_desc})" : ''))
                    ->searchable(['team_a', 'team_b', 'match_desc']),

                Tables\Columns\TextColumn::make('format')
                    ->label('Format')
                    ->badge()
                    ->color(fn (string $state): string => match (strtoupper($state)) {
                        'T20' => 'warning',
                        'ODI' => 'info',
                        'TEST' => 'success',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('state')
                    ->label('State')
                    ->badge()
                    ->color(fn (string $state): string => match (strtolower($state)) {
                        'live', 'in progress' => 'success',
                        'scheduled', 'preview', 'upcoming' => 'warning',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('start_time')
                    ->label('Start Time')
                    ->sortable()
                    ->searchable(),

                Tables\Columns\ToggleColumn::make('is_covered')
                    ->label('Cover Match')
                    ->sortable(),

                Tables\Columns\ToggleColumn::make('is_featured')
                    ->label('Featured')
                    ->toggleable(isToggledHiddenByDefault: true)
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('state')
                    ->options([
                        'Live' => 'Live',
                        'Scheduled' => 'Scheduled',
                    ]),
                Tables\Filters\SelectFilter::make('format')
                    ->options([
                        'T20' => 'T20',
                        'ODI' => 'ODI',
                        'Test' => 'Test',
                    ]),
                Tables\Filters\TernaryFilter::make('is_covered')
                    ->label('Match Covered'),
            ])
            ->actions([
                Tables\Actions\Action::make('notifyUsers')
                    ->label('Notify')
                    ->icon('heroicon-o-bell')
                    ->color('warning')
                    ->modalHeading(fn (ManagedMatch $record) => "📢 Push Notification: {$record->team_a} vs {$record->team_b}")
                    ->modalSubmitActionLabel('🚀 Dispatch Notification')
                    ->form([
                        Forms\Components\Select::make('template')
                            ->label('Template')
                            ->options([
                                'starting_soon' => '⏰ Match Starting Soon (15 Mins)',
                                'toss'          => '🪙 Toss Update',
                                'live_thriller' => '🔥 Live Thriller Alert',
                                'custom'        => '✍️ Custom Message',
                            ])
                            ->default('starting_soon')
                            ->live()
                            ->afterStateUpdated(function (Forms\Get $get, Forms\Set $set, ?string $state, ManagedMatch $record) {
                                $teamA = $record->team_a ?? 'Team A';
                                $teamB = $record->team_b ?? 'Team B';
                                $series = $record->series_name ?? 'Series';
                                $toss = $record->toss_text ?? "Toss conducted for {$teamA} vs {$teamB}";

                                match ($state) {
                                    'starting_soon' => [
                                        $set('title', "⏰ Starting Soon: {$teamA} vs {$teamB}"),
                                        $set('body', "Match begins in 15 minutes! Tap to follow live ball-by-ball scores & odds on Cricwire."),
                                    ],
                                    'toss' => [
                                        $set('title', "🪙 Toss: {$teamA} vs {$teamB}"),
                                        $set('body', $toss ? "{$toss}. Tap for playing XIs and live match updates!" : "Toss completed for {$teamA} vs {$teamB}!"),
                                    ],
                                    'live_thriller' => [
                                        $set('title', "🔥 Thriller Alert: {$teamA} vs {$teamB}"),
                                        $set('body', "Match is in an intense nail-biting phase! Watch live scores & real-time line commentary now!"),
                                    ],
                                    default => [
                                        $set('title', "🏏 {$teamA} vs {$teamB}"),
                                        $set('body', "Live match updates from {$series}."),
                                    ],
                                };
                            }),

                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(100)
                            ->default(fn (ManagedMatch $record) => "⏰ Starting Soon: {$record->team_a} vs {$record->team_b}"),

                        Forms\Components\Textarea::make('body')
                            ->required()
                            ->rows(3)
                            ->maxLength(250)
                            ->default('Match begins in 15 minutes! Tap to follow live ball-by-ball scores & odds on Cricwire.'),

                        Forms\Components\TextInput::make('target_url')
                            ->label('Target URL')
                            ->required()
                            ->default(function (ManagedMatch $record) {
                                $seriesSlug = Str::slug($record->series_name ?: 'series');
                                $matchSlug = Str::slug(($record->team_a ?: 'team-a') . '-vs-' . ($record->team_b ?: 'team-b') . '-' . $record->match_id);
                                return "/match-detail/{$seriesSlug}/live-line/{$matchSlug}";
                            }),

                        Forms\Components\Select::make('audience')
                            ->options([
                                'all'       => 'All Users (Web + App)',
                                'logged_in' => 'Logged-in Users Only',
                            ])
                            ->default('all')
                            ->required(),
                    ])
                    ->action(function (ManagedMatch $record, array $data) {
                        $notification = PushNotification::create([
                            'title'      => $data['title'],
                            'body'       => $data['body'],
                            'type'       => 'match',
                            'target_url' => $data['target_url'],
                            'audience'   => $data['audience'] ?? 'all',
                            'status'     => 'queued',
                            'created_by' => auth()->id(),
                        ]);

                        SendPushNotificationJob::dispatch($notification->id);

                        Notification::make()
                            ->title('Match notification dispatched successfully!')
                            ->success()
                            ->send();
                    }),

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
                        ->color('warning')
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
