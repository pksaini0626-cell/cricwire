{!! '<' . '?xml version="1.0" encoding="UTF-8"?' . '>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"
        xmlns:news="http://www.google.com/schemas/sitemap-news/0.9">
    @foreach($articles as $article)
    <url>
        <loc>{{ url('/cricket-news/' . $article->slug) }}</loc>
        <news:news>
            <news:publication>
                <news:name>{{ config('app.name', 'CricketFans') }}</news:name>
                <news:language>en</news:language>
            </news:publication>
            <news:publication_date>{{ ($article->published_at ?? $article->created_at)->toAtomString() }}</news:publication_date>
            <news:title>{{ $article->title }}</news:title>
            @if($article->seo_keywords)
            <news:keywords>{{ $article->seo_keywords }}</news:keywords>
            @endif
        </news:news>
    </url>
    @endforeach
</urlset>

