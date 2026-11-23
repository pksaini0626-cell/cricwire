@props(['match', 'status' => null])

@php
    $matchId = $match['match_id'] ?? $match['id'] ?? null;
    $series = $match['series'] ?? $match['series_name'] ?? 'Cricket Series';
    $matchs = $match['matchs'] ?? $match['match_desc'] ?? $match['match_type'] ?? 'Match';
    $venue = $match['venue'] ?? '';
    
    // Status detection
    $rawStatus = $status ?? $match['match_status'] ?? $match['status'] ?? $match['state'] ?? 'Live';
    $isLive = is_string($rawStatus) ? strtolower($rawStatus) === 'live' : ($rawStatus == 2 || $rawStatus == 1);
    
    $statusNote = (string) ($match['status_note'] ?? '');
    $resultText = (string) ($match['result'] ?? (str_contains(strtolower($statusNote), 'won') ? $statusNote : ''));
    $stateStr = strtolower((string) ($match['state'] ?? ''));
    $isFinished = !empty($resultText) || in_array($stateStr, ['complete', 'finished', 'completed', 'abandoned'], true) || (is_string($rawStatus) && in_array(strtolower($rawStatus), ['finished', 'complete', 'completed'], true));

    // Team A details
    $teamAName = $match['team_a'] ?? 'Team A';
    $teamAShort = $match['team_a_short'] ?? \App\Services\Cricket\CricketLiveApiService::generateTeamShort($teamAName);
    $teamAImg = $match['team_a_img'] ?? '';
    $rawScoreA = (string) ($match['score_a'] ?? $match['team_a_scores'] ?? $match['team_a_score'] ?? $match['score'] ?? '');
    $teamAOver = (string) ($match['team_a_over'] ?? $match['over_a'] ?? '');
    $teamAScores = $rawScoreA;
    if (!str_contains($rawScoreA, '&')) {
        if (preg_match('/^(.*?)\s*\(([0-9.]+(?:\s*ov)?)\)$/i', trim($rawScoreA), $mA)) {
            $teamAScores = trim($mA[1]);
            if ($teamAOver === '') {
                $teamAOver = $mA[2];
            }
        }
    }
    $teamAOver = \App\Helpers\CricketMatchFormatter::normalizeOvers(preg_replace('/\s*ov\b/i', '', $teamAOver));
    $teamAScores = \App\Helpers\CricketMatchFormatter::normalizeScoreString($teamAScores);

    // Team B details
    $teamBName = $match['team_b'] ?? 'Team B';
    $teamBShort = $match['team_b_short'] ?? \App\Services\Cricket\CricketLiveApiService::generateTeamShort($teamBName);
    $teamBImg = $match['team_b_img'] ?? '';
    $rawScoreB = (string) ($match['score_b'] ?? $match['team_b_scores'] ?? $match['team_b_score'] ?? '');
    $teamBOver = (string) ($match['team_b_over'] ?? $match['over_b'] ?? '');
    $teamBScores = $rawScoreB;
    if (!str_contains($rawScoreB, '&')) {
        if (preg_match('/^(.*?)\s*\(([0-9.]+(?:\s*ov)?)\)$/i', trim($rawScoreB), $mB)) {
            $teamBScores = trim($mB[1]);
            if ($teamBOver === '') {
                $teamBOver = $mB[2];
            }
        }
    }
    $teamBOver = \App\Helpers\CricketMatchFormatter::normalizeOvers(preg_replace('/\s*ov\b/i', '', $teamBOver));
    $teamBScores = \App\Helpers\CricketMatchFormatter::normalizeScoreString($teamBScores);

    // Requirement & Dates
    $needRunBall = (string) ($match['need_run_ball'] ?? $match['trail_lead'] ?? '');
    $toss = (string) ($match['toss'] ?? '');
    $matchTime = (string) ($match['match_time'] ?? '');
    $matchDate = (string) ($match['match_date'] ?? $match['start_time'] ?? '');

    // SEO-Friendly Match Detail URL (Finished matches route directly to scorecard)
    $targetTab = $isFinished ? 'scorecard' : 'live-line';
    $detailUrl = \App\Helpers\CricketSlugHelper::getMatchUrl($match, $targetTab);
    $statusString = $isFinished ? 'finished' : ($isLive ? 'live' : 'upcoming');
    $seriesSlug = \App\Helpers\CricketSlugHelper::makeSeriesSlug($match);
@endphp

