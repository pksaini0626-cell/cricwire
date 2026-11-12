<?php

namespace App\Livewire\Pages;

use App\Models\AdBanner;
use App\Models\NewsArticle;
use Livewire\Component;

class NewsShow extends Component
{
    public NewsArticle $article;

    public function mount(NewsArticle $article): void
    {
        $this->article = $article;
    }

    public function render()
    {
        // 1. Latest 3 news (excluding current article)
        $latestNews = NewsArticle::query()
            ->where('id', '!=', $this->article->id)
            ->where(function ($q) {
                $q->where('status', 'published')
                  ->orWhere('is_published', true);
            })
            ->latest('published_at')
            ->latest('created_at')
            ->take(3)
            ->get();

        // 2. Active sidebar ad banner
        $sidebarBanner = AdBanner::activeSlot('news_sidebar')->first()
            ?? AdBanner::activeSlot('desktop_right')->first()
            ?? AdBanner::activeSlot('sidebar')->first();

        // 3. Extract tags or fallback top categories
        $rawKeywords = $this->article->seo_keywords ?? '';
        $tags = [];
        if (!empty($rawKeywords)) {
            $tags = array_filter(array_map('trim', explode(',', $rawKeywords)));
        }
        if (empty($tags)) {
            $tags = ['BBL', 'IPL 2026', 'ILT20', 'Match Prediction', 'Pitch Report', 'Team Squads'];
        }

        // 4. Fetch related articles for bottom section
        $relatedArticles = NewsArticle::query()
            ->where('id', '!=', $this->article->id)
            ->where(function ($q) {
                $q->where('status', 'published')
                  ->orWhere('is_published', true);
            })
            ->where('category', $this->article->category)
            ->latest('published_at')
            ->take(3)
            ->get();

        if ($relatedArticles->isEmpty()) {
            $relatedArticles = $latestNews;
        }

        return view('livewire.pages.news-show', [
            'article' => $this->article,
            'latestNews' => $latestNews,
            'sidebarBanner' => $sidebarBanner,
            'tags' => $tags,
            'relatedArticles' => $relatedArticles,
        ])->layout('components.layouts.app', [
            'title' => ($this->article->seo_meta_title ?: $this->article->title) . ' | CricWire',
        ]);
    }
}
