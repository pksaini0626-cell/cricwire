<?php

namespace App\Filament\Pages;

use App\Support\SeoConfig;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Section;
use Filament\Forms\Components\Tabs;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Form;
use Filament\Notifications\Notification;
use Filament\Pages\Page;

class SeoSettings extends Page
{
    protected static ?string $navigationIcon = 'heroicon-o-globe-alt';
    protected static ?string $navigationLabel = 'SEO & Schema Settings';
    protected static ?string $navigationGroup = 'Technical SEO';
    protected static ?int $navigationSort = 1;

    protected static string $view = 'filament.pages.seo-settings';

    public ?array $data = [];

    public function mount(): void
    {
        $sameAsLinks = SeoConfig::get('seo_same_as_links', []);
        if (is_string($sameAsLinks)) {
            $sameAsLinks = json_decode($sameAsLinks, true) ?: [];
        }

        // Format for repeater if needed
        $repeaterItems = [];
        if (is_array($sameAsLinks)) {
            foreach ($sameAsLinks as $item) {
                if (is_string($item)) {
                    $repeaterItems[] = ['url' => $item];
                } elseif (is_array($item) && isset($item['url'])) {
                    $repeaterItems[] = $item;
                }
            }
        }

        $this->form->fill([
            // Tab 1: Global & Home SEO
            'seo_home_title' => SeoConfig::get('seo_home_title', SeoConfig::DEFAULTS['seo_home_title']),
            'seo_home_description' => SeoConfig::get('seo_home_description', SeoConfig::DEFAULTS['seo_home_description']),
            'seo_home_keywords' => SeoConfig::get('seo_home_keywords', SeoConfig::DEFAULTS['seo_home_keywords']),
            'seo_og_default_image' => SeoConfig::get('seo_og_default_image'),
            'google_site_verification' => SeoConfig::get('google_site_verification'),
            'bing_site_verification' => SeoConfig::get('bing_site_verification'),

            // Tab 2: Programmatic Match Templates
            'seo_live_match_title_tpl' => SeoConfig::get('seo_live_match_title_tpl', SeoConfig::DEFAULTS['seo_live_match_title_tpl']),
            'seo_live_match_desc_tpl' => SeoConfig::get('seo_live_match_desc_tpl', SeoConfig::DEFAULTS['seo_live_match_desc_tpl']),
            'seo_upcoming_match_title_tpl' => SeoConfig::get('seo_upcoming_match_title_tpl', SeoConfig::DEFAULTS['seo_upcoming_match_title_tpl']),
            'seo_upcoming_match_desc_tpl' => SeoConfig::get('seo_upcoming_match_desc_tpl', SeoConfig::DEFAULTS['seo_upcoming_match_desc_tpl']),
            'seo_finished_match_title_tpl' => SeoConfig::get('seo_finished_match_title_tpl', SeoConfig::DEFAULTS['seo_finished_match_title_tpl']),
            'seo_finished_match_desc_tpl' => SeoConfig::get('seo_finished_match_desc_tpl', SeoConfig::DEFAULTS['seo_finished_match_desc_tpl']),

            // Tab 3: Indexing & Crawl Budget Rules
            'seo_auto_noindex_old_matches_days' => (int) SeoConfig::get('seo_auto_noindex_old_matches_days', 30),
            'seo_robots_txt_content' => SeoConfig::get('seo_robots_txt_content', SeoConfig::DEFAULTS['seo_robots_txt_content']),

            // Tab 4: Social & Schema Organization
            'seo_org_name' => SeoConfig::get('seo_org_name', 'Cricwire'),
            'seo_org_logo' => SeoConfig::get('seo_org_logo'),
            'seo_same_as_links' => $repeaterItems,
        ]);
    }

