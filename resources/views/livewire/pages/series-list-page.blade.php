<div class="min-h-screen bg-slate-950 text-slate-100 font-sans antialiased pb-24 selection:bg-amber-500 selection:text-black">
    <!-- Top Global Header -->
    <x-header />

    <!-- Sub-Navbar / Breadcrumb Strip -->
    <nav class="border-b border-slate-800/80 bg-slate-900/40 backdrop-blur py-2.5 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto flex items-center justify-between text-xs text-slate-400">
            <div class="flex items-center space-x-2">
                <a href="/" wire:navigate class="hover:text-amber-400 transition">Home</a>
                <span>/</span>
                <span class="text-amber-400 font-semibold">Cricket Series &amp; Tournaments</span>
            </div>
            <div class="flex items-center space-x-1.5 text-[11px] text-slate-400 bg-slate-900 px-2.5 py-1 rounded-lg border border-slate-800">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span>Active Tournaments: {{ $totalCount }}</span>
            </div>
        </div>
    </nav>

    <!-- Main Container Grid -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

            <!-- LEFT/MAIN COLUMN: Series Directory (lg:col-span-8) -->
            <section class="lg:col-span-8 space-y-6">

                <!-- Header Title & Stats Strip -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-2 border-b border-slate-800/80">
                    <div>
                        <h1 class="text-xl sm:text-2xl font-black tracking-tight text-white flex items-center gap-2">
                            <span>🏆</span>
                            <span>Cricket Series Hub</span>
                        </h1>
                        <p class="text-xs text-slate-400 mt-1">
                            Browse international bilateral tours, ICC events &amp; domestic T20 leagues.
                        </p>
                    </div>
                    <span class="self-start sm:self-auto px-3 py-1 rounded-full text-xs font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20 shadow-sm">
                        {{ count($seriesList) }} TOURNAMENTS
                    </span>
                </div>

                <!-- Controls: Filter Pills & Search Input -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-900/80 p-2 sm:p-2.5 rounded-2xl border border-slate-800">
                    <!-- Filter Pills -->
                    <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0 scrollbar-none">
                        <button 
                            wire:click="setFilter('all')"
                            class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition whitespace-nowrap {{ $filter === 'all' ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}"
                        >
                            All Active ({{ $totalCount }})
                        </button>

                        <button 
                            wire:click="setFilter('live')"
                            class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition whitespace-nowrap flex items-center gap-1.5 {{ $filter === 'live' ? 'bg-rose-500 text-white shadow-md shadow-rose-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}"
                        >
                            <span class="w-1.5 h-1.5 rounded-full bg-rose-400 animate-ping"></span>
                            <span>Live Now ({{ $liveCount }})</span>
                        </button>

                        <button 
                            wire:click="setFilter('upcoming')"
                            class="px-3.5 py-1.5 rounded-xl text-xs font-bold transition whitespace-nowrap {{ $filter === 'upcoming' ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}"
                        >
                            Upcoming &amp; Tours ({{ $upcomingCount }})
                        </button>
                    </div>

                    <!-- Search Input -->
                    <div class="relative sm:w-64 shrink-0">
                        <input 
                            type="text" 
                            wire:model.live.debounce.300ms="search" 
                            placeholder="Filter series..." 
                            class="w-full bg-slate-950/80 border border-slate-700/80 focus:border-amber-400 rounded-xl px-3 py-1.5 pl-8 text-xs text-white placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-400 transition"
                        />
                        <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35m1.35-5.65a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        @if($search)
                            <button 
                                wire:click="$set('search', '')"
                                class="absolute right-2.5 top-2 text-slate-400 hover:text-white text-xs"
                            >
                                ✕
                            </button>
                        @endif
                    </div>
                </div>

                <!-- Series List Cards -->
                <div class="space-y-3.5">
                    @forelse($seriesList as $series)
                        <div class="bg-slate-900/90 border border-slate-800/80 hover:border-amber-500/40 rounded-2xl p-4 sm:p-5 transition-all duration-200 group shadow-lg hover:shadow-amber-500/5 relative overflow-hidden">
                            <!-- Left Accent Strip for Live Series -->
                            @if(!empty($series['is_live']))
                                <div class="absolute left-0 top-0 bottom-0 w-1 bg-gradient-to-b from-rose-500 to-amber-500"></div>
                            @endif

                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <!-- Series Info -->
                                <div class="space-y-1.5 flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <!-- Format Badge -->
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-slate-800 text-amber-400 border border-amber-500/20">
                                            {{ $series['format'] ?? 'T20' }}
                                        </span>

                                        @if(!empty($series['is_live']))
                                            <span class="inline-flex items-center gap-1.5 text-[10px] font-black uppercase tracking-wider bg-rose-500/20 text-rose-400 px-2 py-0.5 rounded-md border border-rose-500/30">
                                                <span class="w-1.5 h-1.5 bg-rose-500 rounded-full animate-ping"></span>
                                                <span>LIVE MATCH</span>
                                            </span>
                                        @endif

                                        @if(!empty($series['date_range']))
                                            <span class="text-xs text-slate-400 font-mono flex items-center gap-1">
                                                <span>📅</span>
                                                <span>{{ $series['date_range'] }}</span>
                                            </span>
                                        @endif
                                    </div>

                                    <!-- Series Title Link -->
                                    <a 
                                        href="{{ route('series.detail', ['seriesSlug' => $series['series_slug'], 'tab' => 'matches']) }}" 
                                        wire:navigate 
                                        class="text-base sm:text-lg font-bold text-white group-hover:text-amber-400 transition-colors block truncate"
                                    >
                                        {{ $series['series_name'] }}
                                    </a>

                                    <!-- Match Counts Strip -->
                                    <div class="flex items-center gap-3 text-xs text-slate-400 pt-0.5">
                                        @if(($series['live_count'] ?? 0) > 0)
                                            <span class="text-rose-400 font-semibold flex items-center gap-1">
                                                <span class="w-1.5 h-1.5 rounded-full bg-rose-400"></span>
                                                {{ $series['live_count'] }} Live
                                            </span>
                                            <span class="text-slate-600">•</span>
                                        @endif
                                        @if(($series['upcoming_count'] ?? 0) > 0)
                                            <span class="text-amber-300/90 font-medium">
                                                {{ $series['upcoming_count'] }} Upcoming
                                            </span>
                                            <span class="text-slate-600">•</span>
                                        @endif
                                        @if(($series['completed_count'] ?? 0) > 0)
                                            <span class="text-emerald-400 font-medium">
                                                {{ $series['completed_count'] }} Completed
                                            </span>
                                        @endif
                                    </div>
                                </div>

                                <!-- Action Buttons -->
                                <div class="flex items-center gap-2 self-start sm:self-center shrink-0">
                                    <a 
                                        href="{{ route('series.detail', ['seriesSlug' => $series['series_slug'], 'tab' => 'matches']) }}" 
                                        wire:navigate 
                                        class="px-3.5 py-1.5 rounded-xl text-xs font-bold bg-amber-500 hover:bg-amber-400 text-slate-950 transition shadow-md shadow-amber-500/10 flex items-center gap-1"
                                    >
                                        <span>Matches</span>
                                        <span>→</span>
                                    </a>

                                    <a 
                                        href="{{ route('series.detail', ['seriesSlug' => $series['series_slug'], 'tab' => 'news']) }}" 
                                        wire:navigate 
                                        class="px-3 py-1.5 rounded-xl text-xs font-semibold bg-slate-800 hover:bg-slate-700 text-slate-300 hover:text-white transition border border-slate-700/60"
                                    >
                                        News
                                    </a>
                                </div>
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-16 bg-slate-900/40 rounded-2xl border border-slate-800/80">
                            <span class="text-4xl">🏏</span>
                            <h3 class="text-base font-bold text-slate-200 mt-3">No Series Found</h3>
                            <p class="text-xs text-slate-400 mt-1 max-w-sm mx-auto">
                                @if($search)
                                    No tournaments match "{{ $search }}". Try a different keyword or reset filters.
                                @else
                                    No covered cricket tournaments currently available in this section.
                                @endif
                            </p>
                            @if($search)
                                <button 
                                    wire:click="$set('search', '')" 
                                    class="mt-4 px-4 py-1.5 rounded-xl text-xs font-bold bg-slate-800 text-amber-400 border border-slate-700 hover:bg-slate-700 transition"
                                >
                                    Clear Search
                                </button>
                            @endif
                        </div>
                    @endforelse
                </div>

            </section>

            <!-- RIGHT SIDEBAR (lg:col-span-4) -->
            <aside class="lg:col-span-4 space-y-6">

                <!-- Cricwire Telegram Community Card -->
                <div class="rounded-2xl p-5 bg-gradient-to-br from-sky-950/60 via-slate-900 to-slate-900 border border-sky-500/20 shadow-xl relative overflow-hidden group">
                    <div class="flex items-center space-x-3 mb-3">
                        <div class="w-10 h-10 rounded-xl bg-sky-500/20 text-sky-400 flex items-center justify-center font-bold text-xl border border-sky-500/30">
                            📢
                        </div>
                        <div>
                            <h4 class="text-sm font-black text-white group-hover:text-sky-300 transition-colors">Join Cricwire Official</h4>
                            <p class="text-[11px] text-slate-400">Fastest ball-by-ball alerts &amp; toss updates</p>
                        </div>
                    </div>
                    <p class="text-xs text-slate-300 leading-relaxed mb-4">
                        Never miss a wicket, fantasy tips, or live match stream alert on Telegram.
                    </p>
                    <a 
                        href="https://t.me/cricwireofficial" 
                        target="_blank" 
                        rel="noopener noreferrer" 
                        class="w-full flex items-center justify-center space-x-2 py-2.5 px-4 rounded-xl text-xs font-bold bg-sky-500 hover:bg-sky-400 text-white transition-all duration-200 shadow-lg shadow-sky-500/20"
                    >
                        <span>Join Telegram Channel</span>
                        <span>→</span>
                    </a>
                </div>

                <!-- Cricwire Social Card (YouTube & Instagram) -->
                <div class="rounded-2xl p-4 bg-slate-900/90 border border-slate-800 shadow-xl space-y-3">
                    <h4 class="text-xs font-bold uppercase tracking-wider text-slate-400 flex items-center gap-1.5">
                        <span>🌐</span>
                        <span>Official Social Handles</span>
                    </h4>
                    <div class="grid grid-cols-2 gap-2">
                        <a 
                            href="https://youtube.com/@cricwire" 
                            target="_blank" 
                            rel="noopener noreferrer" 
                            class="p-2.5 rounded-xl bg-slate-950/80 border border-slate-800 hover:border-red-500/40 text-center transition group"
                        >
                            <span class="text-lg">📺</span>
                            <div class="text-[11px] font-bold text-white group-hover:text-red-400 transition-colors mt-0.5">YouTube</div>
                            <div class="text-[9px] text-slate-500">Analysis &amp; Shows</div>
                        </a>
                        <a 
                            href="https://instagram.com/cricwire" 
                            target="_blank" 
                            rel="noopener noreferrer" 
                            class="p-2.5 rounded-xl bg-slate-950/80 border border-slate-800 hover:border-pink-500/40 text-center transition group"
                        >
                            <span class="text-lg">📸</span>
                            <div class="text-[11px] font-bold text-white group-hover:text-pink-400 transition-colors mt-0.5">Instagram</div>
                            <div class="text-[9px] text-slate-500">Reels &amp; Memes</div>
                        </a>
                    </div>
                </div>

                <!-- Sidebar Ad Slot (Zero Whitespace if null) -->
                @if($sidebarAd)
                    <div class="rounded-2xl overflow-hidden border border-slate-800/80 shadow-xl">
                        <a href="{{ route('ad.redirect', $sidebarAd) }}" target="_blank" rel="noopener sponsored" class="block">
                            <img src="{{ asset('storage/' . $sidebarAd->image_path) }}" alt="{{ $sidebarAd->title ?? 'Sponsor' }}" class="w-full object-cover rounded-2xl" />
                        </a>
                    </div>
                @endif

            </aside>

        </div>
    </main>

    <!-- Bottom Navigation Bar for Mobile -->
    <x-bottom-nav activeTab="series" />
</div>
