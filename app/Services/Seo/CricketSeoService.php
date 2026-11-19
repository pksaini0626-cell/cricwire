<?php

namespace App\Services\Seo;

use App\Helpers\CricketSlugHelper;
use App\Models\NewsArticle;
use App\Support\SeoConfig;
use Carbon\Carbon;
use Illuminate\Support\Str;

class CricketSeoService
{
    /**
     * Generate complete SEO metadata & JSON-LD schema for Home Page.
     */
    public static function forHome(): array
    {
        $appUrl = config('app.url', 'https://cricwire.in');
        $title = SeoConfig::get('seo_home_title', SeoConfig::DEFAULTS['seo_home_title']);
        $description = SeoConfig::get('seo_home_description', SeoConfig::DEFAULTS['seo_home_description']);
        $keywords = SeoConfig::get('seo_home_keywords', SeoConfig::DEFAULTS['seo_home_keywords']);
        $ogImage = self::resolveOgImage(SeoConfig::get('seo_og_default_image'));
        $orgName = SeoConfig::get('seo_org_name', 'Cricwire');
        $orgLogo = self::resolveOrgLogo();
        $sameAsLinks = self::resolveSameAsLinks();

        // 1. WebSite Schema with SearchAction
        $websiteSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'WebSite',
            'name' => $orgName,
            'alternateName' => 'CricWire Cricket',
            'url' => $appUrl,
            'potentialAction' => [
                '@type' => 'SearchAction',
                'target' => [
                    '@type' => 'EntryPoint',
                    'urlTemplate' => $appUrl . '/cricket-news?search={search_term_string}',
                ],
                'query-input' => 'required name=search_term_string',
            ],
        ];

