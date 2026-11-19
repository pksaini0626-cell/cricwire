<?php

namespace App\Livewire\Pages;

use App\Models\NewsArticle;
use Livewire\Attributes\Url;
use Livewire\Component;

class NewsIndex extends Component
{
    #[Url]
    public string $category = 'all'; // 'all', 'news', 'pitch_report', 'prediction', 'fantasy_tips'

    public int $perPage = 8;

    public function setCategory(string $category): void
    {
        $allowed = ['all', 'news', 'pitch_report', 'prediction', 'fantasy_tips'];
        if (in_array(strtolower($category), $allowed, true)) {
            $this->category = strtolower($category);
            $this->perPage = 8;
        }
    }

    public function loadMore(): void
    {
        $this->perPage += 6;
    }

    public function render()
    {
        $query = NewsArticle::query()
            ->where(function ($q) {
                $q->where('status', 'published')
                  ->orWhere('is_published', true);
            })
            ->latest('published_at')
            ->latest('created_at');

        if ($this->category !== 'all') {
            $query->where('category', $this->category);
        }

        $totalCount = $query->count();
        $articles = $query->take($this->perPage)->get();
        $hasMore = $totalCount > $articles->count();

        // Recent Headlines for Right Sidebar
        $recentHeadlines = NewsArticle::query()
            ->where(function ($q) {
                $q->where('status', 'published')
                  ->orWhere('is_published', true);
            })
            ->latest('published_at')
            ->latest('created_at')
            ->take(5)
            ->get();

        $appUrl = config('app.url', 'https://cricwire.in');
        $seoData = [
            'title' => 'Latest Cricket News, Match Predictions & Pitch Reports | Cricwire',
            'description' => 'Read trending cricket breaking news, match predictions, fantasy playing 11 tips, expert pitch reports, and tournament coverage on Cricwire.',
            'canonical' => route('news.index'),
            'robots' => 'index, follow',
            'og_type' => 'website',
            'og_image' => asset('icons/cricwire_favicons/android-chrome-512x512.png'),
            'schemas' => [
                [
                    '@context' => 'https://schema.org',
                    '@type' => 'CollectionPage',
                    'name' => 'Latest Cricket News & Updates',
                    'url' => route('news.index'),
                    'description' => 'Comprehensive editorial coverage of international and domestic cricket tournaments.',
                    'publisher' => [
                        '@type' => 'Organization',
                        'name' => \App\Support\SeoConfig::get('seo_org_name', 'Cricwire'),
                    ],
                ],
            ],
        ];

        return view('livewire.pages.news-index', [
            'articles' => $articles,
            'recentHeadlines' => $recentHeadlines,
            'totalCount' => $totalCount,
            'category' => $this->category,
            'hasMore' => $hasMore,
            'seoData' => $seoData,
        ])->layout('components.layouts.app', [
            'seoData' => $seoData,
            'title' => $seoData['title'],
        ]);
    }
}
