<?php

namespace App\Livewire\Pages;

use App\Models\NewsArticle;
use App\Models\SkippedSeries;
use Illuminate\Support\Str;
use Livewire\Component;
use Livewire\WithFileUploads;

class WriteForUsPage extends Component
{
    use WithFileUploads;

    public string $title = '';
    public string $category = 'news';
    public ?string $series_name = '';
    public string $summary = '';
    public string $content = '';
    public $featured_image = null;
    public string $author_name = '';
    public string $author_bio = '';
    public ?string $author_social_url = '';
    public bool $cricket_only_confirmed = false;

    public bool $submittedSuccess = false;
    public ?string $submittedTitle = null;
    public ?string $rateLimitError = null;

    public function mount(): void
    {
        if (auth()->check()) {
            $this->author_name = (string) (auth()->user()->name ?? '');
        } else {
            session()->put('url.intended', route('guest-post.create'));
        }
    }

    protected function rules(): array
    {
        return [
            'title' => ['required', 'string', 'min:15', 'max:140'],
            'category' => ['required', 'in:news,pitch_report,prediction,fantasy_tips'],
            'series_name' => ['nullable', 'string', 'max:150'],
            'summary' => ['required', 'string', 'min:40', 'max:300'],
            'content' => ['required', 'string', 'min:1200', 'max:25000'],
            'featured_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:512'],
            'author_name' => ['required', 'string', 'min:2', 'max:80'],
            'author_bio' => ['required', 'string', 'min:15', 'max:220'],
            'author_social_url' => ['nullable', 'url', 'max:200'],
            'cricket_only_confirmed' => ['accepted'],
        ];
    }

    protected function messages(): array
    {
        return [
            'title.min' => 'Your headline must be at least 15 characters long.',
            'summary.min' => 'Please provide a concise summary of at least 40 characters.',
            'content.min' => 'Your article body must be at least 1,200 characters (~250+ words) of genuine cricket analysis.',
            'featured_image.required' => 'Please upload a featured cricket banner image (max 500 KB).',
            'featured_image.max' => 'Banner image must not exceed 500 KB.',
            'cricket_only_confirmed.accepted' => 'You must confirm the article is 100% original, cricket-related, and free of betting/gambling links.',
        ];
    }

    public function submit(): void
    {
        if (!auth()->check()) {
            $this->redirectRoute('auth.google', ['intended' => '/write-for-us']);
            return;
        }

        $this->rateLimitError = null;
        $this->submittedSuccess = false;

        $recentSubmissionsCount = NewsArticle::query()
            ->where('user_id', auth()->id())
            ->where('created_at', '>=', now()->subDay())
            ->count();

        if ($recentSubmissionsCount >= 3) {
            $this->rateLimitError = 'Submission limit reached: you can submit up to 3 guest articles per 24 hours.';
            $this->addError('rate_limit', $this->rateLimitError);
            return;
        }

        $this->validate();

        $baseSlug = Str::slug($this->title) ?: 'cricket-guest-post';
        $slug = $baseSlug;
        $suffix = 2;

        while (NewsArticle::where('slug', $slug)->exists()) {
            $slug = "{$baseSlug}-{$suffix}";
            $suffix++;
        }

        $imagePath = $this->featured_image->store('news', 'public');
        $cleanSeriesName = filled($this->series_name) ? trim((string) $this->series_name) : null;

        $article = NewsArticle::create([
            'user_id' => auth()->id(),
            'title' => trim($this->title),
            'slug' => $slug,
            'category' => $this->category,
            'series_name' => $cleanSeriesName,
            'series_slug' => $cleanSeriesName ? Str::slug($cleanSeriesName) : null,
            'summary' => trim($this->summary),
            'content' => NewsArticle::sanitizeGuestHtml($this->content),
            'featured_image' => $imagePath,
            'source_name' => 'Guest Contributor',
            'status' => 'pending_review',
            'is_published' => false,
            'is_guest_post' => true,
            'author_name' => trim($this->author_name),
            'author_bio' => trim($this->author_bio),
            'author_social_url' => filled($this->author_social_url) ? trim((string) $this->author_social_url) : null,
        ]);

        $this->submittedTitle = $article->title;
        $this->submittedSuccess = true;

        $this->reset([
            'title',
            'series_name',
            'summary',
            'content',
            'featured_image',
            'cricket_only_confirmed',
        ]);
        $this->category = 'news';
        $this->author_name = (string) (auth()->user()->name ?? $this->author_name);
    }

    public function render()
    {
        $activeSeries = SkippedSeries::query()
            ->where('is_skipped', false)
            ->orderBy('series_name')
            ->pluck('series_name')
            ->filter()
            ->unique()
            ->values();

        $mySubmissions = collect();
        $submissionsLast24h = 0;

        if (auth()->check()) {
            $mySubmissions = NewsArticle::query()
                ->where('user_id', auth()->id())
                ->where('is_guest_post', true)
                ->latest('created_at')
                ->get();

            $submissionsLast24h = NewsArticle::query()
                ->where('user_id', auth()->id())
                ->where('created_at', '>=', now()->subDay())
                ->count();
        }

        $seoData = [
            'title' => 'Write for Us — Submit Cricket Guest Posts & Match Analysis | Cricwire',
            'description' => 'Publish your original cricket match previews, pitch reports, tactical breakdowns, and fantasy cricket tips on Cricwire.',
            'canonical' => route('guest-post.create'),
            'robots' => 'index, follow',
            'og_type' => 'website',
        ];

        return view('livewire.pages.write-for-us-page', [
            'activeSeries' => $activeSeries,
            'mySubmissions' => $mySubmissions,
            'submissionsLast24h' => $submissionsLast24h,
            'remainingToday' => max(0, 3 - $submissionsLast24h),
        ])->layout('components.layouts.app', [
            'seoData' => $seoData,
            'title' => $seoData['title'],
        ]);
    }
}