        // 2. SportsOrganization Schema
        $orgSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'SportsOrganization',
            'name' => $orgName,
            'url' => $appUrl,
            'logo' => $orgLogo,
            'sport' => 'Cricket',
            'sameAs' => $sameAsLinks,
        ];

        return [
            'title' => $title,
            'description' => $description,
            'keywords' => $keywords,
            'canonical' => url('/'),
            'robots' => 'index, follow',
            'og_type' => 'website',
            'og_image' => $ogImage,
            'schemas' => [
                $websiteSchema,
                $orgSchema,
            ],
        ];
    }

    /**
     * Generate complete SEO metadata, Canonical URL, & JSON-LD schema for Match Detail views.
     */
    public static function forMatch(array $match, string $tab = 'live-line', array $commentary = []): array
    {
        $appUrl = config('app.url', 'https://cricwire.in');
        $teamA = $match['team_a'] ?? $match['team_a_short'] ?? $match['team1'] ?? 'Team A';
        $teamB = $match['team_b'] ?? $match['team_b_short'] ?? $match['team2'] ?? 'Team B';
        $series = $match['series'] ?? $match['series_name'] ?? $match['series_title'] ?? 'Cricket Tournament';
        $venue = $match['venue'] ?? $match['ground'] ?? $match['stadium'] ?? 'Cricket Stadium';
        $rawDate = $match['date_wise'] ?? $match['match_date'] ?? $match['start_date'] ?? null;
        $dateFormatted = self::formatMatchDate($rawDate);
        $format = $match['match_format'] ?? $match['format'] ?? $match['match_type'] ?? 'T20';

        // Detect Match Status
        $rawStatus = strtolower(trim((string) ($match['match_status'] ?? $match['status'] ?? '')));
        $isLive = in_array($rawStatus, ['live', 'in_progress', 'innings_break', 'inprogress', 'rain', 'delayed'], true);
        $isCompleted = in_array($rawStatus, ['finished', 'completed', 'result', 'ended', 'abandoned'], true);
        $isUpcoming = !$isLive && !$isCompleted;

        // Replace placeholders helper
        $replacements = [
            '{team_a}' => $teamA,
            '{team_b}' => $teamB,
            '{series}' => $series,
            '{venue}' => $venue,
            '{date}' => $dateFormatted,
            '{format}' => $format,
        ];

        // Determine Templates based on match state
        if ($isLive) {
            $titleTpl = SeoConfig::get('seo_live_match_title_tpl', SeoConfig::DEFAULTS['seo_live_match_title_tpl']);
            $descTpl = SeoConfig::get('seo_live_match_desc_tpl', SeoConfig::DEFAULTS['seo_live_match_desc_tpl']);
        } elseif ($isCompleted) {
            $titleTpl = SeoConfig::get('seo_finished_match_title_tpl', SeoConfig::DEFAULTS['seo_finished_match_title_tpl']);
            $descTpl = SeoConfig::get('seo_finished_match_desc_tpl', SeoConfig::DEFAULTS['seo_finished_match_desc_tpl']);
        } else {
            $titleTpl = SeoConfig::get('seo_upcoming_match_title_tpl', SeoConfig::DEFAULTS['seo_upcoming_match_title_tpl']);
            $descTpl = SeoConfig::get('seo_upcoming_match_desc_tpl', SeoConfig::DEFAULTS['seo_upcoming_match_desc_tpl']);
        }

        $metaTitle = str_replace(array_keys($replacements), array_values($replacements), $titleTpl);
        $metaDescription = str_replace(array_keys($replacements), array_values($replacements), $descTpl);

        // Adjust title for specific tabs to avoid duplicate meta across tabs while preserving intent
        if ($tab !== 'live-line' && $tab !== 'info') {
            $tabLabel = ucfirst(str_replace('-', ' ', $tab));
            $metaTitle = "{$tabLabel}: " . $metaTitle;
        }

        // Canonical URL: Clean route URL without query parameters
        $matchId = $match['match_id'] ?? $match['id'] ?? 1;
        $seriesSlug = CricketSlugHelper::makeSeriesSlug($match);
        $matchSlug = CricketSlugHelper::makeMatchSlug($match, $matchId);
        $cleanTab = strtolower(trim($tab ?: 'live-line'));
        
        $canonicalUrl = route('match.detail', [
            'seriesSlug' => $seriesSlug,
            'tab' => $cleanTab,
            'matchSlug' => $matchSlug,
        ]);

        // Check Indexing Rules / Crawl Budget
        $robots = 'index, follow';
        if ($isCompleted) {
            $autoNoindexDays = (int) SeoConfig::get('seo_auto_noindex_old_matches_days', 30);
            if ($autoNoindexDays > 0) {
                $matchTimestamp = self::parseMatchTimestamp($rawDate);
                if ($matchTimestamp && $matchTimestamp->lt(now()->subDays($autoNoindexDays))) {
                    $robots = 'noindex, follow';
                }
            }
        }

        $ogImage = self::resolveOgImage(SeoConfig::get('seo_og_default_image'));
        $schemas = [];

        // 1. SportsEvent Schema
        $startDateIso = self::parseMatchTimestamp($rawDate)?->toIso8601String() ?? now()->toIso8601String();
        $eventStatus = $isLive 
            ? 'https://schema.org/EventScheduled' 
            : ($isCompleted ? 'https://schema.org/EventCompleted' : 'https://schema.org/EventScheduled');

        $sportsEvent = [
            '@context' => 'https://schema.org',
            '@type' => 'SportsEvent',
            'name' => "{$teamA} vs {$teamB}, {$series}",
            'description' => $metaDescription,
            'startDate' => $startDateIso,
            'eventStatus' => $eventStatus,
            'location' => [
                '@type' => 'Place',
                'name' => $venue,
                'address' => [
                    '@type' => 'PostalAddress',
                    'addressLocality' => $venue,
                ],
            ],
            'homeTeam' => [
                '@type' => 'SportsTeam',
                'name' => $teamA,
            ],
            'awayTeam' => [
                '@type' => 'SportsTeam',
                'name' => $teamB,
            ],
            'url' => $canonicalUrl,
        ];

        if ($isCompleted) {
            $resultSummary = $match['result'] ?? $match['status_note'] ?? $match['match_result'] ?? "{$teamA} vs {$teamB} match completed.";
            $sportsEvent['description'] = "{$resultSummary}. " . $metaDescription;
        }

        $schemas[] = $sportsEvent;

        // 2. LIVE MATCH: LiveBlogPosting Schema (Crucial for Google 'LIVE' Badge)
        if ($isLive) {
            $liveBlogUpdates = [];
            $commentaryItems = array_slice($commentary, 0, 10);

            foreach ($commentaryItems as $c) {
                $body = is_array($c) 
                    ? ($c['commentary'] ?? $c['comm'] ?? $c['text'] ?? $c['title'] ?? '') 
                    : (string) $c;

                $headline = is_array($c) ? ($c['over'] ?? $c['overs'] ?? 'Live Ball Update') : 'Live Ball Update';
                if (!empty($body)) {
                    $liveBlogUpdates[] = [
                        '@type' => 'BlogPosting',
                        'headline' => (string) $headline,
                        'articleBody' => strip_tags((string) $body),
                        'datePublished' => now()->toIso8601String(),
                    ];
                }
            }

            $liveBlogSchema = [
                '@context' => 'https://schema.org',
                '@type' => 'LiveBlogPosting',
                'headline' => "{$teamA} vs {$teamB} Live Ball by Ball Commentary & Scorecard",
                'coverageStartTime' => $startDateIso,
                'url' => $canonicalUrl,
                'datePublished' => $startDateIso,
                'dateModified' => now()->toIso8601String(),
                'publisher' => [
                    '@type' => 'Organization',
                    'name' => SeoConfig::get('seo_org_name', 'Cricwire'),
                    'logo' => [
                        '@type' => 'ImageObject',
                        'url' => self::resolveOrgLogo(),
                    ],
                ],
            ];

            if (!empty($liveBlogUpdates)) {
                $liveBlogSchema['liveBlogUpdate'] = $liveBlogUpdates;
            }

            $schemas[] = $liveBlogSchema;
        }

        // 3. UPCOMING MATCH: FAQPage Schema
        $faqs = [];
        if ($isUpcoming) {
            $faqs = self::generateMatchFaqs($match, $teamA, $teamB, $venue, $rawDate, $canonicalUrl);
            $faqItems = [];
            foreach ($faqs as $faq) {
                $faqItems[] = [
                    '@type' => 'Question',
                    'name' => $faq['question'],
                    'acceptedAnswer' => [
                        '@type' => 'Answer',
                        'text' => $faq['answer'],
                    ],
                ];
            }

            $schemas[] = [
                '@context' => 'https://schema.org',
                '@type' => 'FAQPage',
                'mainEntity' => $faqItems,
            ];
        }

        return [
            'title' => $metaTitle,
            'description' => $metaDescription,
            'canonical' => $canonicalUrl,
            'robots' => $robots,
            'og_type' => 'website',
            'og_image' => $ogImage,
            'schemas' => $schemas,
            'faqs' => $faqs, // Returned for visible on-page rendering
        ];
    }

    /**
     * Generate complete SEO metadata & JSON-LD schema for News Article.
     */
    public static function forNewsArticle(NewsArticle $article): array
    {
        $appUrl = config('app.url', 'https://cricwire.in');
        $canonicalUrl = route('news.show', $article->slug);
        $imageUrl = $article->image_url 
            ? (str_starts_with($article->image_url, 'http') ? $article->image_url : asset('storage/' . $article->image_url))
            : self::resolveOgImage(SeoConfig::get('seo_og_default_image'));

        $metaTitle = $article->meta_title ?: $article->title . ' - Cricwire News';
        $metaDescription = $article->meta_description ?: Str::limit(strip_tags($article->content ?? $article->summary ?? ''), 160);

        $orgName = SeoConfig::get('seo_org_name', 'Cricwire');
        $orgLogo = self::resolveOrgLogo();

        // 1. NewsArticle Schema
        $newsSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'NewsArticle',
            'mainEntityOfPage' => [
                '@type' => 'WebPage',
                '@id' => $canonicalUrl,
            ],
            'headline' => Str::limit($article->title, 110),
            'image' => [
                $imageUrl,
            ],
            'datePublished' => $article->created_at?->toIso8601String() ?? now()->toIso8601String(),
            'dateModified' => $article->updated_at?->toIso8601String() ?? now()->toIso8601String(),
            'author' => [
                '@type' => 'Person',
                'name' => $article->author_name ?? 'Cricwire Editorial Team',
            ],
            'publisher' => [
                '@type' => 'Organization',
                'name' => $orgName,
                'logo' => [
                    '@type' => 'ImageObject',
                    'url' => $orgLogo,
                ],
            ],
            'description' => $metaDescription,
        ];

        // 2. BreadcrumbList Schema (Home > Cricket News > {Article Title})
        $breadcrumbSchema = [
            '@context' => 'https://schema.org',
            '@type' => 'BreadcrumbList',
            'itemListElement' => [
                [
                    '@type' => 'ListItem',
                    'position' => 1,
                    'name' => 'Home',
                    'item' => $appUrl,
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 2,
                    'name' => 'Cricket News',
                    'item' => route('news.index'),
                ],
                [
                    '@type' => 'ListItem',
                    'position' => 3,
                    'name' => $article->title,
                    'item' => $canonicalUrl,
                ],
            ],
        ];

        return [
            'title' => $metaTitle,
            'description' => $metaDescription,
            'canonical' => $canonicalUrl,
            'robots' => $article->is_published ? 'index, follow' : 'noindex, nofollow',
            'og_type' => 'article',
            'og_image' => $imageUrl,
            'schemas' => [
                $newsSchema,
                $breadcrumbSchema,
            ],
        ];
    }

    /**
     * Generate high-CTR Match FAQs for Schema + Visible Accordion.
     */
    public static function generateMatchFaqs(
        array $match, 
        string $teamA, 
        string $teamB, 
        string $venue, 
        ?string $rawDate, 
        string $canonicalUrl
    ): array {
        $carbonDate = self::parseMatchTimestamp($rawDate);
        $istTime = $carbonDate ? $carbonDate->copy()->setTimezone('Asia/Kolkata')->format('d M Y, h:i A \I\S\T') : 'TBD';
        $utcTime = $carbonDate ? $carbonDate->copy()->setTimezone('UTC')->format('h:i A \U\T\C') : 'TBD';

        // Playing XI check
        $playingXiA = $match['team_a_playing_xi'] ?? $match['team1_xi'] ?? null;
        $playingXiB = $match['team_b_playing_xi'] ?? $match['team2_xi'] ?? null;

        if ($playingXiA && $playingXiB) {
            $playing11Text = "Playing 11 announced: {$teamA}: " . (is_array($playingXiA) ? implode(', ', $playingXiA) : $playingXiA) . ". {$teamB}: " . (is_array($playingXiB) ? implode(', ', $playingXiB) : $playingXiB) . ".";
        } else {
            $playing11Text = "The official Playing 11 for {$teamA} vs {$teamB} will be announced at the toss (30 minutes before match start). Check live squad updates here on Cricwire.";
        }

        return [
            [
                'question' => "When will {$teamA} vs {$teamB} match start?",
                'answer' => "The match between {$teamA} and {$teamB} is scheduled to start on {$istTime} ({$utcTime}).",
            ],
            [
                'question' => "Where is {$teamA} vs {$teamB} being played?",
                'answer' => "{$teamA} vs {$teamB} is being played at {$venue}.",
            ],
            [
                'question' => "What is the Playing 11 for {$teamA} vs {$teamB}?",
                'answer' => $playing11Text,
            ],
            [
                'question' => "Where can I watch {$teamA} vs {$teamB} live score ball-by-ball?",
                'answer' => "You can track the fastest ball-by-ball live cricket score, audio commentary, and real-time live line for {$teamA} vs {$teamB} directly on Cricwire at {$canonicalUrl}.",
            ],
        ];
    }

    /**
     * Helpers for images, dates and logos
     */
    protected static function resolveOgImage(?string $path): string
    {
        if ($path) {
            return str_starts_with($path, 'http') ? $path : asset('storage/' . $path);
        }
        return asset('icons/cricwire_favicons/android-chrome-512x512.png');
    }

    protected static function resolveOrgLogo(): string
    {
        $logo = SeoConfig::get('seo_org_logo');
        if ($logo) {
            return str_starts_with($logo, 'http') ? $logo : asset('storage/' . $logo);
        }
        return asset('icons/cricwire_favicons/apple-touch-icon.png');
    }

    protected static function resolveSameAsLinks(): array
    {
        $links = SeoConfig::get('seo_same_as_links', []);
        if (is_string($links)) {
            $links = json_decode($links, true) ?: [];
        }
        $result = [];
        if (is_array($links)) {
            foreach ($links as $item) {
                $val = is_array($item) ? ($item['url'] ?? null) : $item;
                if (!empty($val) && filter_var($val, FILTER_VALIDATE_URL)) {
                    $result[] = $val;
                }
            }
        }
        return !empty($result) ? $result : [
            'https://t.me/cricwire',
            'https://instagram.com/cricwire',
            'https://x.com/cricwire',
            'https://youtube.com/@cricwire',
        ];
    }

    protected static function parseMatchTimestamp(?string $dateStr): ?Carbon
    {
        if (empty($dateStr)) {
            return null;
        }

        try {
            return Carbon::parse($dateStr);
        } catch (\Throwable $e) {
            return null;
        }
    }

    protected static function formatMatchDate(?string $dateStr): string
    {
        $parsed = self::parseMatchTimestamp($dateStr);
        return $parsed ? $parsed->format('d M Y') : date('d M Y');
    }
}
