<?php

namespace App\Filament\Resources;

use App\Filament\Resources\UserResource\Pages;
use App\Models\User;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;

class UserResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $navigationIcon = 'heroicon-o-users';

    protected static ?string $navigationGroup = 'Users & Engagement';

    protected static ?string $navigationLabel = 'Users';

    protected static ?string $modelLabel = 'User';

    protected static ?string $pluralModelLabel = 'Users';

    public static function canCreate(): bool
    {
        return false;
    }

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('User Details')
                    ->columns(2)
                    ->schema([
                        Forms\Components\TextInput::make('name')
                            ->disabled(),
                        Forms\Components\TextInput::make('email')
                            ->email()
                            ->disabled(),
                        Forms\Components\TextInput::make('role')
                            ->disabled(),
                        Forms\Components\TextInput::make('google_id')
                            ->label('Google ID')
                            ->placeholder('Standard Account')
                            ->disabled(),
                        Forms\Components\TextInput::make('player_code')
                            ->label('Player ID')
                            ->disabled(),
                        Forms\Components\TextInput::make('cric_gems')
                            ->label('CricGems Balance')
                            ->disabled(),
                        Forms\Components\TextInput::make('login_streak')
                            ->label('Login Streak (Days)')
                            ->disabled(),
                        Forms\Components\TextInput::make('battles_won')
                            ->label('Battles Won')
                            ->disabled(),
                        Forms\Components\TextInput::make('battles_played')
                            ->label('Battles Played')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('email_verified_at')
                            ->label('Email Verified At')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('last_login_at')
                            ->label('Last Login At')
                            ->disabled(),
                        Forms\Components\DateTimePicker::make('created_at')
                            ->label('Registered At')
                            ->disabled(),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\ImageColumn::make('avatar')
                    ->label('Avatar')
                    ->circular()
                    ->disk(fn (?User $record): ?string => $record && str_starts_with((string) $record->avatar, 'http') ? null : 'public')
                    ->defaultImageUrl(fn (?User $record): string => 'https://ui-avatars.com/api/?name=' . urlencode($record?->name ?? 'U') . '&color=7F9CF5&background=EBF4FF'),

                Tables\Columns\TextColumn::make('player_code')
                    ->label('Player ID')
                    ->badge()
                    ->color('warning')
                    ->searchable()
                    ->copyable()
                    ->placeholder('—'),

                Tables\Columns\TextColumn::make('name')
                    ->searchable()
                    ->sortable(),

                Tables\Columns\TextColumn::make('email')
                    ->searchable()
                    ->sortable()
                    ->copyable(),

                Tables\Columns\TextColumn::make('cric_gems')
                    ->label('CricGems')
                    ->badge()
                    ->color('success')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('login_streak')
                    ->label('Streak')
                    ->numeric()
                    ->suffix('d')
                    ->sortable(),

                Tables\Columns\TextColumn::make('battles_won')
                    ->label('Wins')
                    ->numeric()
                    ->sortable(),

                Tables\Columns\TextColumn::make('login_provider')
                    ->label('Provider')
                    ->badge()
                    ->getStateUsing(fn (User $record): string => filled($record->google_id) ? 'Google' : 'Standard')
                    ->color(fn (string $state): string => $state === 'Google' ? 'success' : 'info'),

                Tables\Columns\TextColumn::make('last_login_at')
                    ->label('Last Login')
                    ->dateTime()
                    ->since()
                    ->sortable()
                    ->placeholder('Never'),

                Tables\Columns\TextColumn::make('created_at')
                    ->label('Registered')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->defaultSort('created_at', 'desc')
            ->filters([
                SelectFilter::make('login_provider')
                    ->label('Login Provider')
                    ->options([
                        'google' => 'Google',
                        'standard' => 'Standard',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        return match ($data['value'] ?? null) {
                            'google' => $query->whereNotNull('google_id'),
                            'standard' => $query->whereNull('google_id'),
                            default => $query,
                        };
                    }),
            ])
            ->actions([
                Tables\Actions\ViewAction::make(),
            ])
            ->bulkActions([]);
    }

    public static function getRelations(): array
    {
        return [];
    }

    public static function getPages(): array
    {
        return [
            'index' => Pages\ListUsers::route('/'),
            'view' => Pages\ViewUser::route('/{record}'),
        ];
    }
}
