<?php

namespace App\Filament\Resources;

use App\Filament\Resources\PollResource\Pages;
use App\Models\Poll;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class PollResource extends Resource
{
    protected static ?string $model = Poll::class;

    protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
    protected static ?string $navigationLabel = 'Prediction Polls';
    protected static ?string $navigationGroup = 'Fan Engagement';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Live Match Poll')
                    ->schema([
                        Forms\Components\Select::make('match_id')
                            ->label('Target Live Match')
                            ->relationship('match', 'match_title')
                            ->searchable()
                            ->preload()
                            ->nullable(),
                        Forms\Components\TextInput::make('question')
                            ->placeholder('e.g. Who will win today\'s match?')
                            ->required()
                            ->columnSpanFull(),
                        Forms\Components\TextInput::make('option_a')
                            ->label('Option A')
                            ->required(),
                        Forms\Components\TextInput::make('option_b')
                            ->label('Option B')
                            ->required(),
                        Forms\Components\TextInput::make('option_c')
                            ->label('Option C (Optional)')
                            ->nullable(),
                        Forms\Components\DateTimePicker::make('expires_at')
                            ->label('Expires At')
                            ->default(now()->addHours(6))
                            ->required(),
                        Forms\Components\Toggle::make('is_active')
                            ->label('Active for voting')
                            ->default(true),
                        Forms\Components\Toggle::make('send_push_notification')
                            ->label('🔔 Send Push Notification to All Users')
                            ->helperText('Dispatches push notification broadcast on save')
                            ->default(false)
                            ->dehydrated(false),
                    ])->columns(2),

                Forms\Components\Section::make('Live Vote Tallies')
                    ->schema([
                        Forms\Components\TextInput::make('votes_a')->numeric()->disabled(),
                        Forms\Components\TextInput::make('votes_b')->numeric()->disabled(),
                        Forms\Components\TextInput::make('votes_c')->numeric()->disabled(),
                    ])->columns(3),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\TextColumn::make('question')
                    ->limit(50)
                    ->searchable(),
                Tables\Columns\TextColumn::make('match.match_title')
                    ->label('Match')
                    ->placeholder('Global Poll'),
                Tables\Columns\TextColumn::make('votes_a')
                    ->label('Opt A Votes')
                    ->sortable(),
                Tables\Columns\TextColumn::make('votes_b')
                    ->label('Opt B Votes')
                    ->sortable(),
                Tables\Columns\IconColumn::make('is_active')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('expires_at')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\TernaryFilter::make('is_active'),
            ])
            ->actions([
                Tables\Actions\EditAction::make(),
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
            'index' => Pages\ListPolls::route('/'),
            'create' => Pages\CreatePoll::route('/create'),
            'edit' => Pages\EditPoll::route('/{record}/edit'),
        ];
    }
}
