<div class="min-h-screen bg-slate-950 text-slate-100 font-sans antialiased pb-24 selection:bg-amber-500 selection:text-black">
    <!-- Top Global Header -->
    <x-header />

    <!-- Sub-Navbar / Breadcrumb Strip -->
    <nav class="border-b border-slate-800/80 bg-slate-900/40 backdrop-blur py-2.5 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto flex items-center justify-between text-xs text-slate-400">
            <div class="flex items-center space-x-2">
                <a href="/" wire:navigate class="hover:text-amber-400 transition">Home</a>
                <span>/</span>
                <span class="text-amber-400 font-semibold">Cricket Schedule &amp; Fixtures</span>
                @if($category && $category !== 'all')
                    <span>/</span>
                    <span class="text-slate-300 uppercase">{{ $category }}</span>
                @endif
            </div>
            <div class="flex items-center space-x-1.5 text-[11px] text-slate-400 bg-slate-900 px-2.5 py-1 rounded-lg border border-slate-800">
                <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                <span>All Match Timings: IST (UTC+5:30)</span>
            </div>
        </div>
    </nav>

    <!-- Main Container Grid -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

            <!-- LEFT/MAIN COLUMN: Schedule Stream (lg:col-span-8) -->
            <section class="lg:col-span-8 space-y-6">

                <!-- Header Title & Counter -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-2 border-b border-slate-800/80">
                    <div>
                        <h1 class="text-xl sm:text-2xl font-black tracking-tight text-white flex items-center gap-2">
                            <span>📅</span>
                            <span>Match Fixtures &amp; Schedule</span>
                        </h1>
                        <p class="text-xs text-slate-400 mt-1">
                            Real-time cricket schedule, upcoming international fixtures, domestic leagues &amp; match results.
                        </p>
                    </div>
                    <span class="self-start sm:self-auto px-3 py-1 rounded-full text-xs font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20 shadow-sm">
                        {{ $totalMatchesCount ?? count($matches ?? []) }} MATCHES
                    </span>
                </div>

                <!-- Primary Tabs (Upcoming vs Recent) -->
                <div class="grid grid-cols-2 gap-2 bg-slate-900/80 p-1.5 rounded-2xl border border-slate-800 shadow-inner">
                    <button wire:click="setTab('upcoming')" 
                            class="flex items-center justify-center space-x-2 py-2.5 px-4 rounded-xl text-xs sm:text-sm font-bold transition-all duration-200 {{ $tab === 'upcoming' ? 'bg-gradient-to-r from-amber-500 to-amber-600 text-slate-950 shadow-lg shadow-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/50' }}">
                        <span>⚡</span>
                        <span>Upcoming Matches</span>
                        <span class="ml-1 text-[11px] px-2 py-0.5 rounded-full {{ $tab === 'upcoming' ? 'bg-black/20 text-slate-950 font-black' : 'bg-slate-800 text-slate-400' }}">
                            {{ $upcomingCount ?? 0 }}
                        </span>
                    </button>

                    <button wire:click="setTab('recent')" 
                            class="flex items-center justify-center space-x-2 py-2.5 px-4 rounded-xl text-xs sm:text-sm font-bold transition-all duration-200 {{ $tab === 'recent' ? 'bg-gradient-to-r from-amber-500 to-amber-600 text-slate-950 shadow-lg shadow-amber-500/20' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/50' }}">
                        <span>🏆</span>
                        <span>Recent Results</span>
                        @if(($recentCount ?? 0) > 0)
                            <span class="ml-1 text-[11px] px-2 py-0.5 rounded-full {{ $tab === 'recent' ? 'bg-black/20 text-slate-950 font-black' : 'bg-slate-800 text-slate-400' }}">
                                {{ $recentCount }}
                            </span>
                        @endif
                    </button>
                </div>

                <!-- Format Pills (ALL, T20, ODI, TEST, LEAGUES) -->
                <div class="flex items-center space-x-2 overflow-x-auto no-scrollbar py-1 text-xs">
                    @foreach(['all' => 'ALL', 't20' => 'T20', 'odi' => 'ODI', 'test' => 'TEST', 'leagues' => 'T20 LEAGUES'] as $catKey => $catLabel)
                        <button wire:click="setCategory('{{ $catKey }}')" 
                                class="shrink-0 px-4 py-1.5 rounded-xl font-bold uppercase transition border {{ ($category === $catKey || ($catKey === 'leagues' && $category === 't20-leagues')) ? 'bg-slate-800 text-amber-400 border-amber-500/50 shadow-md shadow-amber-500/10' : 'bg-slate-900/60 text-slate-400 hover:text-slate-200 border-slate-800 hover:border-slate-700' }}">
                            {{ $catLabel }}
                        </button>
                    @endforeach
                </div>

                <!-- Grouped Matches Feed -->
                <div class="space-y-6 pt-2">
                    @forelse($groupedMatches as $date => $dayMatches)
                        <div class="space-y-3">
                            <!-- Date Header Strip -->
                            <div class="flex items-center space-x-2 text-xs font-black text-amber-400 uppercase tracking-widest px-1">
                                <span class="w-2 h-2 rounded-full bg-amber-400 shadow-sm shadow-amber-400"></span>
                                <span>{{ $date }}</span>
                            </div>

                            <!-- Cards Grid -->
                            <div class="space-y-3">
                                @foreach($dayMatches as $match)
                                    @php
                                        $mId = $match['id'] ?? $match['match_id'] ?? null;
                                        $seriesSlug = \App\Helpers\CricketSlugHelper::makeSeriesSlug($match);
                                        $matchSlug = \App\Helpers\CricketSlugHelper::makeMatchSlug($match, $mId);

                                        $teamA = $match['team_a'] ?? $match['team1'] ?? 'Team A';
                                        $teamAShort = $match['team_a_short'] ?? $match['team1_short'] ?? $teamA;
                                        $teamAFlag = $match['team_a_img'] ?? $match['team_a_flag'] ?? $match['team1_flag'] ?? asset('images/default-team.png');

                                        $teamB = $match['team_b'] ?? $match['team2'] ?? 'Team B';
                                        $teamBShort = $match['team_b_short'] ?? $match['team2_short'] ?? $teamB;
                                        $teamBFlag = $match['team_b_img'] ?? $match['team_b_flag'] ?? $match['team2_flag'] ?? asset('images/default-team.png');

                                        $matchTime = $match['match_time'] ?? $match['time'] ?? $match['match_date'] ?? 'TBD';
                                    @endphp

                                    <div class="bg-gradient-to-b from-slate-900/90 to-slate-900/50 hover:from-slate-900 hover:to-slate-800/80 border border-slate-800/80 hover:border-slate-700 rounded-2xl p-4 sm:p-5 shadow-xl transition-all duration-200 relative group">
                                        
                                        <!-- Top Row: Series Info & Timing Badge -->
                                        <div class="flex items-center justify-between gap-2 pb-3 mb-3 border-b border-slate-800/60 text-xs">
                                            <div class="flex items-center space-x-2 truncate">
                                                <span class="px-2.5 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                                    {{ $match['match_type'] ?? $match['match_stage'] ?? $match['round'] ?? 'MATCH' }}
                                                </span>
                                                <span class="text-slate-400 truncate text-[11px] sm:text-xs font-medium">
                                                    {{ $match['series_name'] ?? $match['series'] ?? 'Tournament Series' }}
                                                </span>
                                            </div>

                                            <div class="flex items-center space-x-2 shrink-0">
                                                <span class="px-2.5 py-1 rounded-lg text-[11px] font-bold bg-slate-800/90 text-amber-300 border border-slate-700/60 flex items-center gap-1.5 font-mono">
                                                    <span>🕒</span>
                                                    <span>{{ $matchTime }}</span>
                                                </span>
                                                <!-- Action Buttons: Pin / Skip -->
                                                <button type="button" class="p-1.5 text-slate-500 hover:text-amber-400 hover:bg-slate-800 rounded-lg transition" title="Pin Match">
                                                    📌
                                                </button>
                                            </div>
                                        </div>

                                        <!-- Teams Center Row -->
                                        <a href="{{ route('match.detail', ['seriesSlug' => $seriesSlug, 'tab' => 'info', 'matchSlug' => $matchSlug]) }}" wire:navigate class="block">
                                            <div class="grid grid-cols-11 items-center gap-2 py-2">
                                                
                                                <!-- Team A -->
                                                <div class="col-span-5 flex items-center space-x-3">
                                                    <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-full overflow-hidden bg-slate-950 border border-slate-700/80 flex items-center justify-center shrink-0 shadow">
                                                        <img src="{{ $teamAFlag }}" 
                                                             alt="{{ $teamA }}" 
                                                             class="w-full h-full object-cover" 
                                                             onerror="this.src='{{ asset('images/default-team.png') }}'" />
                                                    </div>
                                                    <div class="min-w-0">
                                                        <h3 class="text-sm sm:text-base font-extrabold text-white group-hover:text-amber-400 transition truncate">
                                                            {{ $teamAShort }}
                                                        </h3>
                                                        <span class="text-[11px] text-slate-500 hidden sm:block truncate">
                                                            {{ $teamA }}
                                                        </span>
                                                        @if(!empty($match['team_a_scores']))
                                                            <span class="text-xs font-bold text-amber-400 font-mono block">
                                                                {{ $match['team_a_scores'] }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                </div>

                                                <!-- VS Badge -->
                                                <div class="col-span-1 flex justify-center">
                                                    <span class="w-7 h-7 rounded-full bg-slate-800/90 border border-slate-700 text-[10px] font-black text-amber-400 flex items-center justify-center shadow-inner">
                                                        VS
                                                    </span>
                                                </div>

                                                <!-- Team B -->
                                                <div class="col-span-5 flex items-center justify-end space-x-3 text-right">
                                                    <div class="min-w-0">
                                                        <h3 class="text-sm sm:text-base font-extrabold text-white group-hover:text-amber-400 transition truncate">
                                                            {{ $teamBShort }}
                                                        </h3>
                                                        <span class="text-[11px] text-slate-500 hidden sm:block truncate">
                                                            {{ $teamB }}
                                                        </span>
                                                        @if(!empty($match['team_b_scores']))
                                                            <span class="text-xs font-bold text-amber-400 font-mono block">
                                                                {{ $match['team_b_scores'] }}
                                                            </span>
                                                        @endif
                                                    </div>
                                                    <div class="w-10 h-10 sm:w-12 sm:h-12 rounded-full overflow-hidden bg-slate-950 border border-slate-700/80 flex items-center justify-center shrink-0 shadow">
                                                        <img src="{{ $teamBFlag }}" 
                                                             alt="{{ $teamB }}" 
                                                             class="w-full h-full object-cover" 
                                                             onerror="this.src='{{ asset('images/default-team.png') }}'" />
                                                    </div>
                                                </div>

                                            </div>
                                        </a>

                                        <!-- Match Result (for Recent Results tab) -->
                                        @if(!empty($match['result']) || !empty($match['status']))
                                            @php
                                                $resultText = $match['result'] ?? $match['status'] ?? '';
                                            @endphp
                                            @if($resultText && !str_contains(strtolower($resultText), 'upcoming'))
                                                <div class="py-1 px-2.5 rounded-lg bg-amber-400/10 border border-amber-400/20 text-[11px] font-semibold text-amber-300 mt-2 truncate">
                                                    🏆 {{ $resultText }}
                                                </div>
                                            @endif
                                        @endif

                                        <!-- Bottom Row: Stadium & Venue -->
                                        <div class="pt-3 mt-2 border-t border-slate-800/40 flex items-center justify-between text-xs text-slate-500">
                                            <div class="flex items-center space-x-1.5 truncate max-w-[70%] sm:max-w-[80%]">
                                                <span class="text-slate-400 shrink-0">📍</span>
                                                <span class="truncate">{{ $match['venue'] ?? $match['ground'] ?? $match['location'] ?? 'Venue to be confirmed' }}</span>
                                            </div>
                                            <a href="{{ route('match.detail', ['seriesSlug' => $seriesSlug, 'tab' => 'info', 'matchSlug' => $matchSlug]) }}" wire:navigate class="text-amber-400 hover:underline font-semibold text-[11px] shrink-0">
                                                Match Info →
                                            </a>
                                        </div>

                                    </div>
                                @endforeach
                            </div>
                        </div>
                    @empty
                        <div class="text-center py-16 bg-slate-900/50 rounded-2xl border border-slate-800/80 p-6">
                            <span class="text-4xl block mb-2">🗓️</span>
                            <h4 class="text-slate-300 font-bold text-sm">No Fixtures Available</h4>
                            <p class="text-slate-500 text-xs mt-1">There are no matches scheduled for the selected category.</p>
                        </div>
                    @endforelse
                </div>

            </section>

            <!-- RIGHT COLUMN: Sticky Sidebar Widgets & Ad Slot (lg:col-span-4) -->
            <aside class="lg:col-span-4 space-y-6 lg:sticky lg:top-20">

                <!-- Admin Managed Ad Banner Slot (Strict Zero Whitespace Rule) -->
                @if(!empty($sidebarBanner) && $sidebarBanner->image_path)
                    <div class="bg-slate-900/40 p-2 rounded-2xl border border-slate-800 shadow-lg">
                        <span class="text-[10px] uppercase font-bold text-slate-500 tracking-wider block mb-1.5 text-center">Sponsored</span>
                        <a href="{{ $sidebarBanner->target_url ?? '#' }}" target="_blank" rel="noopener noreferrer" class="block overflow-hidden rounded-xl border border-slate-800/60">
                            <img src="{{ asset('storage/' . $sidebarBanner->image_path) }}" 
                                 alt="Advertisement" 
                                 loading="lazy" 
                                 class="w-full h-auto max-h-[300px] object-cover mx-auto" />
                        </a>
                    </div>
                @else
                    <x-ad-slot slot="schedule_sidebar" />
                @endif

                <!-- Featured / Top Series Box -->
                <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-5 backdrop-blur shadow-md">
                    <h3 class="text-xs font-bold text-slate-200 uppercase tracking-wider pb-3 border-b border-slate-800 mb-3 flex items-center space-x-2">
                        <span>🏆</span>
                        <span>Popular Tournaments</span>
                    </h3>
                    <div class="space-y-2 text-xs">
                        @foreach([
                            ['name' => 'Big Bash League 2026', 'cat' => 'leagues'],
                            ['name' => 'Womens Victoria T20I Series', 'cat' => 't20'],
                            ['name' => 'India Tour of New Zealand 2026', 'cat' => 'odi'],
                            ['name' => 'ILT20 League 2026', 'cat' => 'leagues']
                        ] as $tourn)
                            <button wire:click="setCategory('{{ $tourn['cat'] }}')" class="w-full flex items-center justify-between p-2.5 rounded-xl bg-slate-950/60 hover:bg-slate-800 border border-slate-800 hover:border-amber-400/30 text-slate-300 hover:text-amber-400 transition text-left">
                                <span class="font-medium truncate">{{ $tourn['name'] }}</span>
                                <span class="text-slate-600 text-[10px]">→</span>
                            </button>
                        @endforeach
                    </div>
                </div>

                <!-- Latest 3 Cricket News Widget -->
                <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-5 backdrop-blur shadow-md">
                    <div class="flex items-center justify-between pb-3 border-b border-slate-800 mb-4">
                        <div class="flex items-center space-x-2">
                            <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                            <h3 class="text-xs font-bold text-slate-200 uppercase tracking-wider">Latest News</h3>
                        </div>
                        <a href="{{ route('news.index') }}" wire:navigate class="text-[11px] text-amber-400 hover:underline font-medium">View All</a>
                    </div>

                    <div class="space-y-4">
                        @forelse($latestNews as $item)
                            <a href="{{ route('news.show', ['article' => $item->slug]) }}" wire:navigate class="group flex items-start space-x-3 transition">
                                @php
                                    $newsImg = $item->resolved_image_url;
                                @endphp
                                @if(!empty($newsImg))
                                    <div class="w-16 h-16 shrink-0 overflow-hidden rounded-xl border border-slate-800 bg-slate-950">
                                        <img src="{{ $newsImg }}" 
                                             alt="{{ $item->title }}" 
                                             class="w-full h-full object-cover group-hover:scale-105 transition duration-300" 
                                             loading="lazy" />
                                    </div>
                                @endif
                                <div class="flex-1 min-w-0">
                                    <h4 class="text-xs font-semibold text-slate-200 group-hover:text-amber-400 line-clamp-2 transition leading-snug">
                                        {{ $item->title }}
                                    </h4>
                                    <span class="text-[10px] text-slate-500 mt-1 block font-mono">
                                        {{ $item->published_at ? $item->published_at->diffForHumans() : 'Recently' }}
                                    </span>
                                </div>
                            </a>
                        @empty
                            <p class="text-xs text-slate-500 py-2">No recent news available.</p>
                        @endforelse
                    </div>
                </div>

            </aside>

        </div>
    </main>

    <!-- Desktop-only Footer (hidden on mobile) -->
    <x-footer />

    <!-- Mobile Bottom App Bar -->
    <x-bottom-nav />
</div>
