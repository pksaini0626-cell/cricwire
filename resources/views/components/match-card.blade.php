@props(['match', 'status' => null])

@php
    $matchId = $match['match_id'] ?? $match['id'] ?? null;
    $series = $match['series'] ?? $match['series_name'] ?? 'Cricket Series';
    $matchs = $match['matchs'] ?? $match['match_desc'] ?? $match['match_type'] ?? 'Match';
    $venue = $match['venue'] ?? '';
    
    // Status detection
    $rawStatus = $status ?? $match['match_status'] ?? $match['status'] ?? 'Live';
    $isLive = is_string($rawStatus) ? strtolower($rawStatus) === 'live' : ($rawStatus == 2 || $rawStatus == 1);
    $resultText = $match['result'] ?? '';
    $isFinished = !empty($resultText) || (is_string($rawStatus) && strtolower($rawStatus) === 'finished');

    // Team A details
    $teamAName = $match['team_a'] ?? 'Team A';
    $teamAShort = $match['team_a_short'] ?? substr($teamAName, 0, 3);
    $teamAImg = $match['team_a_img'] ?? '';
    $teamAScores = $match['team_a_scores'] ?? '';
    $teamAOver = $match['team_a_over'] ?? '';

    // Team B details
    $teamBName = $match['team_b'] ?? 'Team B';
    $teamBShort = $match['team_b_short'] ?? substr($teamBName, 0, 3);
    $teamBImg = $match['team_b_img'] ?? '';
    $teamBScores = $match['team_b_scores'] ?? '';
    $teamBOver = $match['team_b_over'] ?? '';

    // Requirement & Dates
    $needRunBall = $match['need_run_ball'] ?? '';
    $toss = $match['toss'] ?? '';
    $matchTime = $match['match_time'] ?? '';
    $matchDate = $match['match_date'] ?? '';

    // SEO-Friendly Match Detail URL
    $detailUrl = \App\Helpers\CricketSlugHelper::getMatchUrl($match, 'live-line');
    $statusString = $isFinished ? 'finished' : ($isLive ? 'live' : 'upcoming');
@endphp

<div 
    x-data="{
        matchId: '{{ $matchId }}',
        status: '{{ $statusString }}',
        isPinned: false,
        isSkipped: false,
        init() {
            const pinned = JSON.parse(localStorage.getItem('pinned_matches') || '[]');
            const skipped = JSON.parse(localStorage.getItem('skipped_matches') || '[]');

            // Auto-unpin if finished/abandoned
            if (['finished', 'completed', 'abandoned'].includes(this.status)) {
                const updated = pinned.filter(id => String(id) !== String(this.matchId));
                localStorage.setItem('pinned_matches', JSON.stringify(updated));
                this.isPinned = false;
            } else {
                this.isPinned = pinned.some(id => String(id) === String(this.matchId));
            }

            this.isSkipped = skipped.some(id => String(id) === String(this.matchId));
        },
        togglePin() {
            let pinned = JSON.parse(localStorage.getItem('pinned_matches') || '[]');
            if (this.isPinned) {
                pinned = pinned.filter(id => String(id) !== String(this.matchId));
                this.isPinned = false;
            } else {
                if (!pinned.some(id => String(id) === String(this.matchId))) {
                    pinned.push(String(this.matchId));
                }
                this.isPinned = true;
            }
            localStorage.setItem('pinned_matches', JSON.stringify(pinned));
            window.dispatchEvent(new CustomEvent('match-pin-updated'));
        },
        skipMatch() {
            let skipped = JSON.parse(localStorage.getItem('skipped_matches') || '[]');
            if (!skipped.some(id => String(id) === String(this.matchId))) {
                skipped.push(String(this.matchId));
                localStorage.setItem('skipped_matches', JSON.stringify(skipped));
            }
            this.isSkipped = true;
            window.dispatchEvent(new CustomEvent('match-skip-updated'));
        }
    }"
    x-show="!isSkipped"
    class="relative group"
