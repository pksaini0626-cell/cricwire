<?php

namespace Tests\Feature;

use App\Filament\Resources\NewsArticleResource;
use App\Livewire\Pages\WriteForUsPage;
use App\Models\NewsArticle;
use App\Models\SkippedSeries;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class GuestPostSubmissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_write_for_us_page_shows_google_auth_gate_for_guests(): void
    {
        $response = $this->get('/write-for-us');

        $response->assertStatus(200);
        $response->assertSee('Write for Cricwire');
        $response->assertSee('Continue with Google to Submit Article');
        $response->assertDontSee('Submit for Editorial Review');
        $this->assertEquals(route('guest-post.create'), session('url.intended'));
    }

    public function test_authenticated_user_can_submit_guest_post_with_sanitized_html_and_pending_status(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['name' => 'Rahul Verma']);

        SkippedSeries::create([
            'series_name' => 'IPL 2026',
            'is_skipped' => false,
        ]);

        SkippedSeries::create([
            'series_name' => 'Hidden Series',
            'is_skipped' => true,
        ]);

        $longBody = '<p>' . str_repeat('Virat Kohli anchored the chase brilliantly at Chinnaswamy Stadium. ', 20) . '</p>'
            . '<p>Read stats at <a href="https://example-stats.com/report">External Report</a>.</p>'
            . '<script>alert("xss")</script><iframe src="https://evil.test"></iframe><style>body{display:none}</style>';

        $image = UploadedFile::fake()->image('cricket-banner.jpg', 1200, 630)->size(250);

        Livewire::actingAs($user)
            ->test(WriteForUsPage::class)
            ->assertSee('IPL 2026')
            ->assertDontSee('Hidden Series')
            ->assertSet('author_name', 'Rahul Verma')
            ->set('title', 'Why Middle Overs Spin Controls Modern T20 Chases')
            ->set('category', 'pitch_report')
            ->set('series_name', 'IPL 2026')
            ->set('summary', 'A detailed statistical breakdown of middle-over bowling economy rates and boundary percentages.')
            ->set('content', $longBody)
            ->set('featured_image', $image)
            ->set('author_bio', 'Cricket analyst and domestic T20 tactical writer.')
            ->set('author_social_url', 'https://x.com/rahulcricket')
            ->set('cricket_only_confirmed', true)
            ->call('submit')
            ->assertHasNoErrors()
            ->assertSet('submittedSuccess', true);

        $article = NewsArticle::where('user_id', $user->id)->first();
        $this->assertNotNull($article);
        $this->assertEquals('pending_review', $article->status);
        $this->assertFalse($article->is_published);
        $this->assertTrue($article->is_guest_post);
        $this->assertEquals('Guest Contributor', $article->source_name);
        $this->assertEquals('IPL 2026', $article->series_name);
        $this->assertEquals('1', NewsArticleResource::getNavigationBadge());

        Storage::disk('public')->assertExists($article->featured_image);
        $this->assertStringStartsWith('news/', $article->featured_image);

        // Unsafe tags removed and external link has nofollow + target=_blank
        $this->assertStringNotContainsString('<script', $article->content);
        $this->assertStringNotContainsString('<iframe', $article->content);
        $this->assertStringNotContainsString('<style', $article->content);
        $this->assertStringContainsString('rel="nofollow noopener noreferrer"', $article->content);
        $this->assertStringContainsString('target="_blank"', $article->content);

        // Pending guest post must NOT go live publicly
        $this->get('/cricket-news/' . $article->slug)->assertStatus(404);
        $this->get('/cricket-news')->assertDontSee($article->title);

        // Once approved/published, it renders with Author Credit Box
        $article->update([
            'status' => 'published',
            'is_published' => true,
            'published_at' => now(),
        ]);

        $this->assertNull(NewsArticleResource::getNavigationBadge());

        $showResponse = $this->get('/cricket-news/' . $article->slug);
        $showResponse->assertStatus(200);
        $showResponse->assertSee('Community Cricket Contributor');
        $showResponse->assertSee('Rahul Verma');
        $showResponse->assertSee('Cricket analyst and domestic T20 tactical writer.');
        $showResponse->assertSee('Write for Cricwire');
    }

    public function test_guest_submission_enforces_validation_and_24h_rate_limit(): void
    {
        Storage::fake('public');

        $user = User::factory()->create(['name' => 'Ananya Rao']);

        // Validation check for short body & unchecked checkbox
        Livewire::actingAs($user)
            ->test(WriteForUsPage::class)
            ->set('title', 'Too Short')
            ->set('summary', 'Short')
            ->set('content', 'Short text')
            ->set('author_bio', 'Short')
            ->set('cricket_only_confirmed', false)
            ->call('submit')
            ->assertHasErrors(['title', 'summary', 'content', 'featured_image', 'author_bio', 'cricket_only_confirmed']);

        // Seed 3 submissions in last 24h
        for ($i = 1; $i <= 3; $i++) {
            NewsArticle::create([
                'user_id' => $user->id,
                'title' => "Existing Cricket Article Number {$i} Analysis",
                'slug' => "existing-cricket-article-{$i}",
                'category' => 'news',
                'summary' => 'Existing summary with sufficient length for database storage.',
                'content' => '<p>' . str_repeat('Cricket content ', 90) . '</p>',
                'source_name' => 'Guest Contributor',
                'status' => 'pending_review',
                'is_published' => false,
                'is_guest_post' => true,
                'author_name' => 'Ananya Rao',
                'author_bio' => 'Cricket tactics researcher and analyst.',
            ]);
        }

        $image = UploadedFile::fake()->image('banner.png', 800, 450)->size(200);

        Livewire::actingAs($user)
            ->test(WriteForUsPage::class)
            ->set('title', 'Fourth Cricket Submission Within Twenty Four Hours')
            ->set('category', 'news')
            ->set('summary', 'Testing that the 24-hour anti-spam rate limit rejects a fourth submission.')
            ->set('content', str_repeat('Cricket tactical insight and statistical evaluation. ', 30))
            ->set('featured_image', $image)
            ->set('author_bio', 'Cricket tactics researcher and analyst.')
            ->set('cricket_only_confirmed', true)
            ->call('submit')
            ->assertHasErrors(['rate_limit']);

        $this->assertEquals(3, NewsArticle::where('user_id', $user->id)->count());
    }
}
