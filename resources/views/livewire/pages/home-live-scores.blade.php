@php
    $isMatchCompleted = static function ($m): bool {
        if (!is_array($m)) {
            return true;
        }
        if (!empty($m['is_completed']) || !empty($m['isCompleted'])) {
            return true;
        }
        $st = strtolower(trim((string) ($m['state'] ?? $m['status'] ?? $m['match_status'] ?? '')));
        if (in_array($st, ['finished', 'completed', 'complete', 'abandoned', '3'], true)) {
            return true;
        }
        if (!empty($m['result']) && trim((string) $m['result']) !== '') {
            return true;
        }
        return \App\Services\Cricket\CricketLiveApiService::isCompletedMatch($m);
    };

    $activeLiveMatches = array_values(array_filter($liveMatches ?? [], fn ($m) => !$isMatchCompleted($m)));
    $activeUpcomingMatches = array_values(array_filter($upcomingMatches ?? [], fn ($m) => !$isMatchCompleted($m)));

    $activeLiveSeed = array_values(array_filter(array_map(static function ($m) {
        $id = (string) ($m['match_id'] ?? $m['id'] ?? '');
        return $id !== '' ? ['id' => $id, 'match_id' => $id, 'isCompleted' => false, 'status' => 'live'] : null;
    }, $activeLiveMatches)));

    $activeUpcomingSeed = array_values(array_filter(array_map(static function ($m) {
        $id = (string) ($m['match_id'] ?? $m['id'] ?? '');
        return $id !== '' ? ['id' => $id, 'match_id' => $id, 'isCompleted' => false, 'status' => 'upcoming'] : null;
    }, $activeUpcomingMatches)));

    $activePinnableMatches = [];
    $seenPinIds = [];
    foreach ($activeLiveMatches as $m) {
        $id = (string) ($m['match_id'] ?? $m['id'] ?? '');
        if ($id !== '' && !isset($seenPinIds[$id])) {
            $seenPinIds[$id] = true;
            $m['_bucket_status'] = 'live';
            $activePinnableMatches[] = $m;
        }
    }
    foreach ($activeUpcomingMatches as $m) {
        $id = (string) ($m['match_id'] ?? $m['id'] ?? '');
        if ($id !== '' && !isset($seenPinIds[$id])) {
            $seenPinIds[$id] = true;
            $m['_bucket_status'] = 'upcoming';
            $activePinnableMatches[] = $m;
        }
    }
@endphp

