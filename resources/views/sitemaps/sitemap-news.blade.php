{!! '<' . '?xml version="1.0" encoding="UTF-8"?' . '>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">
    @foreach($freshArticles as $article)
    <url>
        <loc>{{ route('news.show', $article->slug) }}</loc>
        <news:news>
            <news:publication>
                <news:name>{{ \App\Support\SeoConfig::get('seo_org_name', 'Cricwire') }}</news:name>
                <news:language>en</news:language>
            </news:publication>
            <news:publication_date>{{ ($article->published_at ?? $article->created_at)->toAtomString() }}</news:publication_date>
            <news:title>{{ $article->title }}</news:title>
            @if($article->seo_keywords)
            <news:keywords>{{ $article->seo_keywords }}</news:keywords>
            @endif
        </news:news>
        <lastmod>{{ ($article->updated_at ?? $article->created_at)->toAtomString() }}</lastmod>
        <changefreq>hourly</changefreq>
        <priority>0.9</priority>
    </url>
    @endforeach

    @foreach($olderArticles as $article)
    <url>
        <loc>{{ route('news.show', $article->slug) }}</loc>
        <lastmod>{{ ($article->updated_at ?? $article->created_at)->toAtomString() }}</lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.7</priority>
    </url>
    @endforeach
</urlset>
