<div class="min-h-screen bg-slate-950 text-slate-100 font-sans antialiased pb-24 selection:bg-amber-500 selection:text-black">
    <!-- Top Global Header -->
    <x-header />

    <!-- Sub-Navbar / Breadcrumb Strip -->
    <nav class="border-b border-slate-800/80 bg-slate-900/40 backdrop-blur py-2.5 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto flex items-center justify-between text-xs text-slate-400 gap-3">
            <div class="flex items-center space-x-2 truncate">
                <a href="/" wire:navigate class="hover:text-amber-400 transition shrink-0">Home</a>
                <span>/</span>
                <span class="text-amber-400 font-semibold truncate">ICC Cricket Rankings</span>
            </div>
            <div class="flex items-center space-x-1.5 text-[11px] text-slate-400 bg-slate-900 px-2.5 py-1 rounded-lg border border-slate-800 shrink-0">
                <span class="w-2 h-2 rounded-full {{ $syncedAtFormatted ? 'bg-emerald-400' : 'bg-amber-400 animate-pulse' }}"></span>
                <span>{{ $syncedAtFormatted ? 'Synced: ' . $syncedAtFormatted : 'Daily Redis Cache' }}</span>
            </div>
        </div>
    </nav>

    <!-- Main Container -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

            <!-- LEFT/MAIN COLUMN: Rankings Leaderboard (lg:col-span-8) -->
            <section class="lg:col-span-8 space-y-5">

                <!-- Page Header -->
                <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3 pb-3 border-b border-slate-800/80">
                    <div>
                        <h1 class="text-xl sm:text-2xl font-black tracking-tight text-white flex items-center gap-2">
                            <span>🏆</span>
                            <span>ICC {{ $formatLabels[$format] ?? strtoupper($format) }} {{ $categoryLabels[$category] ?? ucfirst($category) }} Rankings</span>
                        </h1>
                        <p class="text-xs text-slate-400 mt-1">
                            Official ICC player and team ratings, points &amp; rank movements across Test, ODI, and T20I cricket.
                        </p>
                    </div>
                    <span class="self-start sm:self-auto px-3 py-1 rounded-full text-xs font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20 shadow-sm shrink-0">
                        TOP {{ $totalCount }} RANKED
                    </span>
                </div>

                <!-- Category Tabs (Batting, Bowling, All-Rounders, Teams) -->
                <div class="flex items-center gap-2 overflow-x-auto pb-1 scrollbar-none">
                    @php
                        $categoryTabs = [
                            'batting' => ['label' => 'Batting', 'icon' => '🏏'],
                            'bowling' => ['label' => 'Bowling', 'icon' => '🎯'],
                            'allrounder' => ['label' => 'All-Rounders', 'icon' => '⚡'],
                            'teams' => ['label' => 'Teams', 'icon' => '🛡️'],
                        ];
                    @endphp
                    @foreach($categoryTabs as $catKey => $catMeta)
                        <button
                            type="button"
                            wire:click="setCategory('{{ $catKey }}')"
                            class="px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition whitespace-nowrap flex items-center gap-2 {{ $category === $catKey ? 'bg-amber-500 text-slate-950 shadow-lg shadow-amber-500/20' : 'bg-slate-900/90 text-slate-300 hover:text-white border border-slate-800 hover:border-slate-700' }}"
                        >
                            <span>{{ $catMeta['icon'] }}</span>
                            <span>{{ $catMeta['label'] }}</span>
                        </button>
                    @endforeach
                </div>

                <!-- Format Pills + Search Filter Bar -->
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-slate-900/80 p-2.5 rounded-2xl border border-slate-800">
                    <!-- Format Pills -->
                    <div class="flex items-center gap-1.5 overflow-x-auto pb-1 sm:pb-0 scrollbar-none">
                        @foreach($formatLabels as $fmtKey => $fmtLabel)
                            @php
                                $isAvailable = in_array($fmtKey, $availableFormats, true);
                            @endphp
                            <button
                                type="button"
                                wire:click="setFormat('{{ $fmtKey }}')"
                                @if(!$isAvailable) disabled @endif
                                class="px-4 py-1.5 rounded-xl text-xs font-black uppercase tracking-wider transition whitespace-nowrap {{ $format === $fmtKey ? 'bg-amber-400 text-slate-950 shadow-md shadow-amber-500/20' : ($isAvailable ? 'text-slate-400 hover:text-white hover:bg-slate-800/70' : 'text-slate-600 cursor-not-allowed') }}"
                            >
                                {{ $fmtLabel }}
                            </button>
                        @endforeach
                    </div>

                    <!-- Search Input -->
                    <div class="relative sm:w-64 shrink-0">
                        <input
                            type="text"
                            wire:model.live.debounce.250ms="search"
                            placeholder="{{ $category === 'teams' ? 'Filter team...' : 'Search player or country...' }}"
                            class="w-full bg-slate-950/80 border border-slate-700/80 focus:border-amber-400 rounded-xl px-3 py-1.5 pl-8 pr-7 text-xs text-white placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-400 transition"
                        />
                        <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 top-2.5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35m1.35-5.65a7 7 0 11-14 0 7 7 0 0114 0z"/>
                        </svg>
                        @if($search !== '')
                            <button
                                type="button"
                                wire:click="$set('search', '')"
                                class="absolute right-2.5 top-1.5 text-slate-400 hover:text-white text-xs"
                                aria-label="Clear search"
                            >
                                ✕
                            </button>
                        @endif
                    </div>
                </div>

                <!-- #1 Leader Spotlight Card -->
                @if($topLeader)
                    @php
                        $leaderInitials = strtoupper(mb_substr(preg_replace('/[^A-Za-z0-9]/', '', $topLeader['name'] ?? 'CW'), 0, 2) ?: 'CW');
                    @endphp
                    <div class="bg-gradient-to-br from-slate-900 via-slate-900 to-amber-950/30 border border-amber-500/30 rounded-2xl p-4 sm:p-5 shadow-xl relative overflow-hidden">
                        <div class="flex items-center justify-between gap-4">
                            <div class="flex items-center gap-3.5 min-w-0">
                                <div class="relative shrink-0">
                                    <div class="w-14 h-14 sm:w-16 sm:h-16 rounded-full bg-slate-950 border-2 border-amber-400 overflow-hidden flex items-center justify-center shadow-lg shadow-amber-500/15">
                                        @if(!empty($topLeader['image_url']))
                                            <img
                                                src="{{ $topLeader['image_url'] }}"
                                                alt="{{ $topLeader['name'] }}"
                                                class="w-full h-full object-cover"
                                                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                            />
                                            <span style="display:none" class="w-full h-full items-center justify-center text-sm font-black text-amber-400 font-mono">
                                                {{ $leaderInitials }}
                                            </span>
                                        @else
                                            <span class="text-sm font-black text-amber-400 font-mono">
                                                {{ $leaderInitials }}
                                            </span>
                                        @endif
                                    </div>
                                    <span class="absolute -bottom-1 -right-1 px-1.5 py-0.5 rounded-full bg-amber-400 text-slate-950 text-[10px] font-black shadow">
                                        #1
                                    </span>
                                </div>

                                <div class="min-w-0">
                                    <span class="inline-flex items-center gap-1 text-[10px] font-black uppercase tracking-widest text-amber-400">
                                        <span>World No. 1</span>
                                        <span>•</span>
                                        <span>{{ $formatLabels[$format] ?? strtoupper($format) }}</span>
                                    </span>
                                    <h2 class="text-base sm:text-xl font-black text-white truncate mt-0.5">
                                        {{ $topLeader['name'] }}
                                    </h2>
                                    @if($category !== 'teams' && !empty($topLeader['country']))
                                        <p class="text-xs text-slate-400 truncate">{{ $topLeader['country'] }}</p>
                                    @endif
                                </div>
                            </div>

                            <div class="text-right shrink-0 space-y-1">
                                <div class="text-[10px] uppercase tracking-wider font-bold text-slate-400">Rating</div>
                                <div class="text-2xl sm:text-3xl font-black text-amber-400 font-mono leading-none">
                                    {{ $topLeader['rating'] }}
                                </div>
                                <div class="text-[11px] text-slate-400 font-mono">
                                    Pts: <span class="text-slate-200 font-bold">{{ $topLeader['points'] }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Rankings Leaderboard Table -->
                <div class="bg-slate-900/90 border border-slate-800 rounded-2xl overflow-hidden shadow-xl">
                    @if(!empty($rankings))
                        <div class="overflow-x-auto">
                            <table class="w-full text-left border-collapse">
                                <thead>
                                    <tr class="border-b border-slate-800 bg-slate-950/60 text-[10px] sm:text-[11px] font-black uppercase tracking-wider text-slate-400">
                                        <th class="py-3 px-3 sm:px-4 w-16 text-center">Pos</th>
                                        <th class="py-3 px-3 sm:px-4">{{ $category === 'teams' ? 'Team' : 'Player' }}</th>
                                        @if($category !== 'teams')
                                            <th class="py-3 px-3 sm:px-4 hidden sm:table-cell">Country</th>
                                        @endif
                                        <th class="py-3 px-3 sm:px-4 text-right">Rating</th>
                                        <th class="py-3 px-3 sm:px-4 text-right hidden xs:table-cell sm:table-cell">Points</th>
                                        <th class="py-3 px-3 sm:px-4 text-center w-20">Trend</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800/70 text-xs sm:text-sm">
                                    @foreach($rankings as $row)
                                        @php
                                            $rankNum = (int) ($row['rank'] ?? 0);
                                            $rowInitials = strtoupper(mb_substr(preg_replace('/[^A-Za-z0-9]/', '', $row['name'] ?? 'CW'), 0, 2) ?: 'CW');
                                            $rankBadgeClass = match ($rankNum) {
                                                1 => 'bg-amber-400/20 text-amber-300 border-amber-400/40',
                                                2 => 'bg-slate-300/15 text-slate-200 border-slate-400/30',
                                                3 => 'bg-orange-500/20 text-orange-300 border-orange-500/30',
                                                default => 'bg-slate-800/80 text-slate-400 border-slate-700/60',
                                            };
                                            $trendDir = $row['trend_direction'] ?? 'flat';
                                            $trendLabel = $row['trend'] ?? 'Flat';
                                        @endphp
                                        <tr class="hover:bg-slate-800/40 transition-colors group">
                                            <!-- Rank -->
                                            <td class="py-3 px-3 sm:px-4 text-center">
                                                <span class="inline-flex items-center justify-center w-7 h-7 rounded-lg text-xs font-black font-mono border {{ $rankBadgeClass }}">
                                                    {{ $rankNum }}
                                                </span>
                                            </td>

                                            <!-- Player / Team -->
                                            <td class="py-3 px-3 sm:px-4">
                                                <div class="flex items-center gap-3 min-w-0">
                                                    <div class="w-9 h-9 sm:w-10 sm:h-10 rounded-full bg-slate-950 border border-slate-700/80 overflow-hidden flex items-center justify-center shrink-0">
                                                        @if(!empty($row['image_url']))
                                                            <img
                                                                src="{{ $row['image_url'] }}"
                                                                alt="{{ $row['name'] }}"
                                                                loading="lazy"
                                                                class="w-full h-full object-cover"
                                                                onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                                            />
                                                            <span style="display:none" class="w-full h-full items-center justify-center text-[11px] font-black text-amber-400 font-mono">
                                                                {{ $rowInitials }}
                                                            </span>
                                                        @else
                                                            <span class="text-[11px] font-black text-amber-400 font-mono">
                                                                {{ $rowInitials }}
                                                            </span>
                                                        @endif
                                                    </div>

                                                    <div class="min-w-0">
                                                        <div class="font-bold text-white group-hover:text-amber-400 transition truncate">
                                                            {{ $row['name'] }}
                                                        </div>
                                                        @if($category !== 'teams' && !empty($row['country']))
                                                            <div class="text-[11px] text-slate-400 sm:hidden truncate">
                                                                {{ $row['country'] }}
                                                            </div>
                                                        @endif
                                                    </div>
                                                </div>
                                            </td>

                                            <!-- Country (Desktop) -->
                                            @if($category !== 'teams')
                                                <td class="py-3 px-3 sm:px-4 hidden sm:table-cell">
                                                    <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-slate-950/70 border border-slate-800 text-xs font-medium text-slate-300">
                                                        {{ $row['country'] ?: ($row['country_short'] ?: '—') }}
                                                    </span>
                                                </td>
                                            @endif

                                            <!-- Rating -->
                                            <td class="py-3 px-3 sm:px-4 text-right font-mono font-black text-amber-400">
                                                {{ $row['rating'] }}
                                            </td>

                                            <!-- Points -->
                                            <td class="py-3 px-3 sm:px-4 text-right font-mono font-semibold text-slate-300 hidden xs:table-cell sm:table-cell">
                                                {{ $row['points'] }}
                                            </td>

                                            <!-- Trend -->
                                            <td class="py-3 px-3 sm:px-4 text-center">
                                                @if($trendDir === 'up')
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30">
                                                        <span>▲</span>
                                                        <span>{{ $trendLabel }}</span>
                                                    </span>
                                                @elseif($trendDir === 'down')
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-bold bg-rose-500/15 text-rose-400 border border-rose-500/30">
                                                        <span>▼</span>
                                                        <span>{{ $trendLabel }}</span>
                                                    </span>
                                                @else
                                                    <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-800 text-slate-400 border border-slate-700/60">
                                                        <span>•</span>
                                                        <span>{{ $trendLabel }}</span>
                                                    </span>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <div class="text-center py-16 px-6">
                            <span class="text-4xl block mb-3">📊</span>
                            <h3 class="text-base font-bold text-slate-200">
                                @if($search !== '')
                                    No Rankings Match "{{ $search }}"
                                @else
                                    ICC Rankings Cache Not Yet Populated
                                @endif
                            </h3>
                            <p class="text-xs text-slate-400 mt-1 max-w-md mx-auto">
                                @if($search !== '')
                                    Try clearing your search query or switching to another format or category.
                                @else
                                    Rankings are served strictly from Redis with zero live API drain and refresh automatically via the daily sync schedule.
                                @endif
                            </p>
                            @if($search !== '')
                                <button
                                    type="button"
                                    wire:click="$set('search', '')"
                                    class="mt-4 px-4 py-1.5 rounded-xl text-xs font-bold bg-slate-800 text-amber-400 border border-slate-700 hover:bg-slate-700 transition"
                                >
                                    Clear Search
                                </button>
                            @endif
                        </div>
                    @endif
                </div>

            </section>

            <!-- RIGHT SIDEBAR (lg:col-span-4) -->
            <aside class="lg:col-span-4 space-y-6 lg:sticky lg:top-20">

                <!-- Admin Managed Ad Banner Slot (Zero Whitespace Rule) -->
                @if(!empty($sidebarBanner) && $sidebarBanner->image_path)
                    <div class="bg-slate-900/40 p-2 rounded-2xl border border-slate-800 shadow-lg">
                        <span class="text-[10px] uppercase font-bold text-slate-500 tracking-wider block mb-1.5 text-center">Sponsored</span>
                        <a href="{{ $sidebarBanner->target_url ?? '#' }}" target="_blank" rel="noopener noreferrer" class="block overflow-hidden rounded-xl border border-slate-800/60">
                            <img
                                src="{{ asset('storage/' . $sidebarBanner->image_path) }}"
                                alt="Advertisement"
                                loading="lazy"
                                class="w-full h-auto max-h-[300px] object-cover mx-auto"
                            />
                        </a>
                    </div>
                @else
                    <x-ad-slot slot="rankings_sidebar" />
                @endif

                <!-- Quick Format & Category Directory -->
                <div class="rounded-2xl p-4 bg-slate-900/90 border border-slate-800 shadow-xl space-y-3">
                    <h3 class="text-xs font-black uppercase tracking-wider text-amber-400 flex items-center gap-1.5 pb-2 border-b border-slate-800">
                        <span>🧭</span>
                        <span>Quick Rankings Matrix</span>
                    </h3>
                    <div class="grid grid-cols-3 gap-2 text-xs">
                        @foreach($categoryLabels as $catKey => $catLabel)
                            @foreach($formatLabels as $fmtKey => $fmtLabel)
                                <a
                                    href="{{ route('rankings', ['category' => $catKey, 'format' => $fmtKey]) }}"
                                    wire:navigate
                                    class="px-2.5 py-2 rounded-xl border text-center font-semibold transition truncate {{ ($category === $catKey && $format === $fmtKey) ? 'bg-amber-500/20 text-amber-400 border-amber-500/40 font-bold' : 'bg-slate-950/70 text-slate-300 border-slate-800 hover:border-slate-700 hover:text-white' }}"
                                >
                                    {{ $fmtLabel }} {{ $catKey === 'allrounder' ? 'AR' : $catLabel }}
                                </a>
                            @endforeach
                        @endforeach
                    </div>
                </div>

                <!-- Latest Cricket News Widget -->
                @if($latestNews->isNotEmpty())
                    <div class="rounded-2xl p-4 bg-slate-900/90 border border-slate-800 shadow-xl space-y-3">
                        <div class="flex items-center justify-between pb-2 border-b border-slate-800">
                            <h3 class="text-xs font-black uppercase tracking-wider text-amber-400 flex items-center gap-1.5">
                                <span>📰</span>
                                <span>Latest Cricket News</span>
                            </h3>
                            <a href="{{ route('news.index') }}" wire:navigate class="text-[11px] text-slate-400 hover:text-amber-400 transition font-semibold">
                                View All →
                            </a>
                        </div>

                        <div class="space-y-3">
                            @foreach($latestNews as $article)
                                <a href="{{ route('news.show', $article->slug) }}" wire:navigate class="block group">
                                    <h4 class="text-xs font-bold text-slate-200 group-hover:text-amber-400 transition line-clamp-2 leading-snug">
                                        {{ $article->title }}
                                    </h4>
                                    @if($article->published_at)
                                        <span class="text-[10px] text-slate-500 block mt-1">
                                            {{ $article->published_at->diffForHumans() }}
                                        </span>
                                    @endif
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

            </aside>

        </div>
    </main>

    <!-- Desktop Footer -->
    <x-footer />

    <!-- Mobile Bottom Navigation -->
    <x-bottom-nav />
</div>