    public function form(Form $form): Form
    {
        return $form
            ->schema([
                Tabs::make('SEO Configuration Tabs')
                    ->tabs([
                        // TAB 1: Global & Home SEO
                        Tabs\Tab::make('Global & Home SEO')
                            ->icon('heroicon-o-home')
                            ->schema([
                                TextInput::make('seo_home_title')
                                    ->label('Home Meta Title')
                                    ->required()
                                    ->maxLength(120)
                                    ->helperText('Recommended: 50-60 characters.'),

                                Textarea::make('seo_home_description')
                                    ->label('Home Meta Description')
                                    ->rows(3)
                                    ->maxLength(250)
                                    ->helperText('Recommended: 140-160 characters for high CTR on SERP.'),

                                TextInput::make('seo_home_keywords')
                                    ->label('Home Meta Keywords (Comma separated)')
                                    ->placeholder('cricket live score, match commentary, ipl scores')
                                    ->maxLength(255),

                                FileUpload::make('seo_og_default_image')
                                    ->label('Default OpenGraph & Twitter Share Image')
                                    ->image()
                                    ->maxSize(500)
                                    ->directory('seo/og')
                                    ->helperText('Upload JPG/PNG, max 500KB (1200x630px recommended for social cards).'),

                                Section::make('Search Engine Verification Tokens')
                                    ->schema([
                                        TextInput::make('google_site_verification')
                                            ->label('Google Search Console Verification Code')
                                            ->placeholder('e.g. ABcDefGhIjKlMnOpQrStUvWxYz12345')
                                            ->helperText('Meta tag content value for google-site-verification.'),

                                        TextInput::make('bing_site_verification')
                                            ->label('Bing Webmaster Verification Code')
                                            ->placeholder('e.g. 1234567890ABCDEF1234567890ABCDEF')
                                            ->helperText('Meta tag content value for msvalidate.01.'),
                                    ])->columns(2),
                            ]),

                        // TAB 2: Programmatic Match Templates
                        Tabs\Tab::make('Match Programmatic Templates')
                            ->icon('heroicon-o-variable')
                            ->schema([
                                Section::make('Dynamic Variables Available')
                                    ->description('Variables replaced in templates: {team_a}, {team_b}, {series}, {date}, {venue}, {format}')
                                    ->collapsible()
                                    ->collapsed(),

                                Section::make('Live Matches')
                                    ->schema([
                                        TextInput::make('seo_live_match_title_tpl')
                                            ->label('Live Match Title Template')
                                            ->required(),
                                        Textarea::make('seo_live_match_desc_tpl')
                                            ->label('Live Match Description Template')
                                            ->rows(2),
                                    ]),

                                Section::make('Upcoming Matches')
                                    ->schema([
                                        TextInput::make('seo_upcoming_match_title_tpl')
                                            ->label('Upcoming Match Title Template')
                                            ->required(),
                                        Textarea::make('seo_upcoming_match_desc_tpl')
                                            ->label('Upcoming Match Description Template')
                                            ->rows(2),
                                    ]),

                                Section::make('Completed Matches')
                                    ->schema([
                                        TextInput::make('seo_finished_match_title_tpl')
                                            ->label('Completed Match Title Template')
                                            ->required(),
                                        Textarea::make('seo_finished_match_desc_tpl')
                                            ->label('Completed Match Description Template')
                                            ->rows(2),
                                    ]),
                            ]),

                        // TAB 3: Indexing & Crawl Budget Rules
                        Tabs\Tab::make('Indexing & Crawl Budget')
                            ->icon('heroicon-o-shield-check')
                            ->schema([
                                TextInput::make('seo_auto_noindex_old_matches_days')
                                    ->label('Auto-NoIndex Old Completed Matches (Days)')
                                    ->numeric()
                                    ->minValue(0)
                                    ->maxValue(365)
                                    ->default(30)
                                    ->helperText('Automatically outputs `<meta name="robots" content="noindex, follow">` on completed matches older than X days to conserve search engine crawl budget. Set 0 to disable.')
                                    ->required(),

                                Textarea::make('seo_robots_txt_content')
                                    ->label('Dynamic robots.txt Content')
                                    ->rows(8)
                                    ->extraInputAttributes(['class' => 'font-mono'])
                                    ->helperText('Live rules served immediately at /robots.txt with Content-Type: text/plain.')
                                    ->required(),
                            ]),

                        // TAB 4: Social & Schema Organization
                        Tabs\Tab::make('Organization & Social Schema')
                            ->icon('heroicon-o-building-office')
                            ->schema([
                                TextInput::make('seo_org_name')
                                    ->label('Organization / Brand Name')
                                    ->default('Cricwire')
                                    ->required(),

                                FileUpload::make('seo_org_logo')
                                    ->label('Organization Logo (for Google Knowledge Graph)')
                                    ->image()
                                    ->directory('seo/logo')
                                    ->helperText('Square or transparent PNG recommended for SportsOrganization schema.'),

                                Repeater::make('seo_same_as_links')
                                    ->label('Knowledge Graph Social Profiles (sameAs links)')
                                    ->schema([
                                        TextInput::make('url')
                                            ->label('Social Profile URL')
                                            ->url()
                                            ->placeholder('https://www.youtube.com/@cricwire')
                                            ->required(),
                                    ])
                                    ->defaultItems(2)
                                    ->helperText('Official social accounts (YouTube, Telegram, Instagram, X/Twitter) linked into JSON-LD organization schema.'),
                            ]),
                    ]),
            ])
            ->statePath('data');
    }

