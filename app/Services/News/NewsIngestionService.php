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
     * Last error message or status summary from an API call attempt.
     */
    protected ?string $lastErrorMessage = null;

    /**
     * Get the last HTTP status code from an API call attempt.
     */
    public function getLastHttpStatus(): ?int
    {
        return $this->lastHttpStatus;
    }

    /**
     * Get the last error message from an API call attempt.
     */
    public function getLastErrorMessage(): ?string
    {
        return $this->lastErrorMessage;
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
        $this->lastErrorMessage = null;

        $headers = [
            'Accept' => 'application/json, application/xml, text/xml, */*',
        ];

        // Format authentication headers based on auth_type
        $authType = $config->auth_type ?? 'none';
        $apiKey = !empty($config->api_key_value) ? trim((string) $config->api_key_value) : '';
        $headerKey = !empty($config->api_key_header) ? trim((string) $config->api_key_header) : 'x-rapidapi-key';

        if ($authType === 'bearer' && !empty($apiKey)) {
            $headers['Authorization'] = 'Bearer ' . $apiKey;
        } elseif ($authType === 'header' && !empty($apiKey)) {
            $headers[$headerKey] = $apiKey;
        }

        // Additional headers configured in DB (e.g. x-rapidapi-host)
        if (!empty($config->additional_headers)) {
            $additionalHeaders = is_array($config->additional_headers)
                ? $config->additional_headers
                : json_decode((string) $config->additional_headers, true);

            if (is_array($additionalHeaders)) {
                foreach ($additionalHeaders as $hName => $hVal) {
                    if (!empty($hName)) {
                        $headers[trim((string) $hName)] = trim((string) $hVal);
                    }
                }
            }
        }

        $queryParams = [];
        if ($authType === 'query_param' && !empty($apiKey)) {
            $paramKey = !empty($config->api_key_header) ? trim((string) $config->api_key_header) : 'api_key';
            $queryParams[$paramKey] = $apiKey;
        }

        try {
            $client = Http::withHeaders($headers)->timeout(15);

            if ($authType === 'bearer' && !empty($apiKey)) {
                $client = $client->withToken($apiKey);
            }

            $response = $client->get($config->endpoint_url, $queryParams);

            $this->lastHttpStatus = $response->status();

            if (!$response->successful()) {
                $status = $response->status();
                $bodySnippet = substr($response->body(), 0, 500);

                Log::warning("News API Provider [{$config->name}] returned HTTP {$status}", [
                    'status' => $status,
                    'body_snippet' => $bodySnippet,
                ]);

                // Try to extract user-friendly error message if json
                $errorMsg = "HTTP {$status}";
                $errJson = $response->json();
                if (is_array($errJson)) {
                    $msg = $errJson['message'] ?? $errJson['error'] ?? $errJson['detail'] ?? null;
                    if ($msg) {
                        $errorMsg .= ': ' . $this->sanitizeString($msg);
                    }
                }
                $this->lastErrorMessage = $errorMsg;

                return [];
            }

            $body = trim($response->body());

            // Auto-detect XML / RSS feeds (e.g. ESPNcricinfo, NDTV, BBC, etc.)
            if (Str::startsWith($body, ['<?xml', '<rss', '<feed']) || str_contains(substr($body, 0, 100), '<rss')) {
                $articles = $this->parseXmlFeed($body);
                if (empty($articles)) {
                    Log::warning("News API [{$config->name}] RSS/XML parsed 0 articles", [
                        'status' => $response->status(),
                    ]);
                    $this->lastErrorMessage = 'Feed contained no readable news items.';
                }
                return $articles;
            }

            $json = $response->json();
            $rawItems = [];

            if (is_array($json)) {
                $rawItems = $json['data']['news'] 
                    ?? $json['data']['articles'] 
                    ?? $json['data'] 
                    ?? $json['news'] 
                    ?? $json['articles'] 
                    ?? $json['storyList'] 
                    ?? $json;
            }

            if (!is_array($rawItems) || !array_is_list($rawItems)) {
                // If it's an associative array or unexpected structure, check if wrapped
                if (is_array($rawItems)) {
                    // Check if values inside are articles
                    $firstVal = reset($rawItems);
                    if (is_array($firstVal) && (isset($firstVal['title']) || isset($firstVal['headline']))) {
                        $rawItems = array_values($rawItems);
                    } else {
                        $rawItems = [];
                    }
                } else {
                    $rawItems = [];
                }
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

            $cleanArticles = array_values(array_filter($articles, fn ($a) => !empty($a['title'])));

            if (empty($cleanArticles)) {
                Log::warning("News API [{$config->name}] returned 0 parsed articles", [
                    'status' => $response->status(),
                    'raw_items_count' => count($rawItems),
                    'body_snippet' => substr($response->body(), 0, 500),
                ]);
                $this->lastErrorMessage = 'Provider responded successfully (200 OK) but no news articles were matched.';
            }

            return $cleanArticles;
        } catch (Exception $e) {
            $this->lastHttpStatus = 0;
            $this->lastErrorMessage = $e->getMessage();

            Log::error("NewsIngestionService: Exception calling provider [{$config->name}]", [
                'message' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);

            return [];
        }
    }

    /**
     * Parse RSS/Atom/XML feeds into normalized articles.
     */
    protected function parseXmlFeed(string $xmlContent): array
    {
        $articles = [];

        try {
            // Suppress XML errors and handle gracefully
            libxml_use_internal_errors(true);
            $xml = simplexml_load_string($xmlContent, 'SimpleXMLElement', LIBXML_NOCDATA);

            if ($xml === false) {
                return [];
            }

            // Register namespaces for media/enclosures if present
            $namespaces = $xml->getNamespaces(true);

            // Channel items (RSS 2.0)
            $items = $xml->channel->item ?? $xml->item ?? $xml->entry ?? [];

            foreach ($items as $item) {
                $title = $this->sanitizeString((string) ($item->title ?? ''));
                if (empty($title)) {
                    continue;
                }

                $summary = $this->sanitizeString((string) ($item->description ?? $item->summary ?? ''));
                $sourceUrl = $this->sanitizeString((string) ($item->link ?? ''));

                if (empty($sourceUrl) && isset($item->link['href'])) {
                    $sourceUrl = (string) $item->link['href'];
                }

                $image = null;

                // Check enclosure
                if (isset($item->enclosure) && isset($item->enclosure['url'])) {
                    $image = (string) $item->enclosure['url'];
                }

                // Check media:content or media:thumbnail
                if (empty($image) && isset($namespaces['media'])) {
                    $media = $item->children($namespaces['media']);
                    if (isset($media->content) && isset($media->content->attributes()->url)) {
                        $image = (string) $media->content->attributes()->url;
                    } elseif (isset($media->thumbnail) && isset($media->thumbnail->attributes()->url)) {
                        $image = (string) $media->thumbnail->attributes()->url;
                    }
                }

                $articles[] = [
                    'id' => md5($title . $sourceUrl),
                    'title' => $title,
                    'summary' => !empty($summary) ? strip_tags($summary) : null,
                    'image' => !empty($image) ? trim($image) : null,
                    'source_url' => !empty($sourceUrl) ? trim($sourceUrl) : null,
                    'raw_content' => !empty($summary) ? $summary : null,
                ];
            }
        } catch (Exception $e) {
            Log::error('NewsIngestionService: Failed parsing XML feed: ' . $e->getMessage());
        }

        return $articles;
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
