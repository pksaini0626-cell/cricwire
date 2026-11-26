<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class NewsArticle extends Model
{
    use HasFactory;

    protected $fillable = [
        'author_id',
        'user_id',
        'series_id',
        'series_slug',
        'series_name',
        'match_id',
        'title',
        'slug',
        'category',
        'featured_image',
        'summary',
        'content',
        'source_name',
        'source_url',
        'status',
        'is_guest_post',
        'author_name',
        'author_bio',
        'author_social_url',
        'rejection_reason',
        'youtube_video_url',
        'seo_meta_title',
        'seo_meta_description',
        'seo_keywords',
        'is_published',
        'is_featured',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'is_featured' => 'boolean',
            'is_guest_post' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::saving(function (NewsArticle $article) {
            if ($article->isDirty('status') || $article->is_guest_post) {
                $article->is_published = ($article->status === 'published');
            }

            if ($article->is_guest_post && !empty($article->content)) {
                $article->content = self::sanitizeGuestHtml($article->content);
            }
        });
    }

    public function author()
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Sanitize guest article HTML and enforce rel="nofollow noopener noreferrer" target="_blank" on external links.
     */
    public function getSanitizedContentAttribute(): string
    {
        if (empty($this->content)) {
            return '';
        }

        return $this->is_guest_post
            ? self::sanitizeGuestHtml($this->content)
            : $this->content;
    }

    /**
     * Strip unsafe tags/attributes and enforce SEO link protection on external links.
     */
    public static function sanitizeGuestHtml(?string $html): string
    {
        $html = trim((string) $html);
        if ($html === '') {
            return '';
        }

        // Convert plain-text line breaks into paragraphs if no block-level tags are present
        if (!preg_match('/<(p|h[1-6]|div|ul|ol|li|blockquote|pre|br)\b/i', $html)) {
            $blocks = preg_split("/\r\n\r\n|\n\n|\r\r/", $html) ?: [$html];
            $html = implode("\n", array_map(function (string $block) {
                $block = trim($block);
                if ($block === '') {
                    return '';
                }
                return '<p>' . nl2br($block, false) . '</p>';
            }, $blocks));
        }

        $previousErrors = libxml_use_internal_errors(true);

        $dom = new \DOMDocument('1.0', 'UTF-8');
        $wrapped = '<?xml encoding="utf-8" ?><div id="__guest_root__">' . $html . '</div>';
        $dom->loadHTML($wrapped, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        // Remove dangerous tags completely along with their contents
        $forbiddenTags = ['script', 'iframe', 'style', 'object', 'embed', 'form', 'input', 'textarea', 'button', 'select', 'svg', 'meta', 'link', 'base', 'noscript'];
        foreach ($forbiddenTags as $tag) {
            $nodes = $dom->getElementsByTagName($tag);
            for ($i = $nodes->length - 1; $i >= 0; $i--) {
                $node = $nodes->item($i);
                $node?->parentNode?->removeChild($node);
            }
        }

        $allowedTags = ['p', 'br', 'h2', 'h3', 'h4', 'strong', 'b', 'em', 'i', 'u', 's', 'blockquote', 'ul', 'ol', 'li', 'a', 'code', 'pre', 'hr'];
        $appHost = strtolower((string) (parse_url((string) config('app.url', 'https://cricwire.in'), PHP_URL_HOST) ?: 'cricwire.in'));
        $internalHosts = array_unique(array_filter([$appHost, 'cricwire.in', 'www.cricwire.in']));

        $root = $dom->getElementById('__guest_root__');
        if ($root) {
            $allElements = [];
            foreach ($root->getElementsByTagName('*') as $el) {
                $allElements[] = $el;
            }

            // Traverse bottom-up so unwrapping nested tags preserves children safely
            foreach (array_reverse($allElements) as $el) {
                $tagName = strtolower($el->tagName);

                if (!in_array($tagName, $allowedTags, true)) {
                    // Unwrap disallowed tag while keeping child nodes
                    $parent = $el->parentNode;
                    if ($parent) {
                        while ($el->firstChild) {
                            $parent->insertBefore($el->firstChild, $el);
                        }
                        $parent->removeChild($el);
                    }
                    continue;
                }

                // Strip all attributes first (except href/title on <a>)
                $attrsToRemove = [];
                foreach ($el->attributes as $attr) {
                    $attrName = strtolower($attr->nodeName);
                    if ($tagName === 'a' && in_array($attrName, ['href', 'title'], true)) {
                        continue;
                    }
                    $attrsToRemove[] = $attr->nodeName;
                }
                foreach ($attrsToRemove as $attrName) {
                    $el->removeAttribute($attrName);
                }

                if ($tagName === 'a') {
                    $href = trim((string) $el->getAttribute('href'));
                    $isSafeUrl = preg_match('/^https?:\/\//i', $href) === 1
                        || (str_starts_with($href, '/') && !str_starts_with($href, '//'));

                    if (!$isSafeUrl || $href === '') {
                        $parent = $el->parentNode;
                        if ($parent) {
                            while ($el->firstChild) {
                                $parent->insertBefore($el->firstChild, $el);
                            }
                            $parent->removeChild($el);
                        }
                        continue;
                    }

                    $linkHost = strtolower((string) parse_url($href, PHP_URL_HOST));
                    if ($linkHost !== '' && !in_array($linkHost, $internalHosts, true)) {
                        $el->setAttribute('rel', 'nofollow noopener noreferrer');
                        $el->setAttribute('target', '_blank');
                    }
                }
            }
        }

        $output = '';
        if ($root) {
            foreach ($root->childNodes as $child) {
                $output .= $dom->saveHTML($child);
            }
        }

        libxml_clear_errors();
        libxml_use_internal_errors($previousErrors);

        return trim($output);
    }

    public function series()
    {
        return $this->belongsTo(Series::class, 'series_id');
    }

    public function match()
    {
        return $this->belongsTo(CricketMatch::class, 'match_id');
    }

    /**
     * Resolve the featured image URL whether stored locally or an external URL.
     */
    public function getResolvedImageUrlAttribute(): ?string
    {
        if (empty($this->featured_image)) {
            return null;
        }

        if (filter_var($this->featured_image, FILTER_VALIDATE_URL)) {
            return $this->featured_image;
        }

        // Handle path with or without storage prefix
        $cleanPath = ltrim($this->featured_image, '/');
        if (str_starts_with($cleanPath, 'storage/')) {
            return asset($cleanPath);
        }

        return asset('storage/' . $cleanPath);
    }
}
