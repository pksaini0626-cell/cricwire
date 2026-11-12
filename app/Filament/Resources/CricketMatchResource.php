<?php

namespace App\Filament\Resources;

use App\Filament\Resources\CricketMatchResource\Pages;
use App\Models\CricketMatch;
use App\Models\Series;
use App\Models\Team;
use Filament\Forms;
use Filament\Forms\Form;
use Filament\Resources\Resource;
use Filament\Tables;
use Filament\Tables\Table;

class CricketMatchResource extends Resource
{
    protected static ?string $model = CricketMatch::class;

    protected static ?string $navigationIcon = 'heroicon-o-fire';
    protected static ?string $navigationLabel = 'Matches';
    protected static ?string $navigationGroup = 'Cricket Management';

    public static function form(Form $form): Form
    {
        return $form
            ->schema([
                Forms\Components\Section::make('Match Overview')
                    ->schema([
                        Forms\Components\TextInput::make('api_match_id')
                            ->label('API Match ID')
                            ->required()
                            ->maxLength(255),
                        Forms\Components\Select::make('series_id')
                            ->label('Series / Tournament')
                            ->relationship('series', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('team_a_id')
                            ->label('Team A')
                            ->relationship('teamA', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\Select::make('team_b_id')
                            ->label('Team B')
                            ->relationship('teamB', 'name')
                            ->searchable()
                            ->preload()
                            ->required(),
                        Forms\Components\TextInput::make('match_title')
                            ->placeholder('e.g. Match 4, Group A')
                            ->required(),
                        Forms\Components\TextInput::make('slug')
                            ->unique(ignoreRecord: true)
                            ->required(),
                        Forms\Components\Select::make('format')
                            ->options([
                                'T20' => 'T20',
                                'ODI' => 'ODI',
                                'TEST' => 'TEST',
                                'T10' => 'T10',
                                'SIXES' => 'SIXES',
                            ])
                            ->required(),
                        Forms\Components\TextInput::make('venue')
                            ->required(),
                        Forms\Components\DateTimePicker::make('start_time')
                            ->required(),
                        Forms\Components\Select::make('status')
                            ->options([
                                'upcoming' => 'Upcoming',
                                'live' => 'Live',
                                'stumps' => 'Stumps',
                                'finished' => 'Finished',
                                'abandoned' => 'Abandoned',
                            ])
                            ->required(),
                        Forms\Components\Toggle::make('is_pinned')
                            ->label('Pin as Hot Match (Homepage Top)')
                            ->default(false),
                    ])->columns(2),

                Forms\Components\Section::make('Live Info & Results')
                    ->schema([
                        Forms\Components\TextInput::make('toss_info')
                            ->placeholder('e.g. IND won toss and elected to bat')
                            ->maxLength(255),
                        Forms\Components\TextInput::make('result_summary')
                            ->placeholder('e.g. IND won by 5 wickets')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('scorecard_json')
                            ->label('Scorecard Data (JSON)')
                            ->rows(5)
                            ->columnSpanFull(),
                    ]),

                Forms\Components\Section::make('SEO Metadata')
                    ->schema([
                        Forms\Components\TextInput::make('seo_title')
                            ->placeholder('Custom Page SEO Title')
                            ->maxLength(255),
                        Forms\Components\Textarea::make('seo_description')
                            ->placeholder('Meta Description for Google search')
                            ->rows(3),
                    ]),
            ]);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                Tables\Columns\IconColumn::make('is_pinned')
                    ->label('Pinned')
                    ->boolean()
                    ->sortable(),
                Tables\Columns\TextColumn::make('match_title')
                    ->searchable()
                    ->sortable(),
                Tables\Columns\TextColumn::make('teamA.short_name')
                    ->label('Team A')
                    ->sortable(),
                Tables\Columns\TextColumn::make('teamB.short_name')
                    ->label('Team B')
                    ->sortable(),
                Tables\Columns\TextColumn::make('format')
                    ->badge(),
                Tables\Columns\TextColumn::make('status')
                    ->badge()
                    ->color(fn (string $state): string => match ($state) {
                        'live' => 'danger',
                        'upcoming' => 'info',
                        'finished' => 'success',
                        default => 'gray',
                    }),
                Tables\Columns\TextColumn::make('start_time')
                    ->dateTime()
                    ->sortable(),
            ])
            ->filters([
                Tables\Filters\SelectFilter::make('status')
                    ->options([
                        'upcoming' => 'Upcoming',
                        'live' => 'Live',
                        'finished' => 'Finished',
                    ]),
                Tables\Filters\TernaryFilter::make('is_pinned')
                    ->label('Pinned / Hot Matches'),
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
            'index' => Pages\ListCricketMatches::route('/'),
            'create' => Pages\CreateCricketMatch::route('/create'),
            'edit' => Pages\EditCricketMatch::route('/{record}/edit'),
        ];
    }
}