    public function save(): void
    {
        $state = $this->form->getState();

        // Save Tab 1
        SeoConfig::set('seo_home_title', $state['seo_home_title'] ?? '');
        SeoConfig::set('seo_home_description', $state['seo_home_description'] ?? '');
        SeoConfig::set('seo_home_keywords', $state['seo_home_keywords'] ?? '');
        SeoConfig::set('seo_og_default_image', $state['seo_og_default_image'] ?? null);
        SeoConfig::set('google_site_verification', $state['google_site_verification'] ?? '');
        SeoConfig::set('bing_site_verification', $state['bing_site_verification'] ?? '');

        // Save Tab 2
        SeoConfig::set('seo_live_match_title_tpl', $state['seo_live_match_title_tpl'] ?? '');
        SeoConfig::set('seo_live_match_desc_tpl', $state['seo_live_match_desc_tpl'] ?? '');
        SeoConfig::set('seo_upcoming_match_title_tpl', $state['seo_upcoming_match_title_tpl'] ?? '');
        SeoConfig::set('seo_upcoming_match_desc_tpl', $state['seo_upcoming_match_desc_tpl'] ?? '');
        SeoConfig::set('seo_finished_match_title_tpl', $state['seo_finished_match_title_tpl'] ?? '');
        SeoConfig::set('seo_finished_match_desc_tpl', $state['seo_finished_match_desc_tpl'] ?? '');

        // Save Tab 3
        SeoConfig::set('seo_auto_noindex_old_matches_days', (int) ($state['seo_auto_noindex_old_matches_days'] ?? 30));
        SeoConfig::set('seo_robots_txt_content', $state['seo_robots_txt_content'] ?? '');

        // Save Tab 4
        SeoConfig::set('seo_org_name', $state['seo_org_name'] ?? 'Cricwire');
        SeoConfig::set('seo_org_logo', $state['seo_org_logo'] ?? null);

        // Sanitize sameAs links
        $rawSameAs = $state['seo_same_as_links'] ?? [];
        $cleanedLinks = [];
        if (is_array($rawSameAs)) {
            foreach ($rawSameAs as $item) {
                $url = is_array($item) ? ($item['url'] ?? null) : $item;
                if ($url && filter_var($url, FILTER_VALIDATE_URL)) {
                    $cleanedLinks[] = $url;
                }
            }
        }
        SeoConfig::set('seo_same_as_links', $cleanedLinks);

        Notification::make()
            ->title('Technical SEO & Schema Settings saved successfully.')
            ->success()
            ->send();
    }
}
