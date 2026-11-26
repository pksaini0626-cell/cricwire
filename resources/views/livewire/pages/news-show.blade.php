@php
    $ogImage = !empty($article->featured_image)
        ? (\Illuminate\Support\Str::startsWith($article->featured_image, 'http')
            ? $article->featured_image
            : asset('storage/' . ltrim($article->featured_image, '/')))
        : (file_exists(public_path('images/cricwire-default-og.jpg'))
            ? asset('images/cricwire-default-og.jpg')
            : asset('images/og-image.png'));
@endphp

@push('meta')
    <meta property="og:type" content="article" />
    <meta property="og:site_name" content="Cricwire" />
    <meta property="og:title" content="{{ $article->seo_meta_title ?? $article->title }}" />
    <meta property="og:description" content="{{ \Illuminate\Support\Str::limit(strip_tags($article->seo_meta_description ?? $article->summary ?? $article->content), 160) }}" />
    <meta property="og:url" content="{{ url()->current() }}" />
    <meta property="og:image" content="{{ $ogImage }}" />
    <meta property="og:image:secure_url" content="{{ $ogImage }}" />
    <meta property="og:image:width" content="1200" />
    <meta property="og:image:height" content="630" />
    <meta property="og:image:alt" content="{{ $article->title }}" />

    <meta name="twitter:card" content="summary_large_image" />
    <meta name="twitter:title" content="{{ $article->seo_meta_title ?? $article->title }}" />
    <meta name="twitter:description" content="{{ \Illuminate\Support\Str::limit(strip_tags($article->seo_meta_description ?? $article->summary ?? $article->content), 160) }}" />
    <meta name="twitter:image" content="{{ $ogImage }}" />
@endpush