>
    <a href="{{ $detailUrl }}" wire:navigate class="block rounded-2xl gaming-card {{ $isLive ? 'gaming-card-active' : 'hover:border-white/20' }} p-3.5 pb-4 transition-all duration-300 relative overflow-hidden shadow-lg" :class="{ 'ring-1 ring-amber-400/50 bg-slate-900/90': isPinned }">
        <!-- Top Header: Tournament Badge + LIVE Chip + Action Icons -->
        <div class="flex items-center justify-between text-[11px] pb-2.5 mb-2.5 border-b border-white/5">
            <div class="flex items-center space-x-1.5 overflow-hidden pr-2">
                <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase tracking-wider bg-slate-800/80 text-amber-400 border border-amber-500/20 shrink-0">
                    {{ $matchs }}
                </span>
                <span class="text-slate-600 font-bold">•</span>
                <span class="text-slate-400 truncate text-[11px] font-medium" title="{{ $series }}">
                    {{ $series }}
                </span>
            </div>

            <div class="flex items-center gap-1.5 shrink-0">
                @if($isLive)
                    <span class="inline-flex items-center gap-1.5 text-[10px] font-black uppercase tracking-wider bg-red-500/20 text-red-400 glow-red">
                        <span class="w-1.5 h-1.5 bg-red-500 rounded-full animate-ping"></span>
                    </span>
                @elseif($isFinished)
                    <span class="px-2 py-0.5 rounded-md text-[10px] font-bold bg-emerald-500/15 text-emerald-400 border border-emerald-500/30">
                        Finished
                    </span>
                @else
                    <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-slate-800/90 text-amber-300 border border-amber-500/20">
                        {{ $matchDate ?: 'Upcoming' }} {{ $matchTime }}
                    </span>
                @endif

                <!-- Pin Match Button -->
                <button 
                    type="button" 
                    @click.prevent.stop="togglePin()" 
                    class="p-1 rounded-md text-xs transition active:scale-90"
                    :class="isPinned ? 'text-amber-400 hover:text-amber-300 bg-amber-500/10' : 'text-slate-500 hover:text-slate-300'"
                    :title="isPinned ? 'Unpin match' : 'Pin match to top'"
                    aria-label="Pin Match"
                >
                    <span x-show="!isPinned" class="opacity-60 hover:opacity-100">📌</span>
                    <span x-show="isPinned">⭐</span>
                </button>

                <!-- Skip / Hide Match Button -->
                <button 
                    type="button" 
                    @click.prevent.stop="skipMatch()" 
                    class="p-1 rounded-md text-slate-500 hover:text-rose-400 text-xs transition active:scale-90 opacity-60 hover:opacity-100"
                    title="Hide / Skip this match"
                    aria-label="Skip Match"
                >
                    ✕
                </button>
            </div>
        </div>

    <!-- Middle Section: Team A vs Team B -->
    <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-2 py-1">
        <!-- Team A -->
        <div class="flex items-center space-x-2.5 min-w-0">
            @if(!empty($teamAImg))
                <img src="{{ $teamAImg }}" alt="{{ $teamAName }}" class="w-8 h-8 rounded-full object-cover bg-slate-800 border border-white/10 shrink-0 shadow-sm" onerror="this.style.display='none'">
            @else
                <div class="w-8 h-8 rounded-full bg-slate-800 border border-white/10 text-[10px] font-black text-amber-400 flex items-center justify-center shrink-0 shadow-inner">
                    {{ substr($teamAShort, 0, 2) }}
                </div>
            @endif
            <div class="truncate">
                <div class="font-extrabold text-white text-sm tracking-wide group-hover:text-amber-300 transition-colors truncate">
                    {{ $teamAShort }}
                </div>
                @if(!empty($teamAScores))
                    <div class="text-xs font-mono font-bold text-slate-100 tracking-tight">{{ $teamAScores }}</div>
                    @if(!empty($teamAOver))
                        <div class="text-[9px] text-slate-400 font-mono">({{ $teamAOver }} ov)</div>
                    @endif
                @else
                    <div class="text-[11px] text-slate-500 font-mono">-</div>
                @endif
            </div>
        </div>

        <!-- VS Divider Badge -->
        <div class="flex items-center justify-center px-1">
            <span class="w-6 h-6 rounded-full bg-slate-800/90 border border-amber-500/30 text-[9px] font-black text-amber-400 flex items-center justify-center shadow-md">
                VS
            </span>
        </div>

        <!-- Team B -->
        <div class="flex items-center justify-end space-x-2.5 min-w-0 text-right">
            <div class="truncate">
                <div class="font-extrabold text-white text-sm tracking-wide group-hover:text-amber-300 transition-colors truncate">
                    {{ $teamBShort }}
                </div>
                @if(!empty($teamBScores))
                    <div class="text-xs font-mono font-bold text-slate-100 tracking-tight">{{ $teamBScores }}</div>
                    @if(!empty($teamBOver))
                        <div class="text-[9px] text-slate-400 font-mono">({{ $teamBOver }} ov)</div>
                    @endif
                @else
                    <div class="text-[11px] text-slate-500 font-mono">-</div>
                @endif
            </div>
            @if(!empty($teamBImg))
                <img src="{{ $teamBImg }}" alt="{{ $teamBName }}" class="w-8 h-8 rounded-full object-cover bg-slate-800 border border-white/10 shrink-0 shadow-sm" onerror="this.style.display='none'">
            @else
                <div class="w-8 h-8 rounded-full bg-slate-800 border border-white/10 text-[10px] font-black text-amber-400 flex items-center justify-center shrink-0 shadow-inner">
                    {{ substr($teamBShort, 0, 2) }}
                </div>
            @endif
        </div>
    </div>

    <!-- Status Bar: Requirement / Result / Toss -->
    <div class="mt-2.5 pt-2 border-t border-white/5 text-xs">
        @if(!empty($resultText))
            <div class="text-emerald-400 font-bold flex items-center gap-1.5 truncate">
                <svg class="w-3.5 h-3.5 shrink-0 text-emerald-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" />
                </svg>
                <span class="truncate">{{ $resultText }}</span>
            </div>
        @elseif(!empty($needRunBall))
            <div class="text-neon-gold font-bold flex items-center gap-1.5 truncate animate-pulse">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-400"></span>
                <span class="truncate">{{ $needRunBall }}</span>
            </div>
        @elseif(!empty($toss))
            <div class="text-slate-400 text-[11px] truncate">
                <span class="text-slate-300 font-semibold">Toss:</span> {{ $toss }}
            </div>
        @elseif(!empty($venue))
            <div class="text-slate-500 text-[11px] truncate flex items-center gap-1">
                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" />
                </svg>
                <span class="truncate">{{ $venue }}</span>
            </div>
        @endif
    </div>
    </a>
</div>

