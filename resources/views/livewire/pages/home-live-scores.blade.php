<div 
    class="cricket-page"
    x-data="{
        pinnedIds: [],
        skippedIds: [],
        liveTickerData: {},
        pollInterval: null,
        activeTab: @js($activeTab),
        lastActivityTime: Date.now(),
        isLocal: @js(app()->environment('local')),
        hasAutoPollParam: new URLSearchParams(window.location.search).get('autopoll') === '1',
        isRefreshing: false,
        setTab(tab) {
            this.activeTab = tab;
            $wire.setTab(tab);
        },
        init() {
            this.syncPreferences();
            window.addEventListener('match-pin-updated', () => this.syncPreferences());
            window.addEventListener('match-skip-updated', () => this.syncPreferences());

            // Track user activity (mouse, touch, scroll, keydown) to pause polling after 120s (2 minutes) idle
            const updateActivity = () => { this.lastActivityTime = Date.now(); };
            ['mousemove', 'mousedown', 'keydown', 'touchstart', 'scroll'].forEach(evt => {
                window.addEventListener(evt, updateActivity, { passive: true });
            });

            // If local and not explicitly ?autopoll=1, DO NOT start background polling
            if (this.isLocal && !this.hasAutoPollParam) {
                return;
            }

            // Zero-Refresh Fast Alpine.js Poller hitting /api/live/home every 10s when Live tab is active and !document.hidden
            if (this.activeTab === 'live' && !document.hidden && this.isUserActive()) {
                this.fetchHomeData();
            }
            this.startPoller();

            document.addEventListener('visibilitychange', () => {
                if (!document.hidden && this.activeTab === 'live' && this.isUserActive()) {
                    if (!this.isLocal || this.hasAutoPollParam) {
                        this.fetchHomeData();
                        this.startPoller();
                    }
                }
            });
        },
        destroy() {
            if (this.pollInterval) clearInterval(this.pollInterval);
        },
        isUserActive() {
            // Pause if document hidden or inactive for 2 minutes (120,000ms)
            if (document.hidden) return false;
            return (Date.now() - this.lastActivityTime) < 120000;
        },
        startPoller() {
            if (this.isLocal && !this.hasAutoPollParam) return;
            if (this.pollInterval) clearInterval(this.pollInterval);
            this.pollInterval = setInterval(() => {
                if (!document.hidden && this.activeTab === 'live' && this.isUserActive()) {
                    this.fetchHomeData();
                }
            }, 10000);
        },
        async refreshOnce() {
            this.isRefreshing = true;
            this.lastActivityTime = Date.now();
            await this.fetchHomeData();
            setTimeout(() => { this.isRefreshing = false; }, 600);
        },
        async fetchHomeData() {
            try {
                const res = await fetch(`/api/live/home?_=${Date.now()}`, {
                    headers: { 'Accept': 'application/json' },
                    cache: 'no-store'
                });
                if (!res.ok) return;

                const json = await res.json();
                if (!json) return;
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

    <style>
        /* =========================================================
           CRICKET DASHBOARD — FRESH DARK SPORTS CENTER
           Same theme palette & font family, reimagined layout & structure.
        ========================================================== */
        .cricket-page {
            --cp-bg: #080D17;
            --cp-panel: #111B2B;
            --cp-surface: #0D1422;
            --cp-panel-2: #172337;
            --cp-line: #253248;
            --cp-muted: #64748B;
            --cp-secondary: #94A3B8;
            --cp-text: #F8FAFC;
            --cp-accent: #F59E0B;
            --cp-live: #EF233C;
            --cp-success: #22C55E;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            color: var(--cp-text);
            background-color: var(--cp-bg);
            font-family: Inter, ui-sans-serif, system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
            -webkit-font-smoothing: antialiased;
        }

        .cricket-page *, .cricket-page *::before, .cricket-page *::after { box-sizing: border-box; }

        /* Top Match Center Ticker Bar */
        .cricket-ticker-strip {
            background-color: var(--cp-surface);
            border-bottom: 1px solid var(--cp-line);
        }

        .cricket-shell {
            width: min(1480px, 100%);
            margin: 0 auto;
            padding: 20px 24px 80px;
            flex: 1;
        }

        /* 2-Column Asymmetric Layout: Primary Stage (8-cols) + Live Sports Pulse Sidebar (4-cols) */
        .cricket-grid {
            display: grid;
            grid-template-columns: minmax(0, 1fr) 340px;
            gap: 24px;
            align-items: start;
        }

        .cricket-stage {
            display: flex;
            flex-direction: column;
            gap: 18px;
            min-width: 0;
        }

        .cricket-rail {
            display: flex;
            flex-direction: column;
            gap: 20px;
            min-width: 0;
            position: sticky;
            top: 76px;
        }

        /* Hero Highlights Strip */
        .cricket-hero-bar {
            background: linear-gradient(180deg, rgba(23, 35, 55, 0.6) 0%, rgba(13, 20, 34, 0.4) 100%);
            border: 1px solid var(--cp-line);
            border-radius: 14px;
            padding: 16px 20px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 16px;
        }

        /* Widget Container */
        .cricket-card {
            background-color: var(--cp-panel);
            border: 1px solid var(--cp-line);
            border-radius: 14px;
            overflow: hidden;
            box-shadow: 0 4px 18px rgba(0, 0, 0, 0.28);
            transition: border-color .15s ease;
        }

        .cricket-card:hover {
            border-color: rgba(245, 158, 11, 0.35);
        }

        .cricket-card-header {
            padding: 14px 16px;
            border-bottom: 1px solid rgba(37, 50, 72, 0.7);
            background-color: rgba(13, 20, 34, 0.6);
            display: flex;
            align-items: center;
            justify-content: space-between;
        }

        .cricket-card-body {
            padding: 16px;
        }

        /* Pinned Matches Section */
        .cricket-pinned-wrap {
            background: rgba(245, 158, 11, 0.035);
            border: 1px solid rgba(245, 158, 11, 0.3);
            border-radius: 14px;
            padding: 14px 16px;
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        /* Match Cards Grid Feed */
        .cricket-matches-stack {
            display: flex;
            flex-direction: column;
            gap: 12px;
        }

        /* Responsive Breakpoints */
        @media (max-width: 1120px) {
            .cricket-grid {
                grid-template-columns: minmax(0, 1fr) 300px;
                gap: 18px;
            }
        }

        @media (max-width: 960px) {
            .cricket-shell { padding: 16px 16px 90px; }
            .cricket-grid {
                display: flex;
                flex-direction: column;
                gap: 20px;
            }
            .cricket-stage, .cricket-rail {
                width: 100%;
            }
            .cricket-rail {
                position: static;
            }
            .cricket-hero-bar {
                flex-direction: column;
                align-items: flex-start;
                gap: 12px;
            }
        }

        @media (max-width: 640px) {
            .cricket-shell { padding: 12px 10px 92px; }
            .cricket-card-body { padding: 12px; }
        }
    </style>

    <!-- Top Global App Bar -->
    <x-header />

    @php
        $allCurrentMatches = match($activeTab) {
            'upcoming' => $upcomingMatches,
            'finished' => $finishedMatches,
            default => $liveMatches,
        };

        // Fetch recent news for rail
        $recentNews = \App\Models\NewsArticle::where('is_published', true)->latest()->take(4)->get();
        // Fetch active poll for rail
        $activePoll = \App\Models\Poll::where('is_active', true)->latest()->first();
    @endphp

    <!-- Sub-Header Quick Action Strip (Series Pulse + Live Refresh) -->
    <section class="cricket-ticker-strip">
        <div class="max-w-[1480px] mx-auto px-4 sm:px-6 py-2 flex items-center justify-between gap-4 text-xs">
            <!-- Left: Featured Quick Series Badges -->
            <div class="flex items-center gap-2 overflow-x-auto no-scrollbar py-0.5 min-w-0">
                <span class="text-[10px] uppercase font-bold text-slate-400 tracking-wider shrink-0 flex items-center gap-1">
                    <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span> Top Hubs:
                </span>
                <a href="{{ url('/cricket-schedule/upcoming/international') }}" wire:navigate class="px-2.5 py-1 rounded-full bg-[#111B2B] hover:bg-[#172337] border border-[#253248] text-[#94A3B8] hover:text-amber-400 transition whitespace-nowrap text-[11px] font-medium shrink-0 flex items-center gap-1.5">
                    <span>🏆</span> Champions Trophy
                </a>
                <a href="{{ url('/cricket-schedule/upcoming/t20-leagues') }}" wire:navigate class="px-2.5 py-1 rounded-full bg-[#111B2B] hover:bg-[#172337] border border-[#253248] text-[#94A3B8] hover:text-amber-400 transition whitespace-nowrap text-[11px] font-medium shrink-0 flex items-center gap-1.5">
                    <span>⚡</span> IPL 2026
                </a>
                <a href="{{ route('series.index') }}" wire:navigate class="px-2.5 py-1 rounded-full bg-[#111B2B] hover:bg-[#172337] border border-[#253248] text-[#94A3B8] hover:text-amber-400 transition whitespace-nowrap text-[11px] font-medium shrink-0 flex items-center gap-1.5">
                    <span>🌍</span> All Series →
                </a>
            </div>

            <!-- Right: Live Match Activity Counter & Refresh Action -->
            <div class="flex items-center gap-2.5 shrink-0">
                <div class="hidden sm:flex items-center gap-1.5 text-[11px] text-[#94A3B8]">
                    <span class="w-2 h-2 rounded-full {{ $liveCount > 0 ? 'bg-[#EF233C] animate-pulse' : 'bg-[#64748B]' }}"></span>
                    <span class="font-medium">
                        <strong class="text-[#F8FAFC]">{{ $liveCount }}</strong> Live, 
                        <strong class="text-[#F8FAFC]">{{ $upcomingCount }}</strong> Upcoming
                    </span>
                </div>
                <button 
                    type="button" 
                    @click="refreshOnce()" 
                    :disabled="isRefreshing" 
                    class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-lg bg-[#111B2B] hover:bg-[#172337] border border-[#253248] hover:border-amber-500/40 text-xs font-semibold text-[#F8FAFC] transition active:scale-95 disabled:opacity-50"
                    title="Refresh Live Scores"
                >
                    <svg class="w-3.5 h-3.5 text-amber-400" :class="{ 'animate-spin': isRefreshing }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                    </svg>
                    <span class="hidden md:inline" x-text="isRefreshing ? 'Updating...' : 'Sync'">Sync</span>
                </button>
            </div>
        </div>
    </section>

    <!-- Main Application Shell -->
    <div class="cricket-shell">

        <!-- Top Optional Highlights / Pinned Banner (only takes space when banner exists) -->
        <x-pinned-banner />

        <!-- 2-Column Responsive Layout Grid -->
        <div class="cricket-grid">

            <!-- MAIN COLUMN: Match Stage (Left, 8-cols on desktop) -->
            <main class="cricket-stage">

                <!-- Local Mode Banner (Intact logic) -->
                <div 
                    x-show="isLocal && !hasAutoPollParam" 
                    x-cloak 
                    class="cricket-hero-bar"
                >
                    <div class="flex items-center gap-2.5">
                        <span class="inline-flex items-center px-2 py-0.5 rounded text-[10px] font-bold bg-amber-500/15 border border-amber-500/30 text-amber-300">
                            Developer Mode
                        </span>
                        <span class="text-xs text-[#94A3B8]">Auto-polling paused locally to preserve API quota. Click Sync to fetch live data.</span>
                    </div>
                    <button 
                        type="button" 
                        @click="refreshOnce()" 
                        :disabled="isRefreshing" 
                        class="inline-flex items-center gap-1.5 px-3 py-1 rounded-lg bg-amber-500 hover:bg-amber-400 text-slate-950 font-bold text-xs shadow-sm transition active:scale-95 disabled:opacity-50 shrink-0"
                    >
                        <span x-text="isRefreshing ? 'Refreshing...' : 'Refresh Once'">Refresh Once</span>
                    </button>
                </div>

                <!-- Match Feed Hub Header + Segmented Filter Tabs -->
                <div id="matches-hub" class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 bg-[#111B2B] p-2.5 sm:p-3 rounded-xl border border-[#253248]">
                    <div class="flex items-center gap-2 px-1">
                        <span class="text-xs font-black uppercase tracking-wider text-[#F8FAFC] flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-amber-400"></span> Matches Hub
                        </span>
                        <span class="text-[10px] text-[#64748B] font-medium hidden sm:inline">• Real-time Ball-by-Ball Feed</span>
                    </div>

                    <!-- Filter Tabs Segmented Control Component -->
                    <div class="w-full sm:w-auto">
                        <x-filter-tabs :activeTab="$activeTab" :liveCount="$liveCount" :upcomingCount="$upcomingCount" :finishedCount="$finishedCount" />
                    </div>
                </div>

                <!-- User Pinned Matches Container -->
                <div x-show="pinnedIds.length > 0" x-cloak class="cricket-pinned-wrap">
                    <div class="flex items-center justify-between px-1">
                        <span class="text-xs font-bold uppercase tracking-wider text-amber-400 flex items-center gap-1.5">
                            <span>📌</span> Pinned Matches
                        </span>
                        <span class="text-[10px] text-[#94A3B8] font-semibold" x-text="pinnedIds.length + ' Pinned'"></span>
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

                <!-- Primary Match Cards Stack Feed -->
                <div class="cricket-matches-stack">
                    @if($activeTab === 'live')
                        @forelse($liveMatches as $match)
                            @php $mId = (string) ($match['match_id'] ?? $match['id'] ?? ''); @endphp
                            <div x-show="!pinnedIds.includes('{{ $mId }}') && !skippedIds.includes('{{ $mId }}')">
                                <x-match-card :match="$match" />
                            </div>
                        @empty
                            <div class="flex flex-col items-center justify-center min-h-[220px] p-8 text-center rounded-xl border border-dashed border-[#253248] bg-[#111B2B]/40">
                                <span class="text-3xl block mb-2 opacity-70">🏏</span>
                                <p class="text-[#F8FAFC] font-semibold text-sm">No Live Matches In Play</p>
                                <p class="text-[#64748B] text-xs mt-1">Explore upcoming fixtures or browse recent results in the tabs above.</p>
                            </div>
                        @endforelse
                    @elseif($activeTab === 'upcoming')
                        @forelse($paginatedUpcomingMatches as $match)
                            @php $mId = (string) ($match['match_id'] ?? $match['id'] ?? ''); @endphp
                            <div x-show="!pinnedIds.includes('{{ $mId }}') && !skippedIds.includes('{{ $mId }}')">
                                <x-match-card :match="$match" status="upcoming" />
                            </div>
                        @empty
                            <div class="flex flex-col items-center justify-center min-h-[200px] p-8 text-center rounded-xl border border-dashed border-[#253248] bg-[#111B2B]/40">
                                <p class="text-[#94A3B8] text-sm">No upcoming fixtures scheduled right now.</p>
                            </div>
                        @endforelse

                        {{-- Pagination Bar for Upcoming --}}
                        @if($upcomingTotalPages > 1)
                            <div class="flex items-center justify-between gap-3 px-3 py-2.5 mt-2 bg-[#111B2B] rounded-xl border border-[#253248] text-xs">
                                <button
                                    type="button"
                                    wire:click="prevUpcomingPage"
                                    @click="document.getElementById('matches-hub')?.scrollIntoView({ behavior: 'smooth' })"
                                    @disabled($upcomingPage <= 1)
                                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg font-semibold transition active:scale-95 disabled:opacity-35 disabled:cursor-not-allowed {{ $upcomingPage > 1 ? 'bg-[#172337] hover:bg-amber-500 hover:text-slate-950 text-[#F8FAFC]' : 'bg-[#172337]/50 text-[#64748B]' }}"
                                >
                                    <span>←</span> Previous
                                </button>

                                <span class="text-[#94A3B8] font-medium">
                                    Page <strong class="text-[#F8FAFC] font-bold">{{ $upcomingPage }}</strong> of <strong class="text-[#F8FAFC] font-bold">{{ $upcomingTotalPages }}</strong>
                                    <span class="text-[#64748B] hidden sm:inline">({{ $upcomingCount }} matches)</span>
                                </span>

                                <button
                                    type="button"
                                    wire:click="nextUpcomingPage"
                                    @click="document.getElementById('matches-hub')?.scrollIntoView({ behavior: 'smooth' })"
                                    @disabled($upcomingPage >= $upcomingTotalPages)
                                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg font-semibold transition active:scale-95 disabled:opacity-35 disabled:cursor-not-allowed {{ $upcomingPage < $upcomingTotalPages ? 'bg-[#172337] hover:bg-amber-500 hover:text-slate-950 text-[#F8FAFC]' : 'bg-[#172337]/50 text-[#64748B]' }}"
                                >
                                    Next <span>→</span>
                                </button>
                            </div>
                        @endif

                    @elseif($activeTab === 'finished')
                        @forelse($paginatedFinishedMatches as $match)
                            @php $mId = (string) ($match['match_id'] ?? $match['id'] ?? ''); @endphp
                            <div x-show="!pinnedIds.includes('{{ $mId }}') && !skippedIds.includes('{{ $mId }}')">
                                <x-match-card :match="$match" status="finished" />
                            </div>
                        @empty
                            <div class="flex flex-col items-center justify-center min-h-[200px] p-8 text-center rounded-xl border border-dashed border-[#253248] bg-[#111B2B]/40">
                                <p class="text-[#94A3B8] text-sm">No completed matches available in archive.</p>
                            </div>
                        @endforelse

                        {{-- Pagination Bar for Finished --}}
                        @if($finishedTotalPages > 1)
                            <div class="flex items-center justify-between gap-3 px-3 py-2.5 mt-2 bg-[#111B2B] rounded-xl border border-[#253248] text-xs">
                                <button
                                    type="button"
                                    wire:click="prevFinishedPage"
                                    @click="document.getElementById('matches-hub')?.scrollIntoView({ behavior: 'smooth' })"
                                    @disabled($finishedPage <= 1)
                                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg font-semibold transition active:scale-95 disabled:opacity-35 disabled:cursor-not-allowed {{ $finishedPage > 1 ? 'bg-[#172337] hover:bg-amber-500 hover:text-slate-950 text-[#F8FAFC]' : 'bg-[#172337]/50 text-[#64748B]' }}"
                                >
                                    <span>←</span> Previous
                                </button>

                                <span class="text-[#94A3B8] font-medium">
                                    Page <strong class="text-[#F8FAFC] font-bold">{{ $finishedPage }}</strong> of <strong class="text-[#F8FAFC] font-bold">{{ $finishedTotalPages }}</strong>
                                    <span class="text-[#64748B] hidden sm:inline">({{ $finishedCount }} matches)</span>
                                </span>

                                <button
                                    type="button"
                                    wire:click="nextFinishedPage"
                                    @click="document.getElementById('matches-hub')?.scrollIntoView({ behavior: 'smooth' })"
                                    @disabled($finishedPage >= $finishedTotalPages)
                                    class="inline-flex items-center gap-1 px-3 py-1.5 rounded-lg font-semibold transition active:scale-95 disabled:opacity-35 disabled:cursor-not-allowed {{ $finishedPage < $finishedTotalPages ? 'bg-[#172337] hover:bg-amber-500 hover:text-slate-950 text-[#F8FAFC]' : 'bg-[#172337]/50 text-[#64748B]' }}"
                                >
                                    Next <span>→</span>
                                </button>
                            </div>
                        @endif
                    @endif
                </div>

                <!-- Restore Hidden Matches Action -->
                <div x-show="skippedIds.length > 0" x-cloak class="text-center pt-2">
                    <button 
                        type="button" 
                        @click="resetSkipped()" 
                        class="text-xs font-medium text-[#94A3B8] hover:text-amber-400 underline transition"
                    >
                        Restore <span x-text="skippedIds.length"></span> hidden matches
                    </button>
                </div>

                <!-- Latest Cricket News Section (Main Feed: Mobile & All Screens) -->
                @if(($featuredNews ?? collect())->isNotEmpty() || ($recentNews ?? collect())->isNotEmpty())
                    <section class="cricket-card mt-2">
                        <div class="cricket-card-header">
                            <h2 class="text-xs font-bold uppercase tracking-wider text-amber-400 flex items-center gap-2">
                                <span>📰</span> Latest Cricket News
                            </h2>
                            <a href="{{ route('news.index') }}" wire:navigate class="text-[11px] font-semibold text-[#94A3B8] hover:text-amber-400 transition">
                                View All →
                            </a>
                        </div>
                        <div class="cricket-card-body space-y-3">
                            @php
                                $feedNews = ($featuredNews ?? collect())->merge($recentNews ?? collect())->unique('id')->take(6);
                            @endphp
                            @foreach($feedNews as $article)
                                @php
                                    $rawThumb = $article->resolved_image_url
                                        ?? $article->featured_image
                                        ?? $article->image
                                        ?? $article->image_url
                                        ?? $article->thumbnail
                                        ?? $article->banner
                                        ?? null;

                                    $thumb = null;
                                    if (!empty($rawThumb) && is_string($rawThumb)) {
                                        $rawThumb = trim($rawThumb);
                                        if (str_starts_with($rawThumb, 'http://') || str_starts_with($rawThumb, 'https://')) {
                                            $thumb = $rawThumb;
                                        } else {
                                            $cleanPath = ltrim($rawThumb, '/');
                                            $cleanPath = preg_replace('#^(public/|storage/)+#', '', $cleanPath);
                                            $thumb = asset('storage/' . ltrim($cleanPath, '/'));
                                        }
                                    }

                                    $category = $article->category ?? 'news';
                                    $categoryLabel = match($category) {
                                        'pitch_report' => 'Pitch Report',
                                        'prediction' => 'Prediction',
                                        'fantasy_tips' => 'Fantasy Tips',
                                        default => 'News',
                                    };
                                    $categoryColor = match($category) {
                                        'pitch_report' => 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30',
                                        'prediction' => 'bg-amber-500/15 text-amber-400 border-amber-500/30',
                                        'fantasy_tips' => 'bg-cyan-500/15 text-cyan-400 border-cyan-500/30',
                                        default => 'bg-slate-700/60 text-slate-300 border-slate-600/50',
                                    };
                                @endphp
                                <a href="{{ route('news.show', ['article' => $article->slug]) }}" wire:navigate class="flex items-start gap-3 p-2.5 rounded-xl bg-[#0D1422]/70 hover:bg-[#0D1422] border border-[#253248]/60 hover:border-amber-500/40 transition group">
                                    @if(!empty($thumb))
                                        <img src="{{ $thumb }}" alt="{{ $article->title }}" loading="lazy" onerror="this.style.display='none'" class="w-20 h-14 sm:w-24 sm:h-16 rounded-lg object-cover block shrink-0 border border-[#253248]" />
                                    @else
                                        <div class="w-20 h-14 sm:w-24 sm:h-16 rounded-lg bg-[#172337] border border-[#253248] flex items-center justify-center shrink-0 text-amber-400/70 text-lg">
                                            🏏
                                        </div>
                                    @endif
                                    <div class="min-w-0 flex-1">
                                        <div class="flex items-center gap-2 mb-1">
                                            <span class="inline-flex items-center px-2 py-0.5 rounded-md text-[10px] font-semibold border {{ $categoryColor }}">
                                                {{ $categoryLabel }}
                                            </span>
                                            <span class="text-[10px] text-[#64748B]">
                                                {{ $article->published_at ? $article->published_at->diffForHumans() : ($article->created_at ? $article->created_at->diffForHumans() : 'Recent') }}
                                            </span>
                                        </div>
                                        <h3 class="line-clamp-2 text-xs sm:text-sm font-semibold text-[#F8FAFC] group-hover:text-amber-400 transition-colors leading-snug">
                                            {{ $article->title }}
                                        </h3>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif
            </main>

            <!-- SIDEBAR RAIL: Sports Pulse & Community Widgets (Right, 4-cols on desktop) -->
            <aside class="cricket-rail">

                <!-- 1. Featured Tournaments & Hubs Card -->
                <section class="cricket-card">
                    <div class="cricket-card-header">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-amber-400 flex items-center gap-2">
                            <span>🏆</span> Featured Series Hub
                        </h2>
                        <a href="{{ route('series.index') }}" wire:navigate class="text-[11px] text-[#94A3B8] hover:text-amber-400 transition">View Hub →</a>
                    </div>
                    <div class="cricket-card-body">
                        <ul class="space-y-2.5 text-xs font-medium">
                            <li>
                                <a href="{{ url('/cricket-schedule/upcoming/international') }}" wire:navigate class="hover:text-amber-400 transition flex items-center justify-between p-2 rounded-lg bg-[#0D1422] hover:bg-[#172337] border border-[#253248]/70 group">
                                    <span class="truncate pr-2 text-[#F8FAFC] group-hover:text-amber-400">🏏 ICC Champions Trophy 2026</span>
                                    <span class="text-[10px] text-[#64748B] group-hover:text-amber-400 shrink-0">→</span>
                                </a>
                            </li>
                            <li>
                                <a href="{{ url('/cricket-schedule/upcoming/t20-leagues') }}" wire:navigate class="hover:text-amber-400 transition flex items-center justify-between p-2 rounded-lg bg-[#0D1422] hover:bg-[#172337] border border-[#253248]/70 group">
                                    <span class="truncate pr-2 text-[#F8FAFC] group-hover:text-amber-400">⚡ Indian Premier League (IPL) 2026</span>
                                    <span class="text-[10px] text-[#64748B] group-hover:text-amber-400 shrink-0">→</span>
                                </a>
                            </li>
                            <li>
                                <a href="{{ url('/cricket-schedule/upcoming/international') }}" wire:navigate class="hover:text-amber-400 transition flex items-center justify-between p-2 rounded-lg bg-[#0D1422] hover:bg-[#172337] border border-[#253248]/70 group">
                                    <span class="truncate pr-2 text-[#F8FAFC] group-hover:text-amber-400">🌍 India Tour of New Zealand 2026</span>
                                    <span class="text-[10px] text-[#64748B] group-hover:text-amber-400 shrink-0">→</span>
                                </a>
                            </li>
                            <li>
                                <a href="{{ url('/cricket-schedule/upcoming/t20-leagues') }}" wire:navigate class="hover:text-amber-400 transition flex items-center justify-between p-2 rounded-lg bg-[#0D1422] hover:bg-[#172337] border border-[#253248]/70 group">
                                    <span class="truncate pr-2 text-[#F8FAFC] group-hover:text-amber-400">🔥 Big Bash League &amp; SA20</span>
                                    <span class="text-[10px] text-[#64748B] group-hover:text-amber-400 shrink-0">→</span>
                                </a>
                            </li>
                        </ul>
                    </div>
                </section>

                <!-- Ad Banner Slot (Desktop Left/Rail) -->
                <x-ad-banner placement="desktop_left" />

                <!-- 2. Community Fan Poll Widget -->
                <section class="cricket-card">
                    <div class="cricket-card-header">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-amber-400 flex items-center gap-2">
                            <span>🗳️</span> Fan Pulse Poll
                        </h2>
                        <a href="{{ route('polls') }}" wire:navigate class="text-[11px] text-[#94A3B8] hover:text-amber-400 transition">All Polls →</a>
                    </div>
                    <div class="cricket-card-body">
                        @if($activePoll)
                            @php
                                $totalV = ($activePoll->votes_a ?? 0) + ($activePoll->votes_b ?? 0) + ($activePoll->votes_c ?? 0);
                                $pA = $totalV > 0 ? round((($activePoll->votes_a ?? 0) / $totalV) * 100) : 50;
                                $pB = $totalV > 0 ? round((($activePoll->votes_b ?? 0) / $totalV) * 100) : 50;
                            @endphp
                            <p class="text-xs text-[#F8FAFC] font-medium mb-3 leading-relaxed">{{ $activePoll->question }}</p>
                            <div class="space-y-2">
                                <a href="{{ route('polls') }}" wire:navigate class="w-full text-left text-xs bg-[#0D1422] hover:bg-[#172337] px-3 py-2.5 rounded-lg border border-[#253248] transition flex justify-between items-center group">
                                    <span class="truncate text-[#F8FAFC]">{{ $activePoll->option_a }}</span>
                                    <span class="text-amber-400 font-mono font-bold text-xs shrink-0 ml-2">{{ $pA }}%</span>
                                </a>
                                <a href="{{ route('polls') }}" wire:navigate class="w-full text-left text-xs bg-[#0D1422] hover:bg-[#172337] px-3 py-2.5 rounded-lg border border-[#253248] transition flex justify-between items-center group">
                                    <span class="truncate text-[#F8FAFC]">{{ $activePoll->option_b }}</span>
                                    <span class="text-cyan-400 font-mono font-bold text-xs shrink-0 ml-2">{{ $pB }}%</span>
                                </a>
                            </div>
                        @else
                            <p class="text-xs text-[#F8FAFC] font-medium mb-3 leading-relaxed">Who will take the trophy this season?</p>
                            <div class="space-y-2">
                                <a href="{{ route('polls') }}" wire:navigate class="w-full text-left text-xs bg-[#0D1422] hover:bg-[#172337] px-3 py-2.5 rounded-lg border border-[#253248] transition flex justify-between items-center">
                                    <span class="text-[#F8FAFC]">India</span>
                                    <span class="text-amber-400 font-mono font-bold text-xs">68%</span>
                                </a>
                                <a href="{{ route('polls') }}" wire:navigate class="w-full text-left text-xs bg-[#0D1422] hover:bg-[#172337] px-3 py-2.5 rounded-lg border border-[#253248] transition flex justify-between items-center">
                                    <span class="text-[#F8FAFC]">Australia</span>
                                    <span class="text-cyan-400 font-mono font-bold text-xs">32%</span>
                                </a>
                            </div>
                        @endif
                    </div>
                </section>

                <!-- 3. Editorial Cricket News Widget -->
                <section class="cricket-card">
                    <div class="cricket-card-header">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-amber-400 flex items-center gap-2">
                            <span>📰</span> Latest News &amp; Insights
                        </h2>
                        <a href="{{ route('news.index') }}" wire:navigate class="text-[11px] text-[#94A3B8] hover:text-amber-400 transition">All News →</a>
                    </div>
                    <div class="cricket-card-body">
                        <div class="space-y-3">
                            @forelse($recentNews as $article)
                                <a href="{{ route('news.show', ['article' => $article->slug]) }}" wire:navigate class="block p-2 rounded-lg bg-[#0D1422]/60 hover:bg-[#0D1422] border border-[#253248]/50 hover:border-amber-500/30 transition group">
                                    <p class="line-clamp-2 text-xs font-medium text-[#F8FAFC] group-hover:text-amber-400 transition-colors leading-relaxed">
                                        {{ $article->title }}
                                    </p>
                                    <span class="text-[10px] text-[#64748B] mt-1 block">
                                        {{ $article->published_at ? $article->published_at->diffForHumans() : 'Recent' }}
                                    </span>
                                </a>
                            @empty
                                <div class="text-xs text-[#94A3B8]">
                                    Stay tuned for upcoming editorial reports and match previews.
                                </div>
                            @endforelse
                        </div>
                    </div>
                </section>

                <!-- Ad Banner Slot (Desktop Right) -->
                <x-ad-banner placement="desktop_right" />

            </aside>

        </div>
    </div>

    <!-- Desktop Global Footer -->
    <x-footer />

    <!-- Mobile Sticky Navigation Bar -->
    <x-bottom-nav />
</div>