<div class="min-h-screen bg-slate-950 text-slate-100 font-sans antialiased selection:bg-amber-500 selection:text-black pb-24 flex flex-col">
    <!-- Top Global Header -->
    <x-header />

    <!-- Breadcrumbs / Top Navigation Strip -->
    <nav class="border-b border-slate-800/80 bg-slate-900/40 backdrop-blur py-2.5 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto flex items-center justify-between text-xs text-slate-400">
            <div class="flex items-center space-x-2 truncate">
                <a href="{{ url('/') }}" wire:navigate class="hover:text-amber-400 transition font-medium">Home</a>
                <span class="text-slate-600">/</span>
                <a href="{{ route('news.index') }}" wire:navigate class="hover:text-amber-400 transition font-medium">Cricket News</a>
                <span class="text-slate-600">/</span>
                <span class="text-slate-300 truncate max-w-xs sm:max-w-md font-normal">{{ $article->title }}</span>
            </div>

            <!-- Share Action Button -->
            <button 
                type="button" 
                onclick="navigator.share ? navigator.share({title: document.title, url: window.location.href}) : navigator.clipboard.writeText(window.location.href)"
                class="flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-800/80 hover:bg-slate-800 text-slate-300 hover:text-amber-400 text-xs font-semibold transition shrink-0 ml-3"
                title="Share this article"
            >
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8.684 13.342C8.886 12.938 9 12.482 9 12c0-.482-.114-.938-.316-1.342m0 2.684a3 3 0 110-2.684m0 2.684l6.632 3.316m-6.632-6l6.632-3.316m0 0a3 3 0 105.367-2.684 3 3 0 00-5.367 2.684zm0 9.316a3 3 0 105.368 2.684 3 3 0 00-5.368-2.684z" />
                </svg>
                <span class="text-[11px] font-bold">Share</span>
            </button>
        </div>
    </nav>

    @php
        $categoryBadgeColor = match($article->category) {
            'pitch_report' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
            'prediction' => 'bg-purple-500/10 text-purple-400 border-purple-500/30',
            'fantasy_tips' => 'bg-rose-500/10 text-rose-400 border-rose-500/30',
            default => 'bg-amber-400/10 text-amber-400 border-amber-400/30',
        };

        $categoryLabel = match($article->category) {
            'pitch_report' => 'Pitch Report',
            'prediction' => 'Match Prediction',
            'fantasy_tips' => 'Fantasy Tips',
            default => 'Cricket News',
        };

        $imageUrl = $article->resolved_image_url;

        // Categories list for sidebar quick links
        $sidebarCategories = [
            ['key' => 'all', 'label' => 'All Stories', 'icon' => '📰'],
            ['key' => 'news', 'label' => 'Breaking News', 'icon' => '⚡'],
            ['key' => 'pitch_report', 'label' => 'Pitch Reports', 'icon' => '🏟️'],
            ['key' => 'prediction', 'label' => 'Match Predictions', 'icon' => '🔮'],
            ['key' => 'fantasy_tips', 'label' => 'Fantasy XI Tips', 'icon' => '⭐'],
        ];

        // Split HTML paragraphs to inject mid-article ad (sanitize guest post links/HTML)
        $articleContent = $article->is_guest_post ? $article->sanitized_content : ($article->content ?? '');
        $paragraphs = preg_split('/(<\/p>|<\/div>)/i', $articleContent, -1, PREG_SPLIT_DELIM_CAPTURE);
        $totalParts = count($paragraphs);
        $insertIndex = (int) floor($totalParts / 2);
        if ($insertIndex % 2 !== 0 && $insertIndex > 0) {
            $insertIndex--;
        }
    @endphp

    <!-- Main Editorial Container (2-Column Grid on lg) -->
    <main class="max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 pt-6 flex-1">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

            <!-- LEFT COLUMN: Main Article (lg:col-span-8) -->
            <article class="lg:col-span-8 space-y-6">

                <!-- Category Pill & Timestamps -->
                <div class="flex flex-wrap items-center justify-between gap-3 pb-1 border-b border-white/5">
                    <div class="flex items-center gap-2.5">
                        <a 
                            href="{{ route('news.index', ['category' => $article->category]) }}" 
                            wire:navigate 
                            class="px-3 py-1 rounded-full text-xs font-bold uppercase tracking-wider border hover:opacity-85 transition {{ $categoryBadgeColor }}"
                        >
                            {{ strtoupper($article->category ?? 'Cricket News') }}
                        </a>

                        <span class="text-xs text-slate-400 flex items-center gap-1 font-mono">
                            <span>⏱️</span>
                            <span>{{ $article->published_at ? $article->published_at->format('M d, Y • h:i A') : $article->created_at->format('M d, Y') }}</span>
                        </span>
                    </div>

                    <span class="text-xs text-slate-500 font-mono">
                        {{ $article->published_at ? $article->published_at->diffForHumans() : 'Recent' }}
                    </span>
                </div>

                <!-- Editorial Headline -->
                <h1 class="text-2xl sm:text-3xl lg:text-4xl font-black text-white leading-tight tracking-tight drop-shadow-sm font-sans">
                    {{ $article->title }}
                </h1>

                <!-- Author / Source Byline Strip -->
                <div class="flex items-center justify-between py-3 border-y border-slate-800/80 text-xs sm:text-sm text-slate-300">
                    <div class="flex items-center space-x-3">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-tr from-amber-500 to-yellow-400 flex items-center justify-center text-xs font-bold text-slate-950 shadow-sm">
                            ✍️
                        </div>
                        <div>
                            <span class="font-extrabold text-white block">
                                {{ $article->is_guest_post && $article->author_name ? $article->author_name : 'CricWire News Agency' }}
                            </span>
                            <span class="text-[11px] {{ $article->is_guest_post ? 'text-emerald-400/90' : 'text-amber-400/90' }} block -mt-0.5 flex items-center gap-1 font-medium">
                                <svg class="w-3 h-3 {{ $article->is_guest_post ? 'text-emerald-400' : 'text-amber-400' }} inline" fill="currentColor" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M10 18a8 8 0 100-16 8 8 0 000 16zm3.707-9.293a1 1 0 00-1.414-1.414L9 10.586 7.707 9.293a1 1 0 00-1.414 1.414l2 2a1 1 0 001.414 0l4-4z" clip-rule="evenodd"/>
                                </svg>
                                {{ $article->is_guest_post ? 'Community Cricket Contributor' : 'Official Editorial Desk' }}
                            </span>
                        </div>
                    </div>

                    @if($article->source_url)
                        <a 
                            href="{{ $article->source_url }}" 
                            target="_blank" 
                            rel="noopener noreferrer nofollow" 
                            class="inline-flex items-center gap-1 px-2.5 py-1 rounded-lg bg-slate-900 border border-slate-800 hover:border-amber-400/50 text-amber-400 text-xs font-semibold transition"
                        >
                            <span>Source</span>
                            <span class="text-[10px]">↗</span>
                        </a>
                    @endif
                </div>

                <!-- Top Social Share Bar (Under Headline & Byline) -->
                <x-news-social-share-bar :article="$article" />

                <!-- Featured Hero Image -->
                @if($imageUrl)
                    <div class="relative w-full h-72 sm:h-96 rounded-2xl overflow-hidden bg-slate-900 border border-white/10 shadow-2xl">
                        <img 
                            src="{{ $imageUrl }}" 
                            alt="{{ $article->title }}" 
                            loading="lazy"
                            crossorigin="anonymous"
                            referrerpolicy="no-referrer"
                            onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?auto=format&fit=crop&w=1200&q=80';"
                            class="w-full h-full object-cover"
                        />
                        <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-transparent to-transparent pointer-events-none"></div>

                        <span class="absolute bottom-3 right-3 px-2 py-0.5 rounded text-[10px] font-mono font-bold bg-slate-950/80 border border-white/10 text-slate-300 backdrop-blur-md">
                            Featured Editorial
                        </span>
                    </div>
                @endif

                <!-- Summary Lead Box -->
                @if($article->summary)
                    <div class="p-4 sm:p-5 rounded-2xl bg-gradient-to-r from-amber-500/10 via-amber-500/5 to-transparent border-l-4 border-amber-400 border-y border-r border-slate-800 text-slate-200 text-sm sm:text-base font-medium leading-relaxed italic shadow-inner">
                        {{ $article->summary }}
                    </div>
                @endif

                <!-- In-Blog Ad Space: Top of article body -->
                <x-ad-slot slot="news_in_feed" />

                <!-- Article Body Content with In-Article Ad Splitting -->
                <div class="prose prose-invert prose-base sm:prose-lg max-w-none text-slate-300 leading-relaxed space-y-4 prose-headings:text-white prose-headings:font-black prose-headings:tracking-tight prose-a:text-amber-400 hover:prose-a:underline prose-strong:text-white">
                    @if($totalParts > 4 && $insertIndex > 0)
                        @php
                            $firstHalf = implode('', array_slice($paragraphs, 0, $insertIndex + 2));
                            $secondHalf = implode('', array_slice($paragraphs, $insertIndex + 2));
                        @endphp
                        {!! $firstHalf !!}

                        <!-- In-Article Mid-Content Ad Banner -->
                        <div class="my-6 not-prose">
                            <x-ad-banner placement="news_in_feed" />
                            <x-ad-slot slot="news_in_feed" />
                        </div>

                        {!! $secondHalf !!}
                    @else
                        {!! $articleContent !!}
                    @endif
                </div>

                <!-- Bottom In-Blog Ad Space -->
                <div class="pt-4">
                    <x-ad-slot slot="news_in_feed" />
                </div>

                <!-- Guest Author Credit Box -->
                @if($article->is_guest_post && $article->author_name)
                    <div class="p-5 sm:p-6 rounded-2xl bg-slate-900/90 border border-emerald-500/30 shadow-xl space-y-4">
                        <div class="flex flex-col sm:flex-row sm:items-start justify-between gap-4">
                            <div class="space-y-2">
                                <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-[11px] font-black uppercase tracking-wider bg-emerald-500/15 text-emerald-400 border border-emerald-500/30">
                                    🏏 Community Cricket Contributor
                                </span>

                                <div>
                                    <h3 class="text-base sm:text-lg font-black text-white">
                                        {{ $article->author_name }}
                                    </h3>
                                    @if($article->author_bio)
                                        <p class="text-xs sm:text-sm text-slate-300 mt-1 leading-relaxed">
                                            {{ $article->author_bio }}
                                        </p>
                                    @endif
                                </div>
                            </div>

                            @if($article->author_social_url)
                                <a
                                    href="{{ $article->author_social_url }}"
                                    target="_blank"
                                    rel="nofollow noopener noreferrer"
                                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-slate-950 hover:bg-slate-800 border border-slate-700 hover:border-amber-400/50 text-amber-400 text-xs font-bold transition shrink-0"
                                >
                                    <span>Author Profile</span>
                                    <span>↗</span>
                                </a>
                            @endif
                        </div>

                        <div class="pt-3 border-t border-slate-800 flex items-center justify-between">
                            <a
                                href="{{ route('guest-post.create') }}"
                                wire:navigate
                                class="text-xs font-bold text-amber-400 hover:text-amber-300 transition"
                            >
                                Want to publish your cricket analysis? Write for Cricwire →
                            </a>
                        </div>
                    </div>
                @endif

                <!-- Bottom Social Share Bar (Under Article Body) -->
                <x-news-social-share-bar :article="$article" />

                <!-- Editorial Disclaimer Box -->
                <div class="p-4 sm:p-5 rounded-2xl bg-slate-900/70 border border-slate-800 text-slate-300 text-xs sm:text-sm leading-relaxed space-y-2">
                    <div class="flex items-center gap-2 text-amber-400 font-bold uppercase tracking-wider text-xs">
                        <svg class="w-4 h-4 shrink-0" fill="currentColor" viewBox="0 0 20 20">
                            <path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/>
                        </svg>
                        <span>Editorial Disclaimer &amp; Information Sources</span>
                    </div>
                    <p class="text-slate-400">
                        We collect and curate cricket information, match updates, press conferences, and breaking developments from various reliable news sources, official news agencies, live match telecasts, verified press releases, and national and international sports newspapers to bring cricket enthusiasts accurate and timely coverage.
                    </p>
                </div>

                <!-- About CricWire Section -->
                <div class="p-5 sm:p-6 rounded-2xl bg-gradient-to-br from-slate-900 via-slate-900/90 to-amber-950/20 border border-amber-500/20 shadow-xl space-y-4">
                    <div class="flex items-center justify-between pb-3 border-b border-white/5">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-xl bg-amber-400/10 border border-amber-400/30 flex items-center justify-center text-xl shadow-inner">
                                🏏
                            </div>
                            <div>
                                <h3 class="text-base sm:text-lg font-black text-white tracking-wide flex items-center gap-2">
                                    <span>About CricWire</span>
                                    <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-amber-400 text-slate-950">
                                        Fastest Live Line
                                    </span>
                                </h3>
                                <p class="text-xs text-slate-400">Your premier all-in-one digital cricket destination</p>
                            </div>
                        </div>
                    </div>

                    <p class="text-xs sm:text-sm text-slate-300 leading-relaxed">
                        <strong class="text-white">CricWire</strong> is a modern sports media platform designed for die-hard cricket fans worldwide. We provide ball-by-ball fastest live line scores, ball commentaries, in-depth match previews, pitch and weather reports, fantasy team suggestions, dynamic session rates, and real-time breaking news.
                    </p>

                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-1">
                        <div class="p-3 rounded-xl bg-slate-950/60 border border-slate-800/80">
                            <div class="text-amber-400 text-lg mb-1">⚡</div>
                            <div class="text-xs font-bold text-white">Ultra-Fast Live Line</div>
                            <p class="text-[11px] text-slate-400 mt-0.5 leading-snug">Real-time ball-by-ball live updates with zero-latency match analytics.</p>
                        </div>
                        <div class="p-3 rounded-xl bg-slate-950/60 border border-slate-800/80">
                            <div class="text-amber-400 text-lg mb-1">📰</div>
                            <div class="text-xs font-bold text-white">Curated Journalism</div>
                            <p class="text-[11px] text-slate-400 mt-0.5 leading-snug">Fact-checked reports, press coverage, match reviews, and player transfers.</p>
                        </div>
                        <div class="p-3 rounded-xl bg-slate-950/60 border border-slate-800/80">
                            <div class="text-amber-400 text-lg mb-1">🔮</div>
                            <div class="text-xs font-bold text-white">Match Predictions &amp; XI</div>
                            <p class="text-[11px] text-slate-400 mt-0.5 leading-snug">Data-driven pitch insights, head-to-head records, and fantasy cricket guides.</p>
                        </div>
                    </div>
                </div>

                <!-- Article Tags & Topics Strip -->
                @if(!empty($tags))
                    <div class="pt-6 border-t border-slate-800/80 space-y-2.5">
                        <div class="text-xs font-bold text-slate-400 uppercase tracking-wider flex items-center gap-1.5">
                            <span>🏷️</span> Related Tags &amp; Topics
                        </div>
                        <div class="flex flex-wrap gap-2">
                            @foreach($tags as $tag)
                                <a 
                                    href="{{ route('news.index', ['category' => 'all']) }}" 
                                    wire:navigate 
                                    class="px-3 py-1 rounded-xl text-xs font-semibold bg-slate-900/80 hover:bg-slate-800 text-slate-300 hover:text-amber-400 border border-slate-800 hover:border-amber-400/40 transition-all shadow-sm"
                                >
                                    #{{ $tag }}
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Related Stories Section -->
                @if(isset($relatedArticles) && $relatedArticles->count() > 0)
                    <div class="pt-8 border-t border-slate-800/80 space-y-4">
                        <div class="flex items-center justify-between">
                            <h3 class="text-sm font-black uppercase tracking-wider text-white flex items-center gap-2">
                                <span class="text-amber-400">⚡</span>
                                <span>More in {{ $categoryLabel }}</span>
                            </h3>
                            <a href="{{ route('news.index', ['category' => $article->category]) }}" wire:navigate class="text-xs text-amber-400 hover:underline font-semibold">
                                View Section →
                            </a>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                            @foreach($relatedArticles as $related)
                                <a 
                                    href="{{ route('news.show', ['article' => $related->slug]) }}" 
                                    wire:navigate 
                                    class="p-3 rounded-2xl bg-slate-900/60 border border-slate-800 hover:border-amber-500/40 transition group flex flex-col justify-between shadow-sm hover:shadow-amber-500/5"
                                >
                                    @php
                                        $relImg = $related->resolved_image_url;
                                    @endphp

                                    <div class="w-full h-28 rounded-xl overflow-hidden bg-slate-800 mb-2.5 border border-white/5">
                                        @if($relImg)
                                            <img 
                                                src="{{ $relImg }}" 
                                                alt="{{ $related->title }}" 
                                                loading="lazy" 
                                                referrerpolicy="no-referrer" 
                                                onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?auto=format&fit=crop&w=400&q=80';"
                                                class="w-full h-full object-cover group-hover:scale-105 transition-transform" 
                                            />
                                        @else
                                            <div class="w-full h-full flex items-center justify-center text-xl">🏏</div>
                                        @endif
                                    </div>

                                    <div>
                                        <h4 class="text-xs font-bold text-white group-hover:text-amber-400 transition-colors line-clamp-2 leading-snug">
                                            {{ $related->title }}
                                        </h4>
                                        <span class="text-[10px] text-slate-500 font-mono mt-1.5 block">
                                            {{ $related->published_at ? $related->published_at->format('M d, Y') : $related->created_at->format('M d, Y') }}
                                        </span>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif
            </article>

            <!-- RIGHT COLUMN: Sticky Sidebar (lg:col-span-4) -->
            <aside class="lg:col-span-4 space-y-6 lg:sticky lg:top-20">

                <!-- 1. Latest 3 News Items Widget -->
                <div class="bg-slate-900/70 border border-slate-800 rounded-2xl p-4 backdrop-blur shadow-md space-y-3">
                    <div class="flex items-center justify-between pb-2 border-b border-white/5">
                        <h3 class="text-xs font-bold text-amber-400 uppercase tracking-wider flex items-center gap-1.5">
                            <span>🔥</span> Latest Headlines
                        </h3>
                        <a href="{{ route('news.index') }}" wire:navigate class="text-[10px] text-slate-400 hover:text-amber-400 transition font-medium">View All →</a>
                    </div>

                    <div class="space-y-3">
                        @forelse($latestNews as $recent)
                            <a 
                                href="{{ route('news.show', ['article' => $recent->slug]) }}" 
                                wire:navigate 
                                class="flex items-center gap-3 p-2 rounded-xl bg-slate-950/70 hover:bg-slate-800/70 border border-slate-800/80 hover:border-amber-500/30 transition group"
                            >
                                @php
                                    $recImg = $recent->resolved_image_url;
                                @endphp

                                <div class="w-16 h-14 rounded-lg overflow-hidden bg-slate-900 shrink-0 border border-white/5">
                                    @if($recImg)
                                        <img 
                                            src="{{ $recImg }}" 
                                            alt="{{ $recent->title }}" 
                                            loading="lazy" 
                                            crossorigin="anonymous"
                                            referrerpolicy="no-referrer"
                                            onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?auto=format&fit=crop&w=400&q=80';"
                                            class="w-full h-full object-cover group-hover:scale-105 transition-transform" 
                                        />
                                    @else
                                        <div class="w-full h-full flex items-center justify-center text-xs">🏏</div>
                                    @endif
                                </div>

                                <div class="min-w-0 flex-1">
                                    <h4 class="text-xs font-bold text-slate-200 group-hover:text-amber-300 transition-colors line-clamp-2 leading-snug">
                                        {{ $recent->title }}
                                    </h4>
                                    <span class="text-[10px] text-slate-500 font-mono mt-1 block">
                                        {{ $recent->published_at ? $recent->published_at->diffForHumans() : 'Recent' }}
                                    </span>
                                </div>
                            </a>
                        @empty
                            <p class="text-xs text-slate-500">No other recent stories available.</p>
                        @endforelse
                    </div>
                </div>

                <!-- 2. Admin Managed Sidebar Ad Banner Unit -->
                @if($sidebarBanner)
                    <div class="rounded-2xl overflow-hidden border border-slate-800 bg-slate-900/60 p-3 shadow-md">
                        <div class="text-[9px] uppercase tracking-wider text-slate-500 font-semibold mb-2 flex items-center gap-1">
                            <span>SPONSORED</span>
                            <span class="w-1 h-1 rounded-full bg-slate-600"></span>
                            <span>PARTNER</span>
                        </div>
                        @if($sidebarBanner->type === 'image_link' || $sidebarBanner->type === 'telegram_cta')
                            <a 
                                href="{{ route('ad.redirect', ['banner' => $sidebarBanner->id]) }}" 
                                target="_blank" 
                                rel="noopener noreferrer sponsored"
                                class="block rounded-xl overflow-hidden border border-white/10 hover:border-amber-500/40 transition group"
                            >
                                <img 
                                    src="{{ $sidebarBanner->resolved_image_url }}" 
                                    alt="{{ $sidebarBanner->name }}" 
                                    class="w-full h-auto max-h-[300px] object-cover rounded-xl group-hover:scale-[1.01] transition-transform"
                                />
                            </a>
                        @elseif($sidebarBanner->type === 'html_script')
                            <div class="w-full overflow-hidden flex justify-center">
                                {!! $sidebarBanner->script_code !!}
                            </div>
                        @endif
                    </div>
                @else
                    <!-- Fallback ad slots complying with zero whitespace rule -->
                    <x-ad-banner placement="news_sidebar" />
                    <x-ad-slot slot="sidebar" />
                @endif

                <!-- 3. Explore Categories & Series Hub -->
                <div class="bg-slate-900/70 border border-slate-800 rounded-2xl p-4 backdrop-blur shadow-md space-y-3">
                    <h3 class="text-xs font-bold text-slate-300 uppercase tracking-wider flex items-center gap-1.5 pb-2 border-b border-white/5">
                        <span>🏷️</span> Cricket Hubs
                    </h3>

                    <div class="space-y-1.5">
                        @foreach($sidebarCategories as $cat)
                            <a 
                                href="{{ route('news.index', ['category' => $cat['key']]) }}" 
                                wire:navigate 
                                class="flex items-center justify-between p-2.5 rounded-xl text-xs font-semibold text-slate-300 hover:text-white bg-slate-950/60 hover:bg-slate-800/70 border border-slate-800/80 hover:border-amber-500/40 transition group"
                            >
                                <span class="flex items-center gap-2">
                                    <span>{{ $cat['icon'] }}</span>
                                    <span>{{ $cat['label'] }}</span>
                                </span>
                                <span class="text-[10px] text-slate-500 group-hover:text-amber-400 transition-colors">→</span>
                            </a>
                        @endforeach
                    </div>
                </div>

                <!-- 4. Secondary Sidebar Ad Slot -->
                <x-ad-banner placement="desktop_right" />
            </aside>

        </div>
    </main>

    <!-- Desktop-only Footer (hidden on mobile) -->
    <x-footer />

    <!-- Floating Mobile Bottom Navigation -->
    <x-bottom-nav />
</div>