<div 
    class="cricket-page"
    x-data="{
        live: @js($activeLiveSeed),
        upcoming: @js($activeUpcomingSeed),
        pinnedMatches: [],
        skippedIds: [],
        liveTickerData: {},
        pollInterval: null,
        activeTab: $wire.entangle('activeTab'),
        lastActivityTime: Date.now(),
        isLocal: @js(app()->environment('local')),
        hasAutoPollParam: new URLSearchParams(window.location.search).get('autopoll') === '1',
        isRefreshing: false,
        get pinnedIds() {
            return this.pinnedMatches;
        },
        get activeMatchIdSet() {
            const ids = new Set();
            const isActive = (m) => {
                if (!m) return false;
                if (m.isCompleted === true || m.is_completed === true) return false;
                const st = String(m.state || m.status || m.match_status || '').trim().toLowerCase();
                if (['finished', 'completed', 'complete', 'abandoned', '3'].includes(st)) return false;
                if (m.result && String(m.result).trim() !== '') return false;
                return true;
            };
            (this.live || []).forEach(m => {
                const id = String(m?.match_id ?? m?.id ?? '');
                if (id && isActive(m)) ids.add(id);
            });
            (this.upcoming || []).forEach(m => {
                const id = String(m?.match_id ?? m?.id ?? '');
                if (id && isActive(m)) ids.add(id);
            });
            return ids;
        },
        get activePinnedMatches() {
            const activeIds = this.activeMatchIdSet;
            const pinnedSet = new Set((this.pinnedMatches || []).map(id => String(id)));
            const skippedSet = new Set((this.skippedIds || []).map(id => String(id)));
            const seen = new Set();
            const matched = [];
            [...(this.live || []), ...(this.upcoming || [])].forEach(m => {
                const id = String(m?.match_id ?? m?.id ?? '');
                if (!id || seen.has(id) || !activeIds.has(id) || !pinnedSet.has(id) || skippedSet.has(id)) return;
                seen.add(id);
                matched.push(m);
            });
            return matched;
        },
        setTab(tab) {
            this.activeTab = tab;
            $wire.setTab(tab);
        },
        init() {
            this.syncPreferences();
            window.addEventListener('match-pin-updated', () => this.syncPreferences());
            window.addEventListener('pinned-matches-updated', () => this.syncPreferences());
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
            if (this.activeTab !== 'live') return;
            try {
                const res = await fetch(`/api/matches/live?_=${Date.now()}`, {
                    headers: { 'Accept': 'application/json' },
                    cache: 'no-store'
                });
                if (!res.ok) return;

                const json = await res.json();
                if (!json) return;
                const data = (json && json.data) ? json.data : (json || {});
                const liveList = Array.isArray(data.live) ? data.live : [];

                // Update live (and upcoming if provided) and prune finished/absent pinned matches
                this.live = liveList
                    .filter(m => {
                        if (!m || m.isCompleted === true || m.is_completed === true) return false;
                        const st = String(m.state || m.status || m.match_status || '').trim().toLowerCase();
                        return !['finished', 'completed', 'complete', 'abandoned', '3'].includes(st) && !(m.result && String(m.result).trim() !== '');
                    })
                    .map(m => {
                        const mId = String(m.match_id || m.id || '');
                        return { id: mId, match_id: mId, isCompleted: false, status: 'live', ...m };
                    });
                if (Array.isArray(data.upcoming)) {
                    this.upcoming = data.upcoming
                        .filter(m => !m?.isCompleted && !m?.is_completed && !(m?.result && String(m.result).trim() !== ''))
                        .map(m => {
                            const mId = String(m.match_id || m.id || '');
                            return { id: mId, match_id: mId, isCompleted: false, status: 'upcoming', ...m };
                        });
                }
                this.prunePinnedMatches();

                // Dispatch updates ONLY for active live matches (never overwrite hydrated DB finished scores)
                liveList.forEach(m => {
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
        prunePinnedMatches() {
            let rawPinned = [];
            try {
                const parsed = JSON.parse(localStorage.getItem('pinned_matches') || '[]');
                rawPinned = Array.isArray(parsed) ? parsed.map(id => String(id)).filter(Boolean) : [];
            } catch (e) {
                rawPinned = [];
            }
            const activeIds = this.activeMatchIdSet;
            const pruned = [];
            const seen = new Set();
            rawPinned.forEach(id => {
                if (!seen.has(id) && activeIds.has(id)) {
                    seen.add(id);
                    pruned.push(id);
                }
            });
            this.pinnedMatches = pruned;
            const prunedJson = JSON.stringify(pruned);
            if (JSON.stringify(rawPinned) !== prunedJson) {
                localStorage.setItem('pinned_matches', prunedJson);
            }
        },
        syncPreferences() {
            this.prunePinnedMatches();
            try {
                const skipped = JSON.parse(localStorage.getItem('skipped_matches') || '[]');
                this.skippedIds = Array.isArray(skipped) ? skipped.map(id => String(id)).filter(Boolean) : [];
            } catch (e) {
                this.skippedIds = [];
            }
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

    <!-- Homepage Top Sliding Battle Challenge Alert Bar (Logged-in Captains) -->
    @if(($captainAlertBattles ?? collect())->isNotEmpty())
        <div class="bg-gradient-to-r from-amber-500/20 via-emerald-500/15 to-amber-500/20 border-b border-amber-400/40 overflow-hidden">
            <div class="max-w-[1480px] mx-auto px-3 sm:px-6 py-2 flex items-center gap-3 overflow-x-auto no-scrollbar">
                <span class="px-2 py-0.5 rounded-md bg-amber-400 text-slate-950 text-[10px] font-black uppercase tracking-wider shrink-0 animate-pulse">
                    LIVE ARENA
                </span>
                <div class="flex items-center gap-4 min-w-0 animate-none">
                    @foreach($captainAlertBattles as $ab)
                        @php
                            $isPendingInvite = $ab->status === 'pending_accept';
                            $isXiReady = $ab->playing_xi_notified_at !== null && $ab->status === 'locked_live';
                            $challengerTag = $ab->challenger?->captainTitle() ?? 'Captain';
                            $bannerMsg = $isPendingInvite
                                ? "⚔️ {$challengerTag} challenged you for {$ab->match_title} ({$ab->modeLabel()})! Tap to Accept & Enter Draft Room"
                                : ($isXiReady
                                    ? "🏏 Playing XI Out: {$ab->match_title} ({$ab->modeLabel()})! Review & swap any benched player before lock"
                                    : "⚡ Live Draft Room Active: {$ab->match_title} ({$ab->modeLabel()})! Tap to Pick Your Squad →");
                        @endphp
                        <a
                            href="{{ route('games.battle.show', $ab->slug) }}"
                            wire:navigate
                            class="inline-flex items-center gap-2 text-xs font-bold text-amber-200 hover:text-white whitespace-nowrap transition shrink-0"
                        >
                            <span>{{ $bannerMsg }}</span>
                            <span class="px-2 py-0.5 rounded bg-emerald-500 text-slate-950 text-[10px] font-black">Join →</span>
                        </a>
                    @endforeach
                </div>
            </div>
        </div>
    @endif

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
                <div x-show="activePinnedMatches.length > 0" x-cloak class="cricket-pinned-wrap">
                    <div class="flex items-center justify-between px-1">
                        <span class="text-xs font-bold uppercase tracking-wider text-amber-400 flex items-center gap-1.5">
                            <span>📌</span> Pinned Matches
                        </span>
                        <span class="text-[10px] text-[#94A3B8] font-semibold">
                            <span x-text="activePinnedMatches.length"></span> Pinned
                        </span>
                    </div>
                    
                    @foreach($activePinnableMatches as $match)
                        @php
                            $pId = (string) ($match['match_id'] ?? $match['id'] ?? '');
                            $pStatus = $match['_bucket_status'] ?? 'live';
                        @endphp
                        @if($pId)
                            <div wire:key="pinned-card-{{ $pId }}" x-show="pinnedMatches.includes('{{ $pId }}') && !skippedIds.includes('{{ $pId }}')">
                                <x-match-card :match="$match" :status="$pStatus" />
                            </div>
                        @endif
                    @endforeach
                </div>

                <!-- Primary Match Cards Stack Feed -->
                <div class="cricket-matches-stack">
                    @if($activeTab === 'live')
                        @forelse($liveMatches as $match)
                            @php $mId = (string) ($match['match_id'] ?? $match['id'] ?? ''); @endphp
                            <div x-show="!pinnedMatches.includes('{{ $mId }}') && !skippedIds.includes('{{ $mId }}')">
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
                            <div x-show="!pinnedMatches.includes('{{ $mId }}') && !skippedIds.includes('{{ $mId }}')">
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
                            <div x-show="!pinnedMatches.includes('{{ $mId }}') && !skippedIds.includes('{{ $mId }}')">
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

                <!-- Today's Upcoming Matches Horizontal Carousel -->
                @if(!empty($todayUpcomingMatches) && count($todayUpcomingMatches) > 0)
                    <section class="w-full min-w-0 space-y-3 pt-1" aria-label="Today's Upcoming Matches">
                        <div class="flex items-center justify-between gap-3 px-0.5">
                            <div class="flex items-center gap-2 min-w-0 flex-wrap">
                                <h2 class="text-xs sm:text-sm font-black uppercase tracking-wider text-[#F8FAFC] flex items-center gap-1.5 whitespace-nowrap">
                                    <span>⏰</span> Today's Upcoming
                                </h2>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/15 text-amber-300 border border-amber-500/30 whitespace-nowrap">
                                    {{ count($todayUpcomingMatches) }} Today
                                </span>
                                <span class="inline-flex items-center px-2 py-0.5 rounded-full text-[10px] font-semibold bg-slate-800/80 text-slate-300 border border-slate-700/70 whitespace-nowrap">
                                    {{ now('Asia/Kolkata')->format('D, d M') }}
                                </span>
                            </div>

                            <div class="flex items-center gap-2 shrink-0">
                                <div class="hidden md:flex items-center gap-1.5">
                                    <button
                                        type="button"
                                        @click="$refs.todaySlider.scrollBy({ left: -300, behavior: 'smooth' })"
                                        class="w-7 h-7 inline-flex items-center justify-center rounded-lg bg-[#111B2B] hover:bg-[#172337] border border-[#253248] hover:border-amber-500/40 text-xs font-bold text-slate-300 hover:text-amber-400 transition active:scale-95"
                                        aria-label="Scroll today's upcoming matches left"
                                    >
                                        ←
                                    </button>
                                    <button
                                        type="button"
                                        @click="$refs.todaySlider.scrollBy({ left: 300, behavior: 'smooth' })"
                                        class="w-7 h-7 inline-flex items-center justify-center rounded-lg bg-[#111B2B] hover:bg-[#172337] border border-[#253248] hover:border-amber-500/40 text-xs font-bold text-slate-300 hover:text-amber-400 transition active:scale-95"
                                        aria-label="Scroll today's upcoming matches right"
                                    >
                                        →
                                    </button>
                                </div>
                                <a
                                    href="{{ url('/cricket-schedule') }}"
                                    wire:navigate
                                    class="text-[11px] font-semibold text-[#94A3B8] hover:text-amber-400 transition whitespace-nowrap"
                                >
                                    All Fixtures →
                                </a>
                            </div>
                        </div>

                        <div
                            x-ref="todaySlider"
                            class="flex items-stretch gap-3 overflow-x-auto snap-x snap-mandatory scroll-smooth no-scrollbar pb-1"
                        >
                            @foreach($todayUpcomingMatches as $match)
                                @php
                                    $tMatchId = $match['match_id'] ?? $match['id'] ?? 0;
                                    $tSeriesName = $match['series_name'] ?? $match['series'] ?? 'Cricket Series';
                                    $tStage = $match['match_desc'] ?? $match['matchs'] ?? $match['format'] ?? 'Match';
                                    $tTeamAName = $match['team_a'] ?? 'Team A';
                                    $tTeamAShort = $match['team_a_short'] ?? \App\Services\Cricket\CricketLiveApiService::generateTeamShort($tTeamAName);
                                    $tTeamAImg = $match['team_a_img'] ?? '';
                                    $tTeamBName = $match['team_b'] ?? 'Team B';
                                    $tTeamBShort = $match['team_b_short'] ?? \App\Services\Cricket\CricketLiveApiService::generateTeamShort($tTeamBName);
                                    $tTeamBImg = $match['team_b_img'] ?? '';
                                    $tIstLabel = $match['ist_time_label'] ?? (!empty($match['start_time_ist']) ? \Carbon\Carbon::parse($match['start_time_ist'], 'Asia/Kolkata')->format('h:i A') . ' IST' : 'Today IST');
                                    $tSeriesSlug = \App\Helpers\CricketSlugHelper::makeSeriesSlug($match);
                                    $tMatchSlug = \App\Helpers\CricketSlugHelper::makeMatchSlug($match, $tMatchId);
                                    $tInfoUrl = route('match.detail', [
                                        'seriesSlug' => $tSeriesSlug,
                                        'tab' => 'info',
                                        'matchSlug' => $tMatchSlug,
                                    ]);
                                @endphp
                                <a
                                    href="{{ $tInfoUrl }}"
                                    wire:navigate
                                    class="group block snap-start shrink-0 w-[270px] sm:w-[295px] bg-slate-900/90 hover:bg-slate-800/90 border border-slate-800 hover:border-amber-500/40 rounded-2xl p-3.5 transition shadow-lg"
                                >
                                    {{-- Top Row: Series Name + IST Time Pill --}}
                                    <div class="flex items-center justify-between gap-2 mb-3">
                                        <span class="text-[11px] font-medium text-slate-400 truncate" title="{{ $tSeriesName }}">
                                            {{ $tSeriesName }}
                                        </span>
                                        <span class="shrink-0 inline-flex items-center gap-1 px-2 py-0.5 rounded-full text-[10px] font-bold bg-amber-500/15 text-amber-300 border border-amber-500/30 whitespace-nowrap">
                                            <span>🕒</span> {{ $tIstLabel }}
                                        </span>
                                    </div>

                                    {{-- Middle Row: Team A vs Team B --}}
                                    <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-2 py-1">
                                        <div class="flex items-center gap-2 min-w-0">
                                            @if(!empty($tTeamAImg))
                                                <img
                                                    src="{{ $tTeamAImg }}"
                                                    alt="{{ $tTeamAName }}"
                                                    loading="lazy"
                                                    class="w-7 h-7 rounded-full object-cover bg-[#0D1422] border border-slate-700 shrink-0"
                                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                                />
                                                <div style="display:none" class="w-7 h-7 rounded-full bg-[#172337] border border-amber-500/25 text-[10px] font-bold text-amber-400 items-center justify-center shrink-0">
                                                    {{ \Illuminate\Support\Str::limit($tTeamAShort, 3, '') }}
                                                </div>
                                            @else
                                                <div class="w-7 h-7 rounded-full bg-[#172337] border border-amber-500/25 text-[10px] font-bold text-amber-400 flex items-center justify-center shrink-0">
                                                    {{ \Illuminate\Support\Str::limit($tTeamAShort, 3, '') }}
                                                </div>
                                            @endif
                                            <span class="text-xs font-bold text-slate-100 truncate" title="{{ $tTeamAName }}">
                                                {{ $tTeamAShort }}
                                            </span>
                                        </div>

                                        <span class="px-1.5 py-0.5 rounded text-[9px] font-extrabold uppercase tracking-wider text-slate-400 bg-slate-800/80 border border-slate-700/70">
                                            VS
                                        </span>

                                        <div class="flex items-center justify-end gap-2 min-w-0">
                                            <span class="text-xs font-bold text-slate-100 truncate text-right" title="{{ $tTeamBName }}">
                                                {{ $tTeamBShort }}
                                            </span>
                                            @if(!empty($tTeamBImg))
                                                <img
                                                    src="{{ $tTeamBImg }}"
                                                    alt="{{ $tTeamBName }}"
                                                    loading="lazy"
                                                    class="w-7 h-7 rounded-full object-cover bg-[#0D1422] border border-slate-700 shrink-0"
                                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';"
                                                />
                                                <div style="display:none" class="w-7 h-7 rounded-full bg-[#172337] border border-amber-500/25 text-[10px] font-bold text-amber-400 items-center justify-center shrink-0">
                                                    {{ \Illuminate\Support\Str::limit($tTeamBShort, 3, '') }}
                                                </div>
                                            @else
                                                <div class="w-7 h-7 rounded-full bg-[#172337] border border-amber-500/25 text-[10px] font-bold text-amber-400 flex items-center justify-center shrink-0">
                                                    {{ \Illuminate\Support\Str::limit($tTeamBShort, 3, '') }}
                                                </div>
                                            @endif
                                        </div>
                                    </div>

                                    {{-- Toss Status Strip (30-min pre-match window) --}}
                                    @if(!empty($match['show_toss_banner']) && in_array($match['toss_status'] ?? '', ['pending', 'delayed', 'decided'], true))
                                        <div class="mt-2.5">
                                            @if(($match['toss_status'] ?? '') === 'pending')
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-semibold bg-amber-500/15 text-amber-300 border border-amber-500/30 truncate max-w-full">
                                                    🪙 Toss is pending
                                                </span>
                                            @elseif(($match['toss_status'] ?? '') === 'delayed')
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-semibold bg-orange-500/15 text-orange-300 border border-orange-500/30 truncate max-w-full">
                                                    🌧️ {{ $match['toss_text'] ?? '' }}
                                                </span>
                                            @else
                                                <span class="inline-flex items-center gap-1 px-2 py-0.5 rounded-md text-[10px] font-semibold bg-emerald-500/15 text-emerald-300 border border-emerald-500/30 truncate max-w-full">
                                                    🪙 {{ $match['toss_text'] ?? '' }}
                                                </span>
                                            @endif
                                        </div>
                                    @endif

                                    {{-- Bottom Row: Match Stage + Match Info Link --}}
                                    <div class="flex items-center justify-between gap-2 mt-3 pt-2.5 border-t border-slate-800/90 text-[11px]">
                                        <span class="text-slate-400 font-medium truncate" title="{{ $tStage }}">
                                            {{ $tStage }}
                                        </span>
                                        <span class="text-amber-400 group-hover:text-amber-300 font-semibold shrink-0 transition-colors">
                                            Match Info →
                                        </span>
                                    </div>
                                </a>
                            @endforeach
                        </div>
                    </section>
                @endif

                <!-- Live & Upcoming Captain Battles Horizontal Slider -->
                <section class="cricket-card mt-2">
                    <div class="cricket-card-header">
                        <h2 class="text-xs font-bold uppercase tracking-wider text-amber-400 flex items-center gap-2">
                            <span>⚔️</span> Live &amp; Upcoming Captain Battles
                        </h2>
                        <a href="{{ route('games.index') }}" wire:navigate class="text-[11px] font-semibold text-[#94A3B8] hover:text-amber-400 transition">
                            Open Games Hub →
                        </a>
                    </div>
                    <div class="cricket-card-body">
                        @if(($recentSquadBattles ?? collect())->isEmpty())
                            <div class="p-4 rounded-xl bg-[#0D1422] border border-[#253248] flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <div>
                                    <div class="text-xs font-bold text-white">Challenge a friend to a 5v5 or 3v3 Squad Runs Battle!</div>
                                    <div class="text-[11px] text-[#94A3B8] mt-0.5">Draft players live turn-by-turn and win 1 CricGem per Run.</div>
                                </div>
                                <a href="{{ route('games.index') }}" wire:navigate class="inline-flex items-center justify-center px-4 py-2 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 text-xs font-black uppercase tracking-wider shrink-0 transition">
                                    ⚔️ Start Battle
                                </a>
                            </div>
                        @else
                            <div class="flex items-stretch gap-3 overflow-x-auto pb-2 no-scrollbar">
                                @foreach($recentSquadBattles as $sb)
                                    <a
                                        href="{{ route('games.battle.show', $sb->slug) }}"
                                        wire:navigate
                                        class="min-w-[260px] max-w-[280px] shrink-0 p-3.5 rounded-xl bg-[#0D1422] hover:bg-[#172337] border border-[#253248] hover:border-amber-500/40 transition flex flex-col justify-between gap-2.5 group"
                                    >
                                        <div class="flex items-center justify-between gap-2">
                                            <span class="px-2 py-0.5 rounded text-[10px] font-black {{ $sb->status === 'locked_live' ? 'bg-emerald-500/20 text-emerald-300' : ($sb->status === 'settled' ? 'bg-amber-500/20 text-amber-300' : 'bg-cyan-500/20 text-cyan-300') }}">
                                                {{ $sb->status === 'locked_live' ? '⚡ LIVE' : ($sb->status === 'settled' ? '🏆 SETTLED' : '🪙 DRAFTING') }}
                                            </span>
                                            <span class="text-[10px] font-mono font-bold text-slate-400">{{ $sb->modeLabel() }} • {{ $sb->match_format }}</span>
                                        </div>
                                        <div>
                                            <div class="text-xs font-black text-white group-hover:text-amber-300 transition truncate">
                                                {{ $sb->challenger?->captainTitle() }} vs {{ $sb->opponent?->captainTitle() }}
                                            </div>
                                            <div class="text-[11px] text-[#94A3B8] truncate mt-0.5">{{ $sb->match_title }}</div>
                                        </div>
                                        <div class="flex items-center justify-between pt-2 border-t border-[#253248] text-xs font-mono font-black">
                                            <span class="text-amber-300">{{ $sb->challenger_runs }}</span>
                                            <span class="text-[10px] text-slate-500 font-sans">
                                                {{ $sb->status === 'settled' && $sb->winner ? "Won by {$sb->run_margin}r" : 'Runs' }}
                                            </span>
                                            <span class="text-cyan-300">{{ $sb->opponent_runs }}</span>
                                        </div>
                                    </a>
                                @endforeach
                            </div>
                        @endif
                    </div>
                </section>

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
