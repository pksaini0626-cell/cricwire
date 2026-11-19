<div 
    class="min-h-screen bg-slate-950 text-slate-100 font-sans antialiased relative selection:bg-amber-500 selection:text-black flex flex-col"
    x-data="{
        pinnedIds: [],
        skippedIds: [],
        liveTickerData: {},
        pollInterval: null,
        init() {
            this.syncPreferences();
            window.addEventListener('match-pin-updated', () => this.syncPreferences());
            window.addEventListener('match-skip-updated', () => this.syncPreferences());

            // Lightweight Alpine.js Static JSON Poller hitting /live-data/home.json every 15s (Zero PHP/DB load)
            this.fetchHomeData();
            this.pollInterval = setInterval(() => {
                if (document.visibilityState === 'visible') {
                    this.fetchHomeData();
                }
            }, 15000);
        },
        destroy() {
            if (this.pollInterval) clearInterval(this.pollInterval);
        },
        async fetchHomeData() {
            try {
                const res = await fetch('/live-data/home.json?t=' + Date.now());
                if (!res.ok) return;
                const json = await res.json();
                const data = (json && json.data) ? json.data : (json || {});
                const liveList = data.live || [];
                const recentList = data.recent || data.finished || [];
                
                // Dispatch updates for both live and recently completed matches
                [...liveList, ...recentList].forEach(m => {
                    const mId = String(m.match_id || m.id || '');
                    if (!mId) return;
                    this.liveTickerData[mId] = {
                        ...(this.liveTickerData[mId] || {}),
                        ...m
                    };
                    window.dispatchEvent(new CustomEvent('ticker-score-updated', {
                        detail: { matchId: mId, data: this.liveTickerData[mId] }
                    }));
                });
            } catch (e) {
                // Ignore transient network errors
            }
        },
        syncPreferences() {
            this.pinnedIds = JSON.parse(localStorage.getItem('pinned_matches') || '[]');
            this.skippedIds = JSON.parse(localStorage.getItem('skipped_matches') || '[]');
        },
        resetSkipped() {
            localStorage.removeItem('skipped_matches');
            this.skippedIds = [];
            window.dispatchEvent(new CustomEvent('match-skip-updated'));
        }
    }"
