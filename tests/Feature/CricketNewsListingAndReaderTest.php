<?php

namespace Tests\Feature;

use App\Models\AdBanner;
use App\Models\NewsArticle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class CricketNewsListingAndReaderTest extends TestCase
{
    use RefreshDatabase;

    public function test_cricket_news_index_renders_published_articles_and_zero_whitespace_ads(): void
    {
        $author = User::factory()->create();

        // Published article
        $article = NewsArticle::create([
            'author_id' => $author->id,
            'title' => 'Rohit Sharma Smashes Record Double Century in Mumbai',
            'slug' => 'rohit-sharma-smashes-record-double-century',
            'category' => 'news',
            'summary' => 'A masterclass innings led the team to a mammoth victory.',
            'content' => '<p>Rohit Sharma was in sublime touch tonight in Mumbai.</p>',
            'status' => 'published',
            'is_published' => true,
            'published_at' => now(),
        ]);

        // Draft article (should NOT appear)
        NewsArticle::create([
            'author_id' => $author->id,
            'title' => 'Secret Draft Rumors',
            'slug' => 'secret-draft-rumors',
            'category' => 'news',
            'content' => '<p>Draft preview</p>',
            'status' => 'draft',
            'is_published' => false,
        ]);

        $response = $this->get('/cricket-news');
        $response->assertStatus(200);
        $response->assertSee('Rohit Sharma Smashes Record Double Century in Mumbai');
        $response->assertDontSee('Secret Draft Rumors');

        // ZERO Whitespace Rule: When no AdBanner exists, SPONSORED should not be in response
        $this->assertEquals(0, AdBanner::count());
        $response->assertDontSee('CRICKET PARTNER');

        // Now activate an ad banner
        $banner = AdBanner::create([
            'name' => 'Stake News Partner',
            'placement' => 'news_in_feed',
            'type' => 'image_link',
            'image_url' => 'https://example.com/stake-300x250.jpg',
            'target_url' => 'https://stake.com',
            'is_active' => true,
        ]);

        // Test scopeActiveSlot
        $activeSlot = AdBanner::activeSlot('news_in_feed')->first();
        $this->assertNotNull($activeSlot);
        $this->assertEquals($banner->id, $activeSlot->id);
    }

    public function test_cricket_news_show_reader_view(): void
    {
        $author = User::factory()->create();

        $article = NewsArticle::create([
            'author_id' => $author->id,
            'title' => 'Adelaide Oval Pitch Report & Weather Forecast',
            'slug' => 'adelaide-oval-pitch-report',
            'category' => 'pitch_report',
            'summary' => 'Expect bounce and carry for pacers.',
            'content' => '<p>The Adelaide surface has green tinge.</p>',
            'status' => 'published',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $response = $this->get('/cricket-news/' . $article->slug);
        $response->assertStatus(200);
        $response->assertSee('Adelaide Oval Pitch Report & Weather Forecast');
        $response->assertSee('Pitch Report');
        $response->assertSee('The Adelaide surface has green tinge.');
    }
}

