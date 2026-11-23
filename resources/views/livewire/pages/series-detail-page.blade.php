<div class="min-h-screen bg-slate-950 text-slate-100 font-sans antialiased pb-24 selection:bg-amber-500 selection:text-black">
    <!-- Top Global Header -->
    <x-header />

    <!-- Sub-Navbar / Breadcrumb Strip -->
    <nav class="border-b border-slate-800/80 bg-slate-900/40 backdrop-blur py-2.5 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto flex items-center justify-between text-xs text-slate-400">
            <div class="flex items-center space-x-2 truncate">
                <a href="/" wire:navigate class="hover:text-amber-400 transition shrink-0">Home</a>
                <span>/</span>
                <a href="{{ route('series.index') }}" wire:navigate class="hover:text-amber-400 transition shrink-0">Cricket Series</a>
                <span>/</span>
                <span class="text-amber-400 font-semibold truncate">{{ $series['series_name'] }}</span>
            </div>
            <div class="flex items-center space-x-1.5 text-[11px] text-slate-400 bg-slate-900 px-2.5 py-1 rounded-lg border border-slate-800 shrink-0">
                <span class="w-2 h-2 rounded-full {{ $series['is_live'] ? 'bg-rose-500 animate-ping' : 'bg-emerald-400' }}"></span>
                <span>{{ $series['is_live'] ? 'Live Series' : 'Coverage Active' }}</span>
            </div>
        </div>
    </nav>

    <!-- Main Container Grid -->
    <main class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 pt-6">

        <!-- Cricbuzz-Style Series Hero Card Header -->
        <div class="bg-gradient-to-r from-slate-900 via-slate-900 to-slate-800/80 border border-slate-800 rounded-2xl p-5 sm:p-6 mb-6 shadow-2xl relative overflow-hidden">
            <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                <div class="space-y-2">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="px-2.5 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-slate-800 text-amber-400 border border-amber-500/20">
                            {{ $series['format'] ?? 'T20' }}
                        </span>

                        @if(!empty($series['is_live']))
                            <span class="inline-flex items-center gap-1.5 text-[10px] font-black uppercase tracking-wider bg-rose-500/20 text-rose-400 px-2 py-0.5 rounded-md border border-rose-500/30">
                                <span class="w-1.5 h-1.5 bg-rose-500 rounded-full animate-ping"></span>
                                <span>LIVE NOW</span>
                            </span>
                        @endif

                        @if(!empty($series['date_range']))
                            <span class="text-xs text-slate-400 font-mono flex items-center gap-1.5 bg-slate-950/60 px-2.5 py-0.5 rounded-md border border-slate-800">
                                <span>📅</span>
                                <span>{{ $series['date_range'] }}</span>
                            </span>
                        @endif
                    </div>

                    <h1 class="text-xl sm:text-2xl lg:text-3xl font-black text-white tracking-tight">
                        {{ $series['series_name'] }}
                    </h1>

                    <p class="text-xs sm:text-sm text-slate-300 font-medium flex items-center gap-2">
                        <span class="text-amber-400">⚡</span>
                        <span>{{ $series['summary_line'] }}</span>
                    </p>
                </div>

                <!-- Right Action Buttons -->
                <div class="flex items-center gap-2.5 self-start md:self-center shrink-0">
                    <button 
                        type="button" 
                        onclick="if(navigator.share){navigator.share({title: '{{ addslashes($series['series_name']) }} - Cricwire', url: window.location.href})}else{navigator.clipboard.writeText(window.location.href);alert('Link copied to clipboard!')}" 
                        class="px-4 py-2 rounded-xl text-xs font-bold bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700/80 transition flex items-center gap-1.5"
                    >
                        <span>🔗</span>
                        <span>Share Hub</span>
                    </button>
                </div>
            </div>
        </div>

        <!-- Cricbuzz-Style Horizontal Navigation Tabs -->
        <div class="flex items-center gap-2 border-b border-slate-800/80 pb-3 mb-6 overflow-x-auto scrollbar-none">
            <!-- Matches Tab -->
            <button 
                wire:click="setTab('matches')" 
                class="px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition flex items-center gap-2 whitespace-nowrap {{ $tab === 'matches' ? 'bg-amber-500 text-slate-950 shadow-lg shadow-amber-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}"
            >
                <span>Matches</span>
                <span class="text-[10px] px-1.5 py-0.5 rounded-full {{ $tab === 'matches' ? 'bg-black/20 text-slate-950 font-black' : 'bg-slate-800 text-slate-400' }}">
                    {{ ($series['live_count'] ?? 0) + ($series['upcoming_count'] ?? 0) + ($series['completed_count'] ?? 0) }}
                </span>
            </button>

            <!-- Points Table Tab (RENDERED ONLY when hasPointsTable) -->
            @if($hasPointsTable)
                <button 
                    wire:click="setTab('points-table')" 
                    class="px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition flex items-center gap-2 whitespace-nowrap {{ $tab === 'points-table' ? 'bg-amber-500 text-slate-950 shadow-lg shadow-amber-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}"
                >
                    <span>📊</span>
                    <span>Points Table</span>
                </button>
            @endif

            <!-- News Tab -->
            <button 
                wire:click="setTab('news')" 
                class="px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition flex items-center gap-2 whitespace-nowrap {{ $tab === 'news' ? 'bg-amber-500 text-slate-950 shadow-lg shadow-amber-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}"
            >
                <span>📰</span>
                <span>News</span>
                @if(count($seriesNews) > 0)
                    <span class="text-[10px] px-1.5 py-0.5 rounded-full {{ $tab === 'news' ? 'bg-black/20 text-slate-950 font-black' : 'bg-slate-800 text-slate-400' }}">
                        {{ count($seriesNews) }}
                    </span>
                @endif
            </button>

            <!-- Squads Tab -->
            <button 
                wire:click="setTab('squads')" 
                class="px-4 py-2 rounded-xl text-xs sm:text-sm font-bold transition flex items-center gap-2 whitespace-nowrap {{ $tab === 'squads' ? 'bg-amber-500 text-slate-950 shadow-lg shadow-amber-500/20' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}"
            >
                <span>👥</span>
                <span>Squads</span>
            </button>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-12 gap-8 items-start">

            <!-- LEFT/MAIN COLUMN (lg:col-span-8) -->
            <section class="lg:col-span-8 space-y-6">

                <!-- TAB 1: MATCHES -->
                @if($tab === 'matches')
                    <!-- Section 1: 🔴 Live Matches -->
                    @if(!empty($series['matches']['live']))
                        <div class="space-y-3">
                            <div class="flex items-center justify-between pb-2 border-b border-slate-800">
                                <h3 class="text-sm font-black uppercase tracking-wider text-rose-400 flex items-center gap-2">
                                    <span class="w-2 h-2 rounded-full bg-rose-500 animate-ping"></span>
                                    <span>🔴 Live Matches ({{ count($series['matches']['live']) }})</span>
                                </h3>
                            </div>
                            <div class="space-y-3">
                                @foreach($series['matches']['live'] as $match)
                                    <x-match-card :match="$match" />
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Section 2: 📅 Upcoming Fixtures -->
                    @if(!empty($series['matches']['upcoming']))
                        <div class="space-y-3">
                            <div class="flex items-center justify-between pb-2 border-b border-slate-800">
                                <h3 class="text-sm font-black uppercase tracking-wider text-amber-400 flex items-center gap-2">
                                    <span>📅</span>
                                    <span>Upcoming Fixtures ({{ count($series['matches']['upcoming']) }})</span>
                                </h3>
                            </div>
                            <div class="space-y-3">
                                @foreach($series['matches']['upcoming'] as $match)
                                    <x-match-card :match="$match" status="upcoming" />
                                @endforeach
                            </div>
                        </div>
                    @endif

                    <!-- Section 3: ✅ Completed Results -->
                    @if(!empty($series['matches']['completed']))
                        <div class="space-y-3">
                            <div class="flex items-center justify-between pb-2 border-b border-slate-800">
                                <h3 class="text-sm font-black uppercase tracking-wider text-emerald-400 flex items-center gap-2">
                                    <span>✅</span>
                                    <span>Match Results ({{ count($series['matches']['completed']) }})</span>
                                </h3>
                            </div>
                            <div class="space-y-3">
                                @foreach($series['matches']['completed'] as $match)
                                    <x-match-card :match="$match" status="finished" />
                                @endforeach
                            </div>
                        </div>
                    @endif

                    @if(empty($series['matches']['live']) && empty($series['matches']['upcoming']) && empty($series['matches']['completed']))
                        <div class="text-center py-16 bg-slate-900/40 rounded-2xl border border-slate-800">
                            <span class="text-4xl">🏏</span>
                            <h3 class="text-base font-bold text-slate-200 mt-3">No Matches Scheduled</h3>
                            <p class="text-xs text-slate-400 mt-1">There are no upcoming or completed fixtures scheduled for this tournament yet.</p>
                        </div>
                    @endif
                @endif

                <!-- TAB 2: POINTS TABLE (when hasPointsTable) -->
                @if($tab === 'points-table' && $hasPointsTable)
                    <div class="space-y-4">
                        <div class="flex items-center justify-between pb-2 border-b border-slate-800">
                            <h3 class="text-sm font-black uppercase tracking-wider text-amber-400 flex items-center gap-2">
                                <span>📊</span>
                                <span>{{ $series['series_name'] }} Standings</span>
                            </h3>
                            <span class="text-[11px] text-slate-400">Top 4 qualify for playoffs</span>
                        </div>

                        <div class="overflow-x-auto rounded-2xl border border-slate-800 bg-slate-900/90 shadow-xl">
                            <table class="w-full text-left text-xs border-collapse">
                                <thead>
                                    <tr class="bg-slate-950/80 text-slate-400 border-b border-slate-800 uppercase font-black tracking-wider text-[11px]">
                                        <th class="py-3 px-3 text-center w-10">#</th>
                                        <th class="py-3 px-4">Team</th>
                                        <th class="py-3 px-3 text-center">M</th>
                                        <th class="py-3 px-3 text-center">W</th>
                                        <th class="py-3 px-3 text-center">L</th>
                                        <th class="py-3 px-3 text-center">NR</th>
                                        <th class="py-3 px-3 text-center text-amber-400 font-black">Pts</th>
                                        <th class="py-3 px-3 text-right">NRR</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800/60 font-medium">
                                    @foreach($pointsTable as $row)
                                        @php
                                            $isTop4 = ($row['rank'] ?? 99) <= 4;
                                        @endphp
                                        <tr class="hover:bg-slate-800/40 transition {{ $isTop4 ? 'bg-amber-500/[0.02]' : '' }}">
                                            <td class="py-3 px-3 text-center font-bold {{ $isTop4 ? 'text-amber-400' : 'text-slate-500' }}">
                                                {{ $row['rank'] }}
                                            </td>
                                            <td class="py-3 px-4">
                                                <div class="flex items-center space-x-2">
                                                    @if($isTop4)
                                                        <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                                                    @endif
                                                    <span class="font-bold text-white">{{ $row['team'] }}</span>
                                                    @if(!empty($row['team_short']))
                                                        <span class="text-[10px] text-slate-400 uppercase font-mono">({{ $row['team_short'] }})</span>
                                                    @endif
                                                </div>
                                            </td>
                                            <td class="py-3 px-3 text-center text-slate-300 font-mono">{{ $row['played'] }}</td>
                                            <td class="py-3 px-3 text-center text-emerald-400 font-mono font-bold">{{ $row['won'] }}</td>
                                            <td class="py-3 px-3 text-center text-rose-400 font-mono">{{ $row['lost'] }}</td>
                                            <td class="py-3 px-3 text-center text-slate-400 font-mono">{{ $row['nr'] }}</td>
                                            <td class="py-3 px-3 text-center font-black text-amber-400 text-sm font-mono bg-amber-500/5">
                                                {{ $row['points'] }}
                                            </td>
                                            <td class="py-3 px-3 text-right text-slate-300 font-mono font-semibold">{{ $row['nrr'] }}</td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    </div>
                @endif

                <!-- TAB 3: NEWS -->
                @if($tab === 'news')
                    <div class="space-y-4">
                        <div class="flex items-center justify-between pb-2 border-b border-slate-800">
                            <h3 class="text-sm font-black uppercase tracking-wider text-amber-400 flex items-center gap-2">
                                <span>📰</span>
                                <span>Series News &amp; Match Analysis</span>
                            </h3>
                            <span class="text-[11px] text-slate-400">{{ count($seriesNews) }} Articles</span>
                        </div>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            @forelse($seriesNews as $article)
                                <a 
                                    href="{{ route('news.show', $article->slug) }}" 
                                    wire:navigate 
                                    class="bg-slate-900/90 border border-slate-800 hover:border-amber-500/40 rounded-2xl overflow-hidden transition-all duration-200 group flex flex-col shadow-lg"
                                >
                                    @if($article->featured_image)
                                        <div class="aspect-video w-full overflow-hidden bg-slate-950">
                                            <img 
                                                src="{{ $article->featured_image_url }}" 
                                                alt="{{ $article->title }}" 
                                                class="w-full h-full object-cover group-hover:scale-105 transition duration-300"
                                                loading="lazy"
                                            />
                                        </div>
                                    @endif

                                    <div class="p-4 flex-1 flex flex-col justify-between space-y-2">
                                        <div class="space-y-1.5">
                                            <div class="flex items-center gap-2">
                                                <span class="px-2 py-0.5 rounded text-[10px] font-bold uppercase tracking-wider bg-amber-500/10 text-amber-400 border border-amber-500/20">
                                                    {{ ucfirst($article->category ?? 'News') }}
                                                </span>
                                                @if($article->published_at)
                                                    <span class="text-[11px] text-slate-500">
                                                        {{ $article->published_at->diffForHumans() }}
                                                    </span>
                                                @endif
                                            </div>

                                            <h4 class="text-sm font-bold text-white group-hover:text-amber-400 transition-colors line-clamp-2 leading-snug">
                                                {{ $article->title }}
                                            </h4>

                                            @if($article->summary)
                                                <p class="text-xs text-slate-400 line-clamp-2 leading-relaxed">
                                                    {{ $article->summary }}
                                                </p>
                                            @endif
                                        </div>

                                        <div class="pt-2 text-xs font-semibold text-amber-400 flex items-center gap-1">
                                            <span>Read full report</span>
                                            <span>→</span>
                                        </div>
                                    </div>
                                </a>
                            @empty
                                <div class="col-span-full text-center py-16 bg-slate-900/40 rounded-2xl border border-slate-800">
                                    <span class="text-4xl">📰</span>
                                    <h3 class="text-base font-bold text-slate-200 mt-3">No News Found</h3>
                                    <p class="text-xs text-slate-400 mt-1">There are no editorial reports published for this tournament yet.</p>
                                </div>
                            @endforelse
                        </div>
                    </div>
                @endif

                <!-- TAB 4: SQUADS -->
                @if($tab === 'squads')
                    <div class="space-y-4">
                        <div class="flex items-center justify-between pb-2 border-b border-slate-800">
                            <h3 class="text-sm font-black uppercase tracking-wider text-amber-400 flex items-center gap-2">
                                <span>👥</span>
                                <span>Participating Team Squads</span>
                            </h3>
                        </div>

                        @if(!empty($squads))
                            <!-- Team Selector Pills -->
                            <div class="flex items-center gap-2 overflow-x-auto pb-2 scrollbar-none">
                                @foreach($squads as $idx => $teamSquad)
                                    <button 
                                        wire:click="selectTeamSquad({{ $idx }})" 
                                        class="px-4 py-2 rounded-xl text-xs font-bold transition whitespace-nowrap {{ ($selectedTeamSquadIndex ?? 0) === $idx ? 'bg-amber-500 text-slate-950 shadow-md shadow-amber-500/20' : 'bg-slate-900 text-slate-400 hover:text-white border border-slate-800' }}"
                                    >
                                        {{ $teamSquad['team_name'] }}
                                    </button>
                                @endforeach
                            </div>

                            @php
                                $activeSquad = $squads[$selectedTeamSquadIndex ?? 0] ?? ($squads[0] ?? null);
                            @endphp

                            @if($activeSquad)
                                <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-5 shadow-xl space-y-4">
                                    <div class="flex items-center justify-between pb-3 border-b border-slate-800/80">
                                        <h4 class="text-base font-bold text-white flex items-center gap-2">
                                            <span>🏏</span>
                                            <span>{{ $activeSquad['team_name'] }} Squad</span>
                                        </h4>
                                        <span class="text-xs text-slate-400 font-mono">{{ count($activeSquad['players'] ?? []) }} Players</span>
                                    </div>

                                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 gap-3">
                                        @forelse($activeSquad['players'] ?? [] as $player)
                                            <div class="p-3 rounded-xl bg-slate-950/70 border border-slate-800/80 hover:border-slate-700 transition flex items-center space-x-3">
                                                <div class="w-10 h-10 rounded-full bg-slate-800 border border-slate-700 flex items-center justify-center shrink-0 overflow-hidden">
                                                    @if(!empty($player['image_url']))
                                                        <img src="{{ $player['image_url'] }}" alt="{{ $player['name'] }}" class="w-full h-full object-cover" loading="lazy" />
                                                    @else
                                                        <span class="text-xs font-black text-amber-400 font-mono">
                                                            {{ strtoupper(substr($player['name'] ?? 'P', 0, 2)) }}
                                                        </span>
                                                    @endif
                                                </div>

                                                <div class="min-w-0 flex-1">
                                                    <div class="text-xs font-bold text-white truncate flex items-center gap-1">
                                                        <span>{{ $player['name'] }}</span>
                                                        @if(!empty($player['is_captain']))
                                                            <span class="text-[9px] px-1 py-0.2 rounded bg-amber-500/20 text-amber-400 font-black" title="Captain">(C)</span>
                                                        @endif
                                                        @if(!empty($player['is_keeper']))
                                                            <span class="text-[9px] px-1 py-0.2 rounded bg-sky-500/20 text-sky-400 font-black" title="Wicket-Keeper">(WK)</span>
                                                        @endif
                                                    </div>
                                                    <div class="text-[11px] text-slate-400 truncate">
                                                        {{ $player['role'] ?? 'Player' }}
                                                    </div>
                                                </div>
                                            </div>
                                        @empty
                                            <div class="col-span-full text-center py-8 text-xs text-slate-500">
                                                Squad list not yet officially submitted.
                                            </div>
                                        @endforelse
                                    </div>
                                </div>
                            @endif
                        @else
                            <div class="text-center py-16 bg-slate-900/40 rounded-2xl border border-slate-800">
                                <span class="text-4xl">👥</span>
                                <h3 class="text-base font-bold text-slate-200 mt-3">Squads Unavailable</h3>
                                <p class="text-xs text-slate-400 mt-1">Full team rosters will be updated as soon as team line-ups are announced.</p>
                            </div>
                        @endif
                    </div>
                @endif

            </section>

            <!-- RIGHT SIDEBAR (lg:col-span-4) -->
            <aside class="lg:col-span-4 space-y-6">

                <!-- Mini Points Table Widget (when hasPointsTable) -->
                @if($hasPointsTable && !empty($pointsTable))
                    <div class="rounded-2xl p-4 bg-slate-900/90 border border-slate-800 shadow-xl space-y-3">
                        <div class="flex items-center justify-between pb-2 border-b border-slate-800">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-amber-400 flex items-center gap-1.5">
                                <span>📊</span>
                                <span>Standings</span>
                            </h4>
                            <button wire:click="setTab('points-table')" class="text-[11px] text-slate-400 hover:text-amber-400 transition font-semibold">
                                View Full Table →
                            </button>
                        </div>

                        <div class="space-y-1.5 text-xs">
                            <div class="grid grid-cols-12 text-[10px] uppercase font-black tracking-wider text-slate-500 px-2 py-1">
                                <span class="col-span-2">#</span>
                                <span class="col-span-6">Team</span>
                                <span class="col-span-2 text-center">P</span>
                                <span class="col-span-2 text-right text-amber-400">Pts</span>
                            </div>

                            @foreach(array_slice($pointsTable, 0, 5) as $pt)
                                <div class="grid grid-cols-12 items-center px-2 py-1.5 rounded-lg hover:bg-slate-800/40 transition">
                                    <span class="col-span-2 font-mono font-bold text-slate-400">{{ $pt['rank'] }}</span>
                                    <span class="col-span-6 font-bold text-white truncate">{{ $pt['team'] }}</span>
                                    <span class="col-span-2 text-center text-slate-400 font-mono">{{ $pt['played'] }}</span>
                                    <span class="col-span-2 text-right font-black text-amber-400 font-mono">{{ $pt['points'] }}</span>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif

                <!-- Latest Series News Widget -->
                @if(count($seriesNews) > 0)
                    <div class="rounded-2xl p-4 bg-slate-900/90 border border-slate-800 shadow-xl space-y-3">
                        <div class="flex items-center justify-between pb-2 border-b border-slate-800">
                            <h4 class="text-xs font-bold uppercase tracking-wider text-white flex items-center gap-1.5">
                                <span>📰</span>
                                <span>Tournament News</span>
                            </h4>
                            <button wire:click="setTab('news')" class="text-[11px] text-slate-400 hover:text-amber-400 transition font-semibold">
                                All News →
                            </button>
                        </div>

                        <div class="space-y-3">
                            @foreach($seriesNews->take(4) as $art)
                                <a 
                                    href="{{ route('news.show', $art->slug) }}" 
                                    wire:navigate 
                                    class="flex gap-3 group items-center"
                                >
                                    @if($art->featured_image)
                                        <div class="w-14 h-14 rounded-xl overflow-hidden bg-slate-950 shrink-0">
                                            <img src="{{ $art->featured_image_url }}" alt="{{ $art->title }}" class="w-full h-full object-cover group-hover:scale-105 transition" loading="lazy" />
                                        </div>
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <h5 class="text-xs font-bold text-slate-200 group-hover:text-amber-400 transition-colors line-clamp-2 leading-tight">
                                            {{ $art->title }}
                                        </h5>
                                        <span class="text-[10px] text-slate-500 mt-1 block">
                                            {{ $art->published_at ? $art->published_at->diffForHumans() : 'Recent' }}
                                        </span>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </div>
                @endif

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