>
    <!-- Top App Bar Component (Mobile compact, Desktop expanded nav) -->
    <x-header />

    @php
        $allCurrentMatches = match($activeTab) {
            'upcoming' => $upcomingMatches,
            'finished' => $finishedMatches,
            default => $liveMatches,
        };

        // Fetch recent news for desktop sidebar
        $recentNews = \App\Models\NewsArticle::where('is_published', true)->latest()->take(3)->get();
        // Fetch active poll for desktop sidebar
        $activePoll = \App\Models\Poll::where('is_active', true)->latest()->first();
    @endphp

    <!-- Responsive Container -->
    <div class="max-w-7xl mx-auto w-full px-3 sm:px-6 lg:px-8 py-4 flex-1 pb-24 md:pb-12">
        <!-- Top Highlights / Pinned Live Matches Carousel -->
        <div class="mb-4 sm:mb-6">
            <x-pinned-banner />
        </div>

        <!-- Responsive Layout Grid -->
        <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-start">

            <!-- Left Sidebar: Desktop Only (Series & Quick Links) -->
            <aside class="hidden lg:block lg:col-span-3 space-y-4 sticky top-20">
                <div class="bg-slate-900/60 border border-slate-800 rounded-2xl p-4 backdrop-blur shadow-sm">
                    <h3 class="text-xs font-bold text-amber-400 uppercase tracking-wider mb-3 flex items-center gap-1.5">
                        <span>🏆</span> Featured Series
                    </h3>
                    <ul class="space-y-2.5 text-xs font-medium text-slate-300">
                        <li>
                            <a href="{{ url('/cricket-schedule/upcoming/international') }}" wire:navigate class="hover:text-amber-400 cursor-pointer transition flex items-center justify-between group">
                                <span>🏏 ICC Men's Champions Trophy</span>
                                <span class="text-[10px] text-slate-500 group-hover:text-amber-400">→</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ url('/cricket-schedule/upcoming/t20-leagues') }}" wire:navigate class="hover:text-amber-400 cursor-pointer transition flex items-center justify-between group">
                                <span>🏆 Indian Premier League (IPL) 2026</span>
                                <span class="text-[10px] text-slate-500 group-hover:text-amber-400">→</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ url('/cricket-schedule/upcoming/international') }}" wire:navigate class="hover:text-amber-400 cursor-pointer transition flex items-center justify-between group">
                                <span>🌍 India tour of New Zealand 2026</span>
                                <span class="text-[10px] text-slate-500 group-hover:text-amber-400">→</span>
                            </a>
                        </li>
                        <li>
                            <a href="{{ url('/cricket-schedule/upcoming/t20-leagues') }}" wire:navigate class="hover:text-amber-400 cursor-pointer transition flex items-center justify-between group">
                                <span>⚡ Big Bash League &amp; SA20</span>
                                <span class="text-[10px] text-slate-500 group-hover:text-amber-400">→</span>
                            </a>
                        </li>
                    </ul>
                </div>

                <!-- Ad / Promo Box (Desktop Left) -->
                <x-ad-banner placement="desktop_left" />
            </aside>

            <!-- Center Main Column: Match Feed -->
            <main class="col-span-1 md:col-span-8 lg:col-span-6 space-y-4">
                <!-- Reactive Filter Tabs -->
                <x-filter-tabs :activeTab="$activeTab" :liveCount="$liveCount" :upcomingCount="$upcomingCount" :finishedCount="$finishedCount" />

                <!-- User Pinned Matches Container (Shown when pinned matches exist) -->
                <template x-if="pinnedIds.length > 0">
                    <div class="space-y-2 mb-3 pb-3 border-b border-amber-500/20">
                        <div class="flex items-center justify-between px-1">
                            <span class="text-[11px] font-black uppercase tracking-wider text-amber-400 flex items-center gap-1.5">
                                <span>📌</span> Pinned Matches
                            </span>
                            <span class="text-[10px] text-slate-500" x-text="pinnedIds.length + ' Pinned'"></span>
                        </div>
                        
                        @foreach($allCurrentMatches as $match)
                            @php
                                $pId = (string) ($match['match_id'] ?? $match['id'] ?? '');
                            @endphp
                            @if($pId)
                                <div x-show="pinnedIds.includes('{{ $pId }}') && !skippedIds.includes('{{ $pId }}')">
                                    <x-match-card :match="$match" :status="$activeTab === 'live' ? null : $activeTab" />
                                </div>
                            @endif
                        @endforeach
                    </div>
                </template>

                <!-- Match Cards Feed -->
                <div class="space-y-3.5">
                    @if($activeTab === 'live')
                        @forelse($liveMatches as $match)
                            @php $mId = (string) ($match['match_id'] ?? $match['id'] ?? ''); @endphp
                            <div x-show="!pinnedIds.includes('{{ $mId }}') && !skippedIds.includes('{{ $mId }}')">
                                <x-match-card :match="$match" />
                            </div>
                        @empty
                            <div class="text-center py-16 bg-slate-900/40 rounded-2xl border border-slate-800/80">
                                <span class="text-4xl block mb-2">🏏</span>
                                <p class="text-slate-400 font-medium text-sm">No Live Matches Right Now</p>
                                <p class="text-slate-600 text-xs mt-1">Check upcoming fixtures in the tab above</p>
                            </div>
                        @endforelse
                    @elseif($activeTab === 'upcoming')
                        @forelse($upcomingMatches as $match)
                            @php $mId = (string) ($match['match_id'] ?? $match['id'] ?? ''); @endphp
                            <div x-show="!pinnedIds.includes('{{ $mId }}') && !skippedIds.includes('{{ $mId }}')">
                                <x-match-card :match="$match" status="upcoming" />
                            </div>
                        @empty
                            <div class="text-center py-16 bg-slate-900/40 rounded-2xl border border-slate-800/80">
                                <p class="text-slate-500 text-sm">No upcoming fixtures found.</p>
                            </div>
                        @endforelse
                    @elseif($activeTab === 'finished')
                        @forelse($finishedMatches as $match)
                            @php $mId = (string) ($match['match_id'] ?? $match['id'] ?? ''); @endphp
                            <div x-show="!pinnedIds.includes('{{ $mId }}') && !skippedIds.includes('{{ $mId }}')">
                                <x-match-card :match="$match" status="finished" />
                            </div>
                        @empty
                            <div class="text-center py-16 bg-slate-900/40 rounded-2xl border border-slate-800/80">
                                <p class="text-slate-500 text-sm">No completed matches available.</p>
                            </div>
                        @endforelse
                    @endif
                </div>

                <!-- Reset Hidden Matches Link -->
                <div x-show="skippedIds.length > 0" class="text-center pt-3 pb-1">
                    <button 
                        type="button" 
                        @click="resetSkipped()" 
                        class="text-[11px] font-semibold text-slate-500 hover:text-amber-400 underline transition"
                    >
                        Restore <span x-text="skippedIds.length"></span> hidden matches
                    </button>
                </div>
            </main>

            <!-- Right Sidebar: Tablet (col-span-4) & Desktop (col-span-3) -->
            <aside class="col-span-1 md:col-span-4 lg:col-span-3 space-y-4 sticky top-20">
                <!-- Mini Polls Widget -->
                <div class="bg-slate-900/60 border border-slate-800 rounded-2xl p-4 backdrop-blur shadow-sm">
                    <div class="flex items-center justify-between mb-2">
                        <h3 class="text-xs font-bold text-amber-400 uppercase tracking-wider flex items-center gap-1.5">
                            <span>🗳️</span> Community Poll
                        </h3>
                        <a href="{{ route('polls') }}" wire:navigate class="text-[10px] text-slate-400 hover:text-amber-400 transition">View All</a>
                    </div>
                    @if($activePoll)
                        @php
                            $totalV = ($activePoll->votes_a ?? 0) + ($activePoll->votes_b ?? 0) + ($activePoll->votes_c ?? 0);
                            $pA = $totalV > 0 ? round((($activePoll->votes_a ?? 0) / $totalV) * 100) : 50;
                            $pB = $totalV > 0 ? round((($activePoll->votes_b ?? 0) / $totalV) * 100) : 50;
                        @endphp
                        <p class="text-xs text-slate-200 font-medium mb-3">{{ $activePoll->question }}</p>
                        <div class="space-y-2">
                            <a href="{{ route('polls') }}" wire:navigate class="w-full text-left text-xs bg-slate-800/70 hover:bg-slate-800 px-3 py-2 rounded-lg border border-slate-700/60 transition flex justify-between items-center group">
                                <span class="truncate">{{ $activePoll->option_a }}</span>
                                <span class="text-amber-400 font-mono font-bold text-[11px] shrink-0 ml-1">{{ $pA }}%</span>
                            </a>
                            <a href="{{ route('polls') }}" wire:navigate class="w-full text-left text-xs bg-slate-800/70 hover:bg-slate-800 px-3 py-2 rounded-lg border border-slate-700/60 transition flex justify-between items-center group">
                                <span class="truncate">{{ $activePoll->option_b }}</span>
                                <span class="text-cyan-400 font-mono font-bold text-[11px] shrink-0 ml-1">{{ $pB }}%</span>
                            </a>
                        </div>
                    @else
                        <p class="text-xs text-slate-300 font-medium mb-3">Who will win today's match?</p>
                        <div class="space-y-2">
                            <button class="w-full text-left text-xs bg-slate-800/70 hover:bg-slate-800 px-3 py-2 rounded-lg border border-slate-700/60 transition flex justify-between items-center">
                                <span>India</span>
                                <span class="text-amber-400 font-mono font-bold text-[11px]">68%</span>
                            </button>
                            <button class="w-full text-left text-xs bg-slate-800/70 hover:bg-slate-800 px-3 py-2 rounded-lg border border-slate-700/60 transition flex justify-between items-center">
                                <span>New Zealand</span>
                                <span class="text-cyan-400 font-mono font-bold text-[11px]">32%</span>
                            </button>
                        </div>
                    @endif
                </div>

                <!-- Mini News Widget -->
                <div class="bg-slate-900/60 border border-slate-800 rounded-2xl p-4 backdrop-blur shadow-sm">
                    <div class="flex justify-between items-center mb-3">
                        <h3 class="text-xs font-bold text-slate-300 uppercase tracking-wider flex items-center gap-1.5">
                            <span>📰</span> Latest News
                        </h3>
                        <a href="{{ route('news.index') }}" wire:navigate class="text-[11px] text-amber-400 hover:underline">View All</a>
                    </div>
                    <div class="space-y-2.5 text-xs text-slate-400">
                        @forelse($recentNews as $article)
                            <a href="{{ route('news.show', ['article' => $article->slug]) }}" wire:navigate class="block hover:text-slate-200 transition group">
                                <p class="line-clamp-2 text-slate-300 group-hover:text-amber-300 transition-colors leading-relaxed">{{ $article->title }}</p>
                                <span class="text-[10px] text-slate-500 mt-0.5 block">{{ $article->published_at ? $article->published_at->diffForHumans() : 'Recent' }}</span>
                            </a>
                        @empty
                            <p class="hover:text-slate-200 cursor-pointer transition line-clamp-2">IPL 2026: Pitch report, team combinations and key battles to watch out for tonight...</p>
                        @endforelse
                    </div>
                </div>

                <!-- Sidebar Ad Slot (Desktop Right) -->
                <x-ad-banner placement="desktop_right" />
            </aside>

        </div>
    </div>

    <!-- Desktop-only Footer (hidden on mobile) -->
    <x-footer />

    <!-- Floating Mobile Bottom Navigation (Hidden on md & lg) -->
    <x-bottom-nav />
</div>