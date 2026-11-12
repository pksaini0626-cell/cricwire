<?php

namespace App\Services\News;

use App\Models\NewsApiConfig;
use App\Models\NewsArticle;
use Exception;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class NewsIngestionService
{
    /**
     * Last HTTP status received during fetchFromProvider execution.
     */
    protected ?int $lastHttpStatus = null;

    /**
     * Get the last HTTP status code from an API call attempt.
     */
    public function getLastHttpStatus(): ?int
    {
        return $this->lastHttpStatus;
    }

    /**
     * Safely extract and sanitize a string from primitive, array, or object values.
     */
    protected function sanitizeString(mixed $value, string $default = ''): string
    {
        if (is_string($value)) {
            return trim($value);
        }

        if (is_array($value)) {
            // Handle common API patterns like ['rendered' => '...', 'url' => '...', 'value' => '...']
            $extracted = $value['url'] 
                ?? $value['rendered'] 
                ?? $value['src'] 
                ?? $value['value'] 
                ?? $value['title'] 
                ?? $value['text'] 
                ?? (isset($value[0]) ? reset($value) : null);

            if (is_string($extracted)) {
                return trim($extracted);
            }

            if (is_array($extracted)) {
                return $this->sanitizeString($extracted, $default);
            }

            return !is_null($extracted) ? (string) $extracted : $default;
        }

        return !is_null($value) ? trim((string) $value) : $default;
    }

    /**
     * Fetch news from a given NewsApiConfig provider and map raw items to a normalized array.
     *
     * @param NewsApiConfig $config
     * @return array<int, array{id?: string, title: string, summary: ?string, image: ?string, source_url: ?string, raw_content: ?string}>
     */
    public function fetchFromProvider(NewsApiConfig $config): array
    {
        $this->lastHttpStatus = null;

        $headers = [
            'Accept' => 'application/json',
        ];

        // Format authentication headers
        if (!empty($config->api_key_value)) {
            $apiKey = trim((string) $config->api_key_value);
            $headerKey = !empty($config->api_key_header) ? trim($config->api_key_header) : 'x-rapidapi-key';

            if ($config->auth_type === 'bearer') {
                $headers['Authorization'] = 'Bearer ' . $apiKey;
            } elseif ($config->auth_type !== 'query_param') {
                // Default / header auth
                $headers[$headerKey] = $apiKey;
            }
        }

        // Additional headers configured in DB (e.g. x-rapidapi-host)
        if (!empty($config->additional_headers) && is_array($config->additional_headers)) {
            foreach ($config->additional_headers as $hName => $hVal) {
                if (!empty($hName)) {
                    $headers[trim((string) $hName)] = trim((string) $hVal);
                }
            }
        }

        $queryParams = [];
        if ($config->auth_type === 'query_param' && !empty($config->api_key_value)) {
            $paramKey = !empty($config->api_key_header) ? trim($config->api_key_header) : 'api_key';
            $queryParams[$paramKey] = trim((string) $config->api_key_value);
        }

        try {
            $response = Http::withHeaders($headers)
                ->timeout(15)
                ->get($config->endpoint_url, $queryParams);

            $this->lastHttpStatus = $response->status();

            Log::info("News API Attempt [{$config->name}]", [
                'status' => $response->status(),
                'body_snippet' => substr($response->body(), 0, 500),
            ]);

            if (!$response->successful()) {
                return [];
            }

            $json = $response->json();
            $rawItems = [];

            if (isset($json['data']) && is_array($json['data'])) {
                $rawItems = $json['data']['news'] ?? $json['data'];
            } elseif (isset($json['news']) && is_array($json['news'])) {
                $rawItems = $json['news'];
            } elseif (isset($json['articles']) && is_array($json['articles'])) {
                $rawItems = $json['articles'];
            } elseif (isset($json['storyList']) && is_array($json['storyList'])) {
                $rawItems = $json['storyList'];
            } elseif (is_array($json) && array_is_list($json)) {
                $rawItems = $json;
            }

            $articles = [];
            foreach ($rawItems as $item) {
                if (!is_array($item)) {
                    continue;
                }

                if (isset($item['story']) && is_array($item['story'])) {
                    $item = $item['story'];
                }

                $rawImage = $item['image'] ?? $item['image_url'] ?? $item['imageUrl'] ?? $item['banner'] ?? $item['urlToImage'] ?? null;
                if (empty($rawImage) && isset($item['imageId'])) {
                    $rawImage = "https://static.cricbuzz.com/images/{$item['imageId']}.jpg";
                }

                $title = $this->sanitizeString($item['title'] ?? $item['headline'] ?? $item['news_title'] ?? $item['name'] ?? null);
                $summary = $this->sanitizeString($item['summary'] ?? $item['description'] ?? $item['intro'] ?? $item['snippet'] ?? null);
                $image = $this->sanitizeString($rawImage);
                $sourceUrl = $this->sanitizeString($item['url'] ?? $item['source_url'] ?? $item['link'] ?? $item['web_url'] ?? null);
                $rawContent = $this->sanitizeString($item['content'] ?? $item['details'] ?? $item['description'] ?? $item['raw_content'] ?? null);

                $articles[] = [
                    'id' => md5($title . $sourceUrl),
                    'title' => $title,
                    'summary' => !empty($summary) ? $summary : null,
                    'image' => !empty($image) ? $image : null,
                    'source_url' => !empty($sourceUrl) ? $sourceUrl : null,
                    'raw_content' => !empty($rawContent) ? $rawContent : (!empty($summary) ? $summary : null),
                ];
            }

            return array_values(array_filter($articles, fn ($a) => !empty($a['title'])));
        } catch (Exception $e) {
            $this->lastHttpStatus = 0;

            Log::error("NewsIngestionService: Exception calling provider [{$config->name}]", [
                'message' => $e->getMessage(),
            ]);

            return [];
        }
    }

    /**
     * Fetch raw preview articles without persisting anything to the database.
     *
     * @param NewsApiConfig $config
     * @return array<int, array{id: string, title: string, summary: ?string, image: ?string, source_url: ?string, raw_content: ?string}>
     */
    public function fetchRawPreviews(NewsApiConfig $config): array
    {
        return $this->fetchFromProvider($config);
    }

    /**
     * Save only selected articles into news_articles as draft status.
     * Skips duplicates based on title and slug.
     *
     * @param array<int, array{title: string, summary: ?string, image: ?string, source_url: ?string, raw_content: ?string}> $selectedItems
     * @param string $sourceName
     * @return int Number of newly imported articles
     */
    public function importSelectedArticles(array $selectedItems, string $sourceName): int
    {
        return $this->importArticles($selectedItems, $sourceName);
    }

    /**
     * Import normalized articles into news_articles as draft status, skipping duplicates based on title/slug.
     *
     * @param array<int, array{title: string, summary: ?string, image: ?string, source_url: ?string, raw_content: ?string}> $articles
     * @param string $sourceName
     * @return int Number of newly imported articles
     */
    public function importArticles(array $articles, string $sourceName): int
    {
        $importedCount = 0;

        foreach ($articles as $article) {
            $title = $this->sanitizeString($article['title'] ?? null);
            if (empty($title)) {
                continue;
            }

            $baseSlug = Str::slug($title);
            if (empty($baseSlug)) {
                $baseSlug = 'news-' . Str::random(8);
            }

            // Duplicate protection: Check if title or slug already exists
            $exists = NewsArticle::where('title', $title)
                ->orWhere('slug', $baseSlug)
                ->exists();

            if ($exists) {
                continue;
            }

            $slug = $baseSlug;
            $counter = 1;
            while (NewsArticle::where('slug', $slug)->exists()) {
                $slug = "{$baseSlug}-{$counter}";
                $counter++;
            }

            $summary = $this->sanitizeString($article['summary'] ?? null);
            $rawContent = $this->sanitizeString($article['raw_content'] ?? null);
            $image = $this->sanitizeString($article['image'] ?? null);
            $sourceUrl = $this->sanitizeString($article['source_url'] ?? null);

            $content = !empty($rawContent) ? $rawContent : (!empty($summary) ? $summary : $title);

            // Format HTML paragraphs for rich text rendering if plain text
            $haystack = is_string($content) ? $content : (string) $content;
            if (!str_contains($haystack, '<p>') && !str_contains($haystack, '<div>')) {
                $content = '<p>' . nl2br(e($haystack)) . '</p>';
            }

            NewsArticle::create([
                'title' => $title,
                'slug' => $slug,
                'category' => 'news',
                'featured_image' => !empty($image) ? $image : null,
                'summary' => !empty($summary) ? $summary : null,
                'content' => $content,
                'source_name' => $sourceName,
                'source_url' => !empty($sourceUrl) ? $sourceUrl : null,
                'status' => 'draft',
                'is_published' => false,
                'published_at' => null,
                'seo_meta_title' => Str::limit($title, 60),
                'seo_meta_description' => Str::limit(!empty($summary) ? $summary : $title, 160),
            ]);

            $importedCount++;
        }

        return $importedCount;
    }
}
