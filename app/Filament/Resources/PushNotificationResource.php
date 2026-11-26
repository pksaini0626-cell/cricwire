<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PushNotificationResource\Pages;
use App\Jobs\SendPushNotificationJob;
use App\Models\PushNotification;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PushNotificationResource extends Resource
{
    protected static ?string $model = PushNotification::class;

    protected static ?string $navigationGroup = 'Engagement';

    protected static ?string $navigationIcon = 'heroicon-o-bell-alert';

    protected static ?string $navigationLabel = 'Push Notifications';

    protected static ?string $modelLabel = 'Push Notification';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Notification Content')
                    ->schema([
                        Forms\Components\TextInput::make('title')
                            ->required()
                            ->maxLength(100)
                            ->placeholder('🪙 Toss: IND won the toss & elected to bat'),

                        Forms\Components\Textarea::make('body')
                            ->required()
                            ->rows(3)
                            ->maxLength(250)
                            ->placeholder('Brief notification description...'),

                        Forms\Components\Select::make('type')
                            ->options([
                                'general' => 'General',
                                'match'   => 'Match Alert',
                                'poll'    => 'Poll / Vote',
                                'news'    => 'Breaking News',
                            ])
                            ->default('general')
                            ->required(),

                        Forms\Components\TextInput::make('target_url')
                            ->nullable()
                            ->helperText('Relative path like /polls or full URL e.g. /match-detail/ipl-2025/live-line/csk-vs-mi'),

                        Forms\Components\FileUpload::make('image_url')
                            ->label('Banner Image')
                            ->disk('public')
                            ->directory('push-banners')
                            ->image()
                            ->maxSize(512),

                        Forms\Components\Select::make('audience')
                            ->options([
                                'all'       => 'All Users (Web + App)',
                                'logged_in' => 'Logged-in Users Only',
                            ])
                            ->default('all')
                            ->required(),

                        Forms\Components\Toggle::make('send_immediately')
                            ->label('🚀 Send Immediately on Creation')
                            ->default(true)
                            ->dehydrated(false)
                            ->visibleOn('create')
                            ->helperText('Dispatches FCM multicast push job right after saving.'),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('title')
                    ->limit(40)
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('type')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'match' => 'warning',
                        'news' => 'danger',
                        'poll' => 'info',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'sent' => 'success',
                        'queued' => 'warning',
                        'failed' => 'danger',
                        default => 'gray',
                    }),

                Tables\Columns\TextColumn::make('total_targeted')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('sent_count')
                    ->numeric()
                    ->color('success')
                    ->sortable(),

                Tables\Columns\TextColumn::make('failed_count')
                    ->numeric()
                    ->color('danger')
                    ->sortable(),

                Tables\Columns\TextColumn::make('sent_at')
                    ->dateTime('M d, Y H:i')
                    ->sortable(),
            ])
            ->defaultSort('id', 'desc')
            ->actions([
                Tables\Actions\Action::make('sendNow')
                    ->label('🚀 Send Now')
                    ->icon('heroicon-o-paper-airplane')
                    ->color('success')
                    ->visible(fn (PushNotification $record): bool => in_array($record->status, ['draft', 'queued', 'failed']))
                    ->requiresConfirmation()
                    ->modalHeading('Send Push Notification')
                    ->modalDescription('Are you sure you want to dispatch this push notification to all targeted users?')
                    ->action(function (PushNotification $record) {
                        $record->update(['status' => 'queued']);
                        SendPushNotificationJob::dispatch($record->id);

                        Notification::make()
                            ->title('Push notification queued for delivery')
                            ->success()
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
            'index' => Pages\ListPushNotifications::route('/'),
            'create' => Pages\CreatePushNotification::route('/create'),
            'edit' => Pages\EditPushNotification::route('/{record}/edit'),
        ];
    }
}
