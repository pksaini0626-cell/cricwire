<?php

namespace Tests\Feature;

use App\Models\NewsApiConfig;
use App\Models\NewsArticle;
use App\Services\News\NewsIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class NewsIngestionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_news_ingestion_service_fetches_and_imports_articles_as_drafts(): void
    {
        $config = NewsApiConfig::create([
            'name' => 'RapidAPI Live Line News',
            'endpoint_url' => 'https://cricket-live-line1.p.rapidapi.com/news',
            'auth_type' => 'header',
            'api_key_header' => 'x-rapidapi-key',
            'api_key_value' => 'mock-api-key',
            'additional_headers' => ['x-rapidapi-host' => 'cricket-live-line1.p.rapidapi.com'],
            'is_active' => true,
        ]);

        Http::fake([
            'https://cricket-live-line1.p.rapidapi.com/news*' => Http::response([
                'status' => true,
                'data' => [
                    [
                        'title' => 'India Clinches Thrilling T20 Victory Over Australia',
                        'summary' => 'A scintillating finish in the final over sealed the deal.',
                        'image' => 'https://example.com/cricket.jpg',
                        'source_url' => 'https://cricketfans.in/source/1',
                        'raw_content' => '<p>A scintillating finish in the final over sealed the deal.</p>',
                    ],
                    [
                        'title' => 'Pitch Report: Adelaide Oval Favours Fast Bowlers Early On',
                        'summary' => 'Expect swing under lights with new ball.',
                        'image' => 'https://example.com/pitch.jpg',
                        'source_url' => 'https://cricketfans.in/source/2',
                        'raw_content' => '<p>Expect swing under lights with new ball.</p>',
                    ],
                ],
            ], 200),
        ]);

        $service = app(NewsIngestionService::class);
        $articles = $service->fetchFromProvider($config);

        $this->assertCount(2, $articles);
        $this->assertEquals('India Clinches Thrilling T20 Victory Over Australia', $articles[0]['title']);

        $imported = $service->importArticles($articles, $config->name);
        $this->assertEquals(2, $imported);

        // Verify draft status in database
        $article = NewsArticle::where('title', 'India Clinches Thrilling T20 Victory Over Australia')->first();
        $this->assertNotNull($article);
        $this->assertEquals('draft', $article->status);
        $this->assertEquals('RapidAPI Live Line News', $article->source_name);
        $this->assertEquals('news', $article->category);

        // Test duplicate skipping
        $duplicateImport = $service->importArticles($articles, $config->name);
        $this->assertEquals(0, $duplicateImport);
    }

    public function test_news_ingestion_handles_nested_array_fields_without_type_error(): void
    {
        $config = NewsApiConfig::create([
            'name' => 'Complex Payload Provider',
            'endpoint_url' => 'https://api.cricket.test/news',
            'auth_type' => 'none',
            'is_active' => true,
        ]);

        Http::fake([
            'https://api.cricket.test/news*' => Http::response([
                'data' => [
                    [
                        // Title passed as array/object structure (e.g., WordPress or custom CMS)
                        'title' => ['rendered' => 'Complex Title From Nested Array'],
                        'summary' => ['value' => 'Summary in array structure'],
                        'image' => ['url' => 'https://example.com/nested.jpg'],
                        'url' => ['url' => 'https://example.com/source'],
                        // Content passed as array
                        'content' => ['rendered' => 'Nested content body'],
                    ],
                ],
            ], 200),
        ]);

        $service = app(NewsIngestionService::class);
        $articles = $service->fetchFromProvider($config);

        $this->assertCount(1, $articles);
        $this->assertEquals('Complex Title From Nested Array', $articles[0]['title']);
        $this->assertEquals('Summary in array structure', $articles[0]['summary']);
        $this->assertEquals('https://example.com/nested.jpg', $articles[0]['image']);

        $imported = $service->importArticles($articles, $config->name);
        $this->assertEquals(1, $imported);

        $record = NewsArticle::where('title', 'Complex Title From Nested Array')->first();
        $this->assertNotNull($record);
        $this->assertStringContainsString('Nested content body', $record->content);
    }

    public function test_fetch_raw_previews_and_import_selected_articles_only(): void
    {
        $config = NewsApiConfig::create([
            'name' => 'Selective News Provider',
            'endpoint_url' => 'https://api.cricket.test/selective-news',
            'auth_type' => 'none',
            'is_active' => true,
        ]);

        Http::fake([
            'https://api.cricket.test/selective-news*' => Http::response([
                'data' => [
                    [
                        'title' => 'Article 1 to skip',
                        'summary' => 'Summary 1',
                    ],
                    [
                        'title' => 'Article 2 to keep',
                        'summary' => 'Summary 2',
                        'image' => 'https://example.com/img2.jpg',
                    ],
                    [
                        'title' => 'Article 3 to skip',
                        'summary' => 'Summary 3',
                    ],
                ],
            ], 200),
        ]);

        $service = app(NewsIngestionService::class);
        $previews = $service->fetchRawPreviews($config);

        // Previews do not insert to database
        $this->assertCount(3, $previews);
        $this->assertEquals(0, NewsArticle::count());

        // Import only Article 2
        $selectedOnly = [$previews[1]];
        $importedCount = $service->importSelectedArticles($selectedOnly, $config->name);

        $this->assertEquals(1, $importedCount);
        $this->assertEquals(1, NewsArticle::count());
        $this->assertTrue(NewsArticle::where('title', 'Article 2 to keep')->exists());
        $this->assertFalse(NewsArticle::where('title', 'Article 1 to skip')->exists());
    }
}
