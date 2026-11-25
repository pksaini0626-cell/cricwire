<?php

namespace App\Filament\Pages;

use App\Support\LiveEngineConfig;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class LiveEngineSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-bolt';
    protected static ?string $navigationLabel = 'System & App Settings';
    protected static ?string $navigationGroup = 'System';
    protected static ?int $navigationSort = 99;

    protected static string $view = 'filament.pages.live-engine-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $this->form->fill([
            'live_engine_enabled' => LiveEngineConfig::isEnabled(),
            'poll_interval_t20' => LiveEngineConfig::pollIntervalT20(),
            'poll_interval_odi' => LiveEngineConfig::pollIntervalOdi(),
            'poll_interval_test' => LiveEngineConfig::pollIntervalTest(),
            'daily_api_call_limit' => LiveEngineConfig::dailyApiCallLimit(),
            'show_market_rates_and_session' => LiveEngineConfig::isMarketRatesEnabled(),
            'polling_interval_seconds' => LiveEngineConfig::pollingInterval(),

            // Social media links & toggles
            'social_telegram_url' => LiveEngineConfig::get('social_telegram_url', 'https://t.me/cricwireofficial'),
            'social_telegram_enabled' => filter_var(LiveEngineConfig::get('social_telegram_enabled', '1'), FILTER_VALIDATE_BOOLEAN),

            'social_instagram_url' => LiveEngineConfig::get('social_instagram_url', 'https://instagram.com/cricwire.in'),
            'social_instagram_enabled' => filter_var(LiveEngineConfig::get('social_instagram_enabled', '0'), FILTER_VALIDATE_BOOLEAN),

            'social_twitter_url' => LiveEngineConfig::get('social_twitter_url', 'https://x.com/cricwire'),
            'social_twitter_enabled' => filter_var(LiveEngineConfig::get('social_twitter_enabled', '0'), FILTER_VALIDATE_BOOLEAN),

            'social_youtube_url' => LiveEngineConfig::get('social_youtube_url', 'https://www.youtube.com/@cricwire.official'),
            'social_youtube_enabled' => filter_var(LiveEngineConfig::get('social_youtube_enabled', '0'), FILTER_VALIDATE_BOOLEAN),

            'social_facebook_url' => LiveEngineConfig::get('social_facebook_url', 'https://facebook.com/cricwire'),
            'social_facebook_enabled' => filter_var(LiveEngineConfig::get('social_facebook_enabled', '0'), FILTER_VALIDATE_BOOLEAN),

            // Google Analytics 4
            'ga4_measurement_id' => LiveEngineConfig::get('ga4_measurement_id', ''),
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Section::make('App Store Review & Safety Controls')
                    ->description('Instantly hide/show market rates, match odds, and session metrics for Google Play Store compliance.')
                    ->schema([
                        Toggle::make('show_market_rates_and_session')
                            ->label('Show Market Rates & Session Odds')
                            ->helperText('Safe-Mode Review Toggle: When turned OFF, all market rates and session boxes are completely removed from the DOM so app store reviewers see zero odds content.')
                            ->default(true),
                    ]),

                Section::make('Google Analytics 4 (GA4) Tracking')
                    ->description('Configure your Google Analytics 4 Measurement ID to automatically inject gtag.js on public pages.')
                    ->schema([
                        TextInput::make('ga4_measurement_id')
                            ->label('GA4 Measurement ID')
                            ->placeholder('G-XXXXXXXXXX')
                            ->helperText('Enter your GA4 Measurement ID (e.g. G-XXXXXXXXXX). Leave empty to disable Google Analytics.')
                            ->maxLength(32)
                            ->nullable()
                            ->regex('/^G-[A-Za-z0-9]+$/')
                            ->validationMessages([
                                'regex' => 'GA4 Measurement ID must match format G-XXXXXXXXXX.',
                            ]),
                    ]),

                Section::make('Social Media Links & Follow Us')
                    ->description('Configure official social channels shown on the /more user hub. Only enabled channels with valid URLs are displayed.')
                    ->schema([
                        TextInput::make('social_telegram_url')
                            ->label('Telegram Channel URL')
                            ->url()
                            ->placeholder('https://t.me/cricwire'),
                        Toggle::make('social_telegram_enabled')
                            ->label('Enable Telegram Link')
                            ->default(true),

                        TextInput::make('social_instagram_url')
                            ->label('Instagram URL')
                            ->url()
                            ->placeholder('https://instagram.com/...'),
                        Toggle::make('social_instagram_enabled')
                            ->label('Enable Instagram Link')
                            ->default(false),

                        TextInput::make('social_twitter_url')
                            ->label('X (Twitter) URL')
                            ->url()
                            ->placeholder('https://x.com/...'),
                        Toggle::make('social_twitter_enabled')
                            ->label('Enable X Link')
                            ->default(false),

                        TextInput::make('social_youtube_url')
                            ->label('YouTube Channel URL')
                            ->url()
                            ->placeholder('https://youtube.com/@...'),
                        Toggle::make('social_youtube_enabled')
                            ->label('Enable YouTube Link')
                            ->default(false),

                        TextInput::make('social_facebook_url')
                            ->label('Facebook Page URL')
                            ->url()
                            ->placeholder('https://facebook.com/...'),
                        Toggle::make('social_facebook_enabled')
                            ->label('Enable Facebook Link')
                            ->default(false),
                    ])->columns(2),

                Section::make('Live Poller & Adaptive Engine Configuration')
                    ->description('Control background live polling behavior, format-wise delays, and daily API safety quotas.')
                    ->schema([
                        Toggle::make('live_engine_enabled')
                            ->label('Enable Background Live Poller')
                            ->helperText('When turned off, the live poller enters an idle sleep state without making upstream API calls.')
                            ->default(true),

                        TextInput::make('poll_interval_t20')
                            ->label('T20 / T10 Polling Delay (Seconds)')
                            ->helperText('Polling interval for T20/T10 matches (default: 10 seconds).')
                            ->numeric()
                            ->minValue(3)
                            ->maxValue(120)
                            ->default(10)
                            ->required(),

                        TextInput::make('poll_interval_odi')
                            ->label('ODI Polling Delay (Seconds)')
                            ->helperText('Polling interval for 50-50 ODI matches (default: 25 seconds).')
                            ->numeric()
                            ->minValue(5)
                            ->maxValue(180)
                            ->default(25)
                            ->required(),

                        TextInput::make('poll_interval_test')
                            ->label('Test Match Polling Delay (Seconds)')
                            ->helperText('Polling interval for Test matches (default: 30 seconds).')
                            ->numeric()
                            ->minValue(5)
                            ->maxValue(300)
                            ->default(30)
                            ->required(),

                        TextInput::make('daily_api_call_limit')
                            ->label('Daily API Call Safety Limit')
                            ->helperText('Safety cap below 5,000/day quota (default: 4800). Polling suspends when reached.')
                            ->numeric()
                            ->minValue(100)
                            ->default(4800)
                            ->required(),
                    ])->columns(2),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        LiveEngineConfig::set('live_engine_enabled', (bool) ($state['live_engine_enabled'] ?? true));
        LiveEngineConfig::set('poll_interval_t20', (int) ($state['poll_interval_t20'] ?? 10));
        LiveEngineConfig::set('poll_interval_odi', (int) ($state['poll_interval_odi'] ?? 25));
        LiveEngineConfig::set('poll_interval_test', (int) ($state['poll_interval_test'] ?? 30));
        LiveEngineConfig::set('daily_api_call_limit', (int) ($state['daily_api_call_limit'] ?? 4800));
        LiveEngineConfig::set('show_market_rates_and_session', (bool) ($state['show_market_rates_and_session'] ?? true));
        LiveEngineConfig::set('polling_interval_seconds', (int) ($state['polling_interval_seconds'] ?? 30));

        LiveEngineConfig::set('social_telegram_url', (string) ($state['social_telegram_url'] ?? ''));
        LiveEngineConfig::set('social_telegram_enabled', (bool) ($state['social_telegram_enabled'] ?? false));

        LiveEngineConfig::set('social_instagram_url', (string) ($state['social_instagram_url'] ?? ''));
        LiveEngineConfig::set('social_instagram_enabled', (bool) ($state['social_instagram_enabled'] ?? false));

        LiveEngineConfig::set('social_twitter_url', (string) ($state['social_twitter_url'] ?? ''));
        LiveEngineConfig::set('social_twitter_enabled', (bool) ($state['social_twitter_enabled'] ?? false));

        LiveEngineConfig::set('social_youtube_url', (string) ($state['social_youtube_url'] ?? ''));
        LiveEngineConfig::set('social_youtube_enabled', (bool) ($state['social_youtube_enabled'] ?? false));

        LiveEngineConfig::set('social_facebook_url', (string) ($state['social_facebook_url'] ?? ''));
        LiveEngineConfig::set('social_facebook_enabled', (bool) ($state['social_facebook_enabled'] ?? false));

        LiveEngineConfig::set('ga4_measurement_id', strtoupper(trim((string) ($state['ga4_measurement_id'] ?? ''))));

        Notification::make()
            ->title('System, Social & App Settings updated successfully.')
            ->success()
            ->send();
    }
}