<div 
    x-data="{
        matchId: '{{ $matchId }}',
        status: '{{ $statusString }}',
        isPinned: false,
        isSkipped: false,
        teamAScore: '{{ addslashes($teamAScores) }}',
        teamAOver: '{{ addslashes($teamAOver) }}',
        teamBScore: '{{ addslashes($teamBScores) }}',
        teamBOver: '{{ addslashes($teamBOver) }}',
        needRunBall: '{{ addslashes($needRunBall) }}',
        resultText: '{{ addslashes($resultText) }}',
        init() {
            const pinned = JSON.parse(localStorage.getItem('pinned_matches') || '[]');
            const skipped = JSON.parse(localStorage.getItem('skipped_matches') || '[]');

            if (['finished', 'completed', 'abandoned'].includes(this.status)) {
                const updated = pinned.filter(id => String(id) !== String(this.matchId));
                localStorage.setItem('pinned_matches', JSON.stringify(updated));
                this.isPinned = false;
            } else {
                this.isPinned = pinned.includes(String(this.matchId));
            }

            this.isSkipped = skipped.includes(String(this.matchId));

            if (this.status !== 'finished') {
                window.addEventListener('ticker-score-updated', (event) => {
                    if (String(event.detail?.matchId) === String(this.matchId)) {
                        const d = event.detail.data;
                        const rawA = d.team_a_scores ?? d.score_a ?? d.score;
                        const rawB = d.team_b_scores ?? d.score_b;
                        
                        const normOv = (ov) => {
                            if (!ov) return '';
                            const clean = String(ov).replace(/\s*ov/i, '').trim();
                            const m = clean.match(/^(\d+)\.6$/);
                            return m ? String(parseInt(m[1], 10) + 1) : clean;
                        };
                        const normSc = (sc) => {
                            if (!sc) return '';
                            return String(sc).replace(/\((\d+)\.6(\s*ov)?\)/gi, (m, p1, p2) => '(' + (parseInt(p1, 10) + 1) + (p2 || ' ov') + ')');
                        };

                        if (rawA !== undefined) {
                            const strA = normSc(String(rawA));
                            if (!strA.includes('&')) {
                                const matchA = strA.match(/^(.*?)\s*\(([0-9.]+(?:\s*ov)?)\)$/i);
                                if (matchA) {
                                    this.teamAScore = matchA[1].trim();
                                    if (d.team_a_over === undefined) this.teamAOver = normOv(matchA[2]);
                                } else {
                                    this.teamAScore = strA;
                                }
                            } else {
                                this.teamAScore = strA;
                                this.teamAOver = '';
                            }
                        }
                        if (d.team_a_over !== undefined && !String(rawA || '').includes('&')) {
                            this.teamAOver = normOv(d.team_a_over);
                        }

                        if (rawB !== undefined) {
                            const strB = normSc(String(rawB));
                            if (!strB.includes('&')) {
                                const matchB = strB.match(/^(.*?)\s*\(([0-9.]+(?:\s*ov)?)\)$/i);
                                if (matchB) {
                                    this.teamBScore = matchB[1].trim();
                                    if (d.team_b_over === undefined) this.teamBOver = normOv(matchB[2]);
                                } else {
                                    this.teamBScore = strB;
                                }
                            } else {
                                this.teamBScore = strB;
                                this.teamBOver = '';
                            }
                        }
                        if (d.team_b_over !== undefined && !String(rawB || '').includes('&')) {
                            this.teamBOver = normOv(d.team_b_over);
                        }

                        if (d.trail_lead !== undefined && d.trail_lead !== '') {
                            this.needRunBall = d.trail_lead;
                        } else if (d.need_run_ball !== undefined) {
                            this.needRunBall = d.need_run_ball;
                        }
                        if (d.result !== undefined && d.result !== '') this.resultText = d.result;
                    }
                });
            }
        },
        togglePin() {
            let pinned = JSON.parse(localStorage.getItem('pinned_matches') || '[]');
            const id = String(this.matchId);
            if (pinned.includes(id)) {
                pinned = pinned.filter(i => i !== id);
                this.isPinned = false;
            } else {
                pinned.push(id);
                this.isPinned = true;
            }
            localStorage.setItem('pinned_matches', JSON.stringify(pinned));
            window.dispatchEvent(new CustomEvent('match-pin-updated'));
            window.dispatchEvent(new CustomEvent('pinned-matches-updated'));
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
    x-cloak
    class="relative group"
>
    <a 
        href="{{ $detailUrl }}" 
        wire:navigate 
        class="block rounded-lg bg-[#111B2B] border {{ $isLive ? 'border-amber-500/30' : 'border-[#253248]' }} hover:border-amber-500/40 transition-all duration-150 relative overflow-hidden"
        :class="{ 'ring-1 ring-amber-400/50 border-amber-500/40': isPinned }"
    >
        {{-- ───── HEADER ROW: Badge · Series · Status · Actions ───── --}}
        <div class="flex items-center justify-between gap-2 px-3 py-2 bg-[#0D1422]/60 border-b border-[#253248]/60">
            {{-- Left: Format badge + Series name --}}
            <div class="flex items-center gap-2 min-w-0 flex-1">
                <span class="px-1.5 py-px rounded text-[9px] font-bold uppercase tracking-wider bg-[#172337] text-amber-400 border border-amber-500/15 shrink-0 leading-relaxed">
                    {{ $matchs }}
                </span>
                <a 
                    href="{{ route('series.detail', ['seriesSlug' => $seriesSlug, 'tab' => 'matches']) }}" 
                    wire:navigate 
                    @click.stop
                    class="text-[#94A3B8] hover:text-amber-400 transition-colors truncate text-[11px] font-medium" 
                    title="{{ $series }}"
                >
                    {{ $series }}
                </a>
            </div>

            {{-- Right: Status + Pin + Skip --}}
            <div class="flex items-center gap-1 shrink-0">
                @if($isLive)
                    <span class="inline-flex items-center gap-1 text-[9px] font-bold uppercase tracking-wider bg-[#EF233C]/15 text-[#EF233C] px-1.5 py-px rounded border border-[#EF233C]/25 leading-relaxed">
                        <span class="w-1.5 h-1.5 bg-[#EF233C] rounded-full animate-ping"></span>
                        LIVE
                    </span>
                @elseif($isFinished)
                    <span class="px-1.5 py-px rounded text-[9px] font-semibold bg-[#22C55E]/12 text-[#22C55E] border border-[#22C55E]/25 leading-relaxed">
                        Finished
                    </span>
                @else
                    <span class="px-1.5 py-px rounded text-[9px] font-medium bg-[#172337] text-amber-300 border border-amber-500/15 leading-relaxed">
                        {{ $matchDate ?: 'Upcoming' }} {{ $matchTime }}
                    </span>
                @endif

                <button 
                    type="button" 
                    @click.prevent.stop="togglePin()" 
                    class="w-6 h-6 flex items-center justify-center rounded transition active:scale-90"
                    :class="isPinned ? 'text-amber-400' : 'text-[#64748B] hover:text-[#F8FAFC]'"
                    :title="isPinned ? 'Unpin' : 'Pin to top'"
                    aria-label="Pin Match"
                >
                    <template x-if="isPinned">
                        <svg class="w-3 h-3 fill-amber-400" viewBox="0 0 20 20"><path d="M9.049 2.927c.3-.921 1.603-.921 1.902 0l1.07 3.292a1 1 0 00.95.69h3.462c.969 0 1.371 1.24.588 1.81l-2.8 2.034a1 1 0 00-.364 1.118l1.07 3.292c.3.921-.755 1.688-1.54 1.118l-2.8-2.034a1 1 0 00-1.175 0l-2.8 2.034c-.784.57-1.838-.197-1.539-1.118l1.07-3.292a1 1 0 00-.364-1.118L2.98 8.72c-.783-.57-.38-1.81.588-1.81h3.461a1 1 0 00.951-.69l1.07-3.292z"/></svg>
                    </template>
                    <template x-if="!isPinned">
                        <svg class="w-3 h-3 stroke-current" fill="none" viewBox="0 0 24 24" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M11.049 2.927c.3-.921 1.603-.921 1.902 0l1.519 4.674a1 1 0 00.95.69h4.915c.969 0 1.371 1.24.588 1.81l-3.976 2.888a1 1 0 00-.363 1.118l1.518 4.674c.3.922-.755 1.688-1.538 1.118l-3.976-2.888a1 1 0 00-1.176 0l-3.976 2.888c-.783.57-1.838-.197-1.538-1.118l1.518-4.674a1 1 0 00-.363-1.118l-3.976-2.888c-.784-.57-.38-1.81.588-1.81h4.914a1 1 0 00.951-.69l1.519-4.674z"/></svg>
                    </template>
                </button>

                <button 
                    type="button" 
                    @click.prevent.stop="skipMatch()" 
                    class="w-6 h-6 flex items-center justify-center rounded text-[#64748B] hover:text-[#EF233C] transition active:scale-90"
                    title="Hide match"
                    aria-label="Skip Match"
                >
                    <svg class="w-3 h-3 stroke-current" fill="none" viewBox="0 0 24 24" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>

        {{-- ───── SCOREBOARD: Team A — VS — Team B ───── --}}
        <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-1.5 sm:gap-3 px-3 py-2.5">
            {{-- Team A (left-aligned) --}}
            <div class="flex items-center gap-2 min-w-0">
                @if(!empty($teamAImg))
                    <img src="{{ $teamAImg }}" alt="{{ $teamAName }}" class="w-8 h-8 sm:w-9 sm:h-9 rounded-full object-cover bg-[#0D1422] border border-[#253248] shrink-0" onerror="this.style.display='none'" />
                @else
                    <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-full bg-[#172337] border border-amber-500/20 text-[10px] font-bold text-amber-400 flex items-center justify-center shrink-0">
                        {{ substr($teamAShort, 0, 3) }}
                    </div>
                @endif
                <div class="min-w-0">
                    <div class="font-semibold text-[#F8FAFC] text-[13px] sm:text-sm truncate" title="{{ $teamAName }}">{{ $teamAName }}</div>
                    <div x-show="teamAScore" class="text-xs font-mono font-bold text-[#F8FAFC] mt-px">
                        <span x-text="teamAScore"></span>
                        <span x-show="teamAOver" class="text-[10px] text-[#94A3B8] font-normal ml-0.5" x-text="'(' + teamAOver + ' ov)'"></span>
                    </div>
                    <div x-show="!teamAScore" class="text-[10px] text-[#64748B] font-mono mt-px">Yet to bat</div>
                </div>
            </div>

            {{-- VS badge --}}
            <div class="flex items-center justify-center">
                <span class="text-[9px] font-semibold text-[#64748B] uppercase">vs</span>
            </div>

            {{-- Team B (right-aligned) --}}
            <div class="flex items-center justify-end gap-2 min-w-0">
                <div class="min-w-0 text-right">
                    <div class="font-semibold text-[#F8FAFC] text-[13px] sm:text-sm truncate" title="{{ $teamBName }}">{{ $teamBName }}</div>
                    <div x-show="teamBScore" class="text-xs font-mono font-bold text-[#F8FAFC] mt-px">
                        <span x-text="teamBScore"></span>
                        <span x-show="teamBOver" class="text-[10px] text-[#94A3B8] font-normal ml-0.5" x-text="'(' + teamBOver + ' ov)'"></span>
                    </div>
                    <div x-show="!teamBScore" class="text-[10px] text-[#64748B] font-mono mt-px">Yet to bat</div>
                </div>
                @if(!empty($teamBImg))
                    <img src="{{ $teamBImg }}" alt="{{ $teamBName }}" class="w-8 h-8 sm:w-9 sm:h-9 rounded-full object-cover bg-[#0D1422] border border-[#253248] shrink-0" onerror="this.style.display='none'" />
                @else
                    <div class="w-8 h-8 sm:w-9 sm:h-9 rounded-full bg-[#172337] border border-amber-500/20 text-[10px] font-bold text-amber-400 flex items-center justify-center shrink-0">
                        {{ substr($teamBShort, 0, 3) }}
                    </div>
                @endif
            </div>
        </div>

        {{-- ───── STATUS FOOTER: Result / Requirement / Toss / Venue ───── --}}
        <template x-if="resultText">
            <div class="px-3 py-1.5 bg-[#0D1422]/70 border-t border-[#253248]/50 text-[11px] text-[#22C55E] font-semibold flex items-center gap-1.5 truncate">
                <svg class="w-3 h-3 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7" /></svg>
                <span class="truncate" x-text="resultText"></span>
            </div>
        </template>
        <template x-if="!resultText && needRunBall">
            <div class="px-3 py-1.5 bg-[#0D1422]/70 border-t border-[#253248]/50 text-[11px] text-amber-400 font-semibold flex items-center gap-1.5 truncate">
                <span class="w-1.5 h-1.5 rounded-full bg-amber-400 animate-pulse shrink-0"></span>
                <span class="truncate" x-text="needRunBall"></span>
            </div>
        </template>
        @if(!empty($toss) || !empty($match['status_note']) || !empty($venue))
            <template x-if="!resultText && !needRunBall">
                <div class="px-3 py-1.5 bg-[#0D1422]/70 border-t border-[#253248]/50 text-[11px] truncate">
                    @if(!empty($toss))
                        <span class="text-[#94A3B8]"><span class="text-[#F8FAFC] font-medium">Toss:</span> {{ $toss }}</span>
                    @elseif(!empty($match['status_note']) && $match['status_note'] !== $resultText)
                        <span class="text-[#94A3B8]">{{ $match['status_note'] }}</span>
                    @elseif(!empty($venue))
                        <span class="text-[#64748B] flex items-center gap-1">
                            <svg class="w-2.5 h-2.5 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z" /></svg>
                            {{ $venue }}
                        </span>
                    @endif
                </div>
            </template>
        @endif
    </a>
</div>
