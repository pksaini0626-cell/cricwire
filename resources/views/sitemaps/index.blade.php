{!! '<' . '?xml version="1.0" encoding="UTF-8"?' . '>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    <url>
        <loc>{{ url('/') }}</loc>
        <changefreq>always</changefreq>
        <priority>1.0</priority>
    </url>
    <url>
        <loc>{{ url('/live-scores') }}</loc>
        <changefreq>always</changefreq>
        <priority>0.9</priority>
    </url>
    <url>
        <loc>{{ url('/cricket-schedule') }}</loc>
        <changefreq>daily</changefreq>
        <priority>0.8</priority>
    </url>
    <url>
        <loc>{{ url('/cricket-rankings') }}</loc>
        <changefreq>daily</changefreq>
        <priority>0.8</priority>
    </url>
    <url>
        <loc>{{ url('/cricket-news') }}</loc>
        <changefreq>hourly</changefreq>
        <priority>0.8</priority>
    </url>
    <url>
        <loc>{{ url('/polls') }}</loc>
        <changefreq>daily</changefreq>
        <priority>0.7</priority>
    </url>

    @foreach($matches as $match)
    <url>
        <loc>{{ \App\Services\Cricket\CricketUrlService::getMatchUrl($match, 'live-line') }}</loc>
        <lastmod>{{ $match->updated_at->toAtomString() }}</lastmod>
        <changefreq>{{ $match->status === 'live' ? 'always' : 'daily' }}</changefreq>
        <priority>{{ $match->is_pinned ? '0.9' : '0.8' }}</priority>
    </url>
    @endforeach

    @foreach($seriesList as $series)
    <url>
        <loc>{{ url('/cricket-series/' . $series->slug . '/overview') }}</loc>
        <lastmod>{{ $series->updated_at->toAtomString() }}</lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.7</priority>
    </url>
    @endforeach

    @foreach($articles as $article)
    <url>
        <loc>{{ url('/cricket-news/' . $article->slug) }}</loc>
        <lastmod>{{ $article->updated_at->toAtomString() }}</lastmod>
        <changefreq>weekly</changefreq>
        <priority>0.7</priority>
    </url>
    @endforeach
</urlset>

