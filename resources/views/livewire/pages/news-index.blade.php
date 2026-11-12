<div class="min-h-screen bg-slate-950 text-slate-100 font-sans antialiased relative selection:bg-amber-500 selection:text-black flex flex-col">
    <!-- Sticky Header Topbar -->
    <x-header />

    <!-- Top Ad Slot (728x90 Desktop / 320x50 Mobile) with ZERO Whitespace Fallback -->
    <div class="max-w-7xl mx-auto w-full px-3 sm:px-6 lg:px-8 pt-3">
        <x-ad-slot slot="news_top" />
    </div>

    <!-- Heading & Category Filter Row -->
    <div class="max-w-7xl mx-auto w-full px-4 sm:px-6 lg:px-8 pt-2 pb-2">
        <div class="flex items-center justify-between mb-3">
            <div>
                <h1 class="text-lg sm:text-2xl font-black tracking-tight text-white flex items-center gap-2">
                    <span>📰</span>
                    <span class="text-transparent bg-clip-text bg-gradient-to-r from-amber-400 via-yellow-300 to-amber-500 drop-shadow-[0_0_12px_rgba(245,158,11,0.25)]">
                        Cricket News &amp; Editorials
                    </span>
                </h1>
                <p class="text-xs sm:text-sm text-slate-400 mt-0.5 font-medium">Headlines, pitch reports, predictions &amp; fantasy tips</p>
            </div>

            <span class="inline-flex items-center px-2.5 py-1 rounded-full text-[10px] sm:text-xs font-black uppercase tracking-wider bg-slate-900 border border-slate-800 text-amber-400">
                {{ $totalCount }} {{ $totalCount === 1 ? 'Story' : 'Stories' }}
            </span>
        </div>

        <!-- Horizontal Category Pills -->
        <div class="flex items-center space-x-2 overflow-x-auto no-scrollbar py-2 -mx-4 sm:mx-0 px-4 sm:px-0">
            @php
                $categories = [
                    'all' => 'All Stories',
                    'news' => 'Breaking News',
                    'pitch_report' => 'Pitch Report',
                    'prediction' => 'Predictions',
                    'fantasy_tips' => 'Fantasy XI',
                ];
            @endphp

            @foreach($categories as $catKey => $catLabel)
                <button
                    type="button"
                    wire:click="setCategory('{{ $catKey }}')"
                    class="shrink-0 px-3.5 py-1.5 rounded-full text-xs font-bold uppercase tracking-wider transition-all duration-150 {{ $category === $catKey ? 'bg-amber-400 text-slate-950 shadow-sm shadow-amber-400/40 ring-1 ring-amber-300' : 'bg-slate-900/80 text-slate-400 hover:text-slate-200 border border-slate-800/80 hover:border-slate-700' }}"
                >
                    {{ $catLabel }}
                </button>
            @endforeach
        </div>
    </div>

    <!-- Main Content Area: Responsive Grid (Feed on Left, Sticky Sidebar on Right) -->
    <div class="max-w-7xl mx-auto w-full px-3 sm:px-6 lg:px-8 py-4 flex-1 pb-24 md:pb-12">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

            <!-- Center/Left Column: News Cards (Thumbnail Grid, at least 2 on a single row) -->
            <main class="col-span-1 lg:col-span-8 space-y-6">
                <!-- Thumbnail Grid (1 col on mobile, 2 cols on tablet & desktop) -->
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 sm:gap-5">
                    @forelse($articles as $index => $article)
                        @php
                            $categoryBadgeColor = match($article->category) {
                                'pitch_report' => 'bg-emerald-500/10 text-emerald-400 border-emerald-500/30',
                                'prediction' => 'bg-purple-500/10 text-purple-400 border-purple-500/30',
                                'fantasy_tips' => 'bg-rose-500/10 text-rose-400 border-rose-500/30',
                                default => 'bg-amber-500/10 text-amber-400 border-amber-500/30',
                            };

                            $categoryLabel = match($article->category) {
                                'pitch_report' => 'Pitch Report',
                                'prediction' => 'Match Prediction',
                                'fantasy_tips' => 'Fantasy Tips',
                                default => 'Latest News',
                            };

                            $imageUrl = $article->resolved_image_url;
                        @endphp

                        <!-- News Thumbnail Card -->
                        <article class="rounded-2xl gaming-card p-3 border border-white/10 hover:border-amber-500/40 transition-all duration-200 group shadow-md hover:shadow-amber-500/10 flex flex-col justify-between">
                            <a href="{{ route('news.show', ['article' => $article->slug]) }}" wire:navigate class="block flex-1 flex flex-col">
                                <!-- Card Thumbnail Image -->
                                <div class="relative w-full h-44 sm:h-48 rounded-xl overflow-hidden mb-3 bg-slate-900 border border-white/5 shrink-0">
                                    @if($imageUrl)
                                        <img 
                                            src="{{ $imageUrl }}" 
                                            alt="{{ $article->title }}" 
                                            loading="lazy"
                                            crossorigin="anonymous"
                                            referrerpolicy="no-referrer"
                                            onerror="this.onerror=null; this.src='https://images.unsplash.com/photo-1540747913346-19e32dc3e97e?auto=format&fit=crop&w=800&q=80';"
                                            class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-300"
                                        />
                                    @else
                                        <div class="w-full h-full flex flex-col items-center justify-center bg-gradient-to-tr from-slate-900 to-slate-800 text-slate-500">
                                            <span class="text-3xl mb-1">🏏</span>
                                            <span class="text-xs font-bold text-slate-400">CRICWIRE</span>
                                        </div>
                                    @endif
                                    <div class="absolute inset-0 bg-gradient-to-t from-slate-950/80 via-transparent to-transparent pointer-events-none"></div>
                                    
                                    <span class="absolute top-2.5 left-2.5 px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider border backdrop-blur-md {{ $categoryBadgeColor }}">
                                        {{ $categoryLabel }}
                                    </span>
                                </div>

                                <div class="space-y-1.5 flex-1 flex flex-col justify-between">
                                    <div>
                                        <h2 class="text-sm sm:text-base font-extrabold text-white group-hover:text-amber-400 transition-colors leading-snug line-clamp-2">
                                            {{ $article->title }}
                                        </h2>

                                        @if($article->summary)
                                            <p class="text-xs text-slate-400 line-clamp-2 leading-relaxed mt-1.5">
                                                {{ $article->summary }}
                                            </p>
                                        @endif
                                    </div>

                                    <div class="pt-2 flex items-center justify-between text-[11px] text-slate-500 border-t border-white/5 mt-3 font-medium">
                                        <span class="flex items-center gap-1.5 text-slate-400 truncate max-w-[170px]">
                                            <span>✍️</span>
                                            <span class="truncate">{{ $article->source_name ?: 'CricWire News Agency' }}</span>
                                        </span>
                                        <span class="shrink-0">{{ $article->published_at ? $article->published_at->format('M d, Y') : $article->created_at->format('M d, Y') }}</span>
                                    </div>
                                </div>
                            </a>
                        </article>

                        <!-- In-Feed Ad Banner Slot (Spans full 2 columns) -->
                        @if(($index + 1) % 4 === 0)
                            <div class="col-span-1 sm:col-span-2">
                                <x-ad-slot slot="news_in_feed" />
                            </div>
                        @endif

                    @empty
                        <!-- Dark Gaming Glass Empty State -->
                        <div class="col-span-1 sm:col-span-2 text-center py-16 bg-slate-900/50 rounded-2xl border border-slate-800/80 mx-2">
                            <span class="text-4xl block mb-2">📰</span>
                            <p class="text-slate-300 font-bold text-sm">No articles found in this category</p>
                            <p class="text-slate-500 text-xs mt-1">Try selecting another category or check back shortly.</p>
                        </div>
                    @endforelse
                </div>

                <!-- Load More Reactive Pagination -->
                @if($hasMore)
                    <div class="pt-4 pb-2 text-center max-w-sm mx-auto">
                        <button
                            type="button"
                            wire:click="loadMore"
                            wire:loading.attr="disabled"
                            class="w-full py-2.5 px-4 rounded-xl bg-slate-900/90 hover:bg-slate-800 border border-white/10 hover:border-amber-500/40 text-xs font-bold text-slate-200 hover:text-white transition-all shadow-md active:scale-[0.99] flex items-center justify-center gap-2"
                        >
                            <span wire:loading.remove wire:target="loadMore">Load More Stories ⚡</span>
                            <span wire:loading wire:target="loadMore" class="flex items-center gap-2">
                                <svg class="animate-spin h-3.5 w-3.5 text-amber-400" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                                </svg>
                                <span>Loading next stories...</span>
                            </span>
                        </button>
                    </div>
                @endif
            </main>

            <!-- Right Sidebar: Recent Headlines, Categories & Ad Slots (4 cols on lg, sticky) -->
            <aside class="col-span-1 lg:col-span-4 space-y-5 lg:sticky lg:top-20">
                <!-- 1. Recent Headlines Widget -->
                <div class="bg-slate-900/60 border border-slate-800 rounded-2xl p-4 backdrop-blur shadow-sm space-y-3">
                    <div class="flex items-center justify-between pb-2 border-b border-white/5">
                        <h3 class="text-xs font-bold text-amber-400 uppercase tracking-wider flex items-center gap-1.5">
                            <span>🔥</span> Recent Headlines
                        </h3>
                        <span class="text-[10px] text-slate-500 font-mono">Live Feed</span>
                    </div>

                    <div class="space-y-3">
                        @forelse($recentHeadlines as $recent)
                            <a href="{{ route('news.show', ['article' => $recent->slug]) }}" wire:navigate class="flex items-center gap-3 p-2 rounded-xl bg-slate-950/70 hover:bg-slate-800/60 border border-slate-800/80 hover:border-amber-500/30 transition group">
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
                            <p class="text-xs text-slate-500">No headlines available.</p>
                        @endforelse
                    </div>
                </div>

                <!-- 2. Explore Topics / Categories Widget -->
                <div class="bg-slate-900/60 border border-slate-800 rounded-2xl p-4 backdrop-blur shadow-sm space-y-3">
                    <h3 class="text-xs font-bold text-slate-300 uppercase tracking-wider flex items-center gap-1.5 pb-2 border-b border-white/5">
                        <span>🏷️</span> Explore Topics
                    </h3>

                    @php
                        $sidebarCategories = [
                            ['key' => 'all', 'label' => 'All Stories', 'icon' => '📰'],
                            ['key' => 'news', 'label' => 'Breaking News', 'icon' => '⚡'],
                            ['key' => 'pitch_report', 'label' => 'Pitch Reports', 'icon' => '🏟️'],
                            ['key' => 'prediction', 'label' => 'Match Predictions', 'icon' => '🔮'],
                            ['key' => 'fantasy_tips', 'label' => 'Fantasy XI Tips', 'icon' => '⭐'],
                        ];
                    @endphp

                    <div class="space-y-1.5">
                        @foreach($sidebarCategories as $cat)
                            <button
                                type="button"
                                wire:click="setCategory('{{ $cat['key'] }}')"
                                class="w-full flex items-center justify-between p-2 rounded-xl text-xs font-semibold transition group {{ $category === $cat['key'] ? 'bg-amber-400 text-slate-950 font-bold shadow-sm' : 'text-slate-300 hover:text-white bg-slate-950/50 hover:bg-slate-800/70 border border-slate-800/80 hover:border-amber-500/40' }}"
                            >
                                <span class="flex items-center gap-2">
                                    <span>{{ $cat['icon'] }}</span>
                                    <span>{{ $cat['label'] }}</span>
                                </span>
                                <span class="text-[10px] {{ $category === $cat['key'] ? 'text-slate-900' : 'text-slate-500 group-hover:text-amber-400' }} transition-colors">→</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                <!-- 3. Sidebar Ad Slots -->
                <x-ad-banner placement="desktop_right" />
                <x-ad-slot slot="sidebar" />
            </aside>

        </div>
    </div>

    <!-- Desktop-only Footer (hidden on mobile) -->
    <x-footer />

    <!-- Bottom Navigation -->
    <x-bottom-nav />
</div>
