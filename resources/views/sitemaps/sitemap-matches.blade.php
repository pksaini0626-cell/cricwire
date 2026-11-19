{!! '<' . '?xml version="1.0" encoding="UTF-8"?' . '>' !!}
<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">
    @foreach($matches as $m)
        @php
            $matchStatus = strtolower((string) ($m['status'] ?? ''));
            $isLiveOrUpcoming = in_array($matchStatus, ['live', 'in_progress', 'upcoming', 'scheduled', 'inprogress'], true);
            $changefreq = $isLiveOrUpcoming ? 'always' : 'weekly';
            $priority = $isLiveOrUpcoming ? '0.9' : '0.6';
        @endphp
        @if(!empty($m['primary_url']))
        <url>
            <loc>{{ $m['primary_url'] }}</loc>
            <changefreq>{{ $changefreq }}</changefreq>
            <priority>{{ $priority }}</priority>
            <lastmod>{{ now()->toAtomString() }}</lastmod>
        </url>
        @endif
        @if(!empty($m['info_url']) && $m['info_url'] !== ($m['primary_url'] ?? ''))
        <url>
            <loc>{{ $m['info_url'] }}</loc>
            <changefreq>{{ $changefreq }}</changefreq>
            <priority>{{ $priority }}</priority>
            <lastmod>{{ now()->toAtomString() }}</lastmod>
        </url>
        @endif
    @endforeach
</urlset>
