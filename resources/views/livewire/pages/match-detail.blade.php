<div 
    class="min-h-screen bg-slate-950 text-slate-100 pb-24 font-sans antialiased flex flex-col selection:bg-rose-500 selection:text-white transition-all duration-300"
    :class="{ 'ring-2 ring-amber-400': isFlashing }"
    x-data="{
        soundEnabled: false,
        isFlashing: false,
        lastEvent: '',
        matchId: '{{ $matchId }}',
        isPinned: false,
        isSkipped: false,
        userVotes: {},
        init() {
            // Echo Real-Time Listener
            if (window.Echo) {
                window.Echo.channel('match.{{ $matchId }}')
                    .listen('LiveScoreUpdated', (e) => {
                        this.handleBallEvent(e);
                        if (typeof $wire !== 'undefined' && $wire.$refresh) {
                            $wire.$refresh();
                        }
                    });
            }

            // Sync user local preferences
            const pinned = JSON.parse(localStorage.getItem('pinned_matches') || '[]');
            const skipped = JSON.parse(localStorage.getItem('skipped_matches') || '[]');
            this.isPinned = pinned.some(id => String(id) === String(this.matchId));
            this.isSkipped = skipped.some(id => String(id) === String(this.matchId));
            this.userVotes = JSON.parse(localStorage.getItem('user_poll_votes') || '{}');
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
            window.location.href = '{{ url('/') }}';
        },
        castVote(pollId, option) {
            if (this.userVotes[pollId]) return;
            this.userVotes[pollId] = option;
            localStorage.setItem('user_poll_votes', JSON.stringify(this.userVotes));
            if (typeof $wire !== 'undefined' && $wire.vote) {
                $wire.vote(pollId, option);
            }
        },
        handleBallEvent(e) {
            this.isFlashing = true;
            setTimeout(() => { this.isFlashing = false; }, 800);

            const payload = e.payload || e;
            const eventType = payload.last_ball_event || payload.event || payload.raw_status || '';

            if (eventType && eventType !== this.lastEvent) {
                this.lastEvent = eventType;
                this.speakEvent(eventType);
            }
        },
        speakEvent(text) {
            if (!this.soundEnabled || !('speechSynthesis' in window)) {
                return;
            }

            let speechText = text;
            const lower = (text || '').toLowerCase();

            if (lower.includes('four') || lower === '4') {
                speechText = 'Four Runs!';
            } else if (lower.includes('six') || lower === '6') {
                speechText = 'Six Runs!';
            } else if (lower.includes('out') || lower.includes('wicket') || lower === 'w') {
                speechText = 'Wicket!';
            } else if (lower.includes('dot') || lower === '0' || lower === '0 run') {
                speechText = 'Dot Ball';
            } else if (lower.includes('1 run') || lower === '1') {
                speechText = 'One Run';
            } else if (lower.includes('2 run') || lower === '2') {
                speechText = 'Two Runs';
            } else if (lower.includes('wide')) {
                speechText = 'Wide Ball';
            } else if (lower.includes('no ball')) {
                speechText = 'No Ball';
            }

            try {
                window.speechSynthesis.cancel();
                const utterance = new SpeechSynthesisUtterance(speechText);
                utterance.rate = 1.05;
                utterance.pitch = 1.0;
                window.speechSynthesis.speak(utterance);
            } catch (err) {
                console.warn('SpeechSynthesis error:', err);
            }
        }
    }"
>
    @php
        // Merge match detail with liveInfo fallback
        $teamAName = $match['team_a'] ?? $liveInfo['team_a'] ?? 'Team A';
        $teamAShort = $match['team_a_short'] ?? $liveInfo['team_a_short'] ?? substr($teamAName, 0, 3);
        $teamAImg = $match['team_a_img'] ?? $liveInfo['team_a_img'] ?? '';
        $teamAScores = $liveInfo['team_a_scores'] ?? $match['team_a_scores'] ?? '';
        $teamAOver = $liveInfo['team_a_over'] ?? $match['team_a_over'] ?? '';

        $teamBName = $match['team_b'] ?? $liveInfo['team_b'] ?? 'Team B';
        $teamBShort = $match['team_b_short'] ?? $liveInfo['team_b_short'] ?? substr($teamBName, 0, 3);
        $teamBImg = $match['team_b_img'] ?? $liveInfo['team_b_img'] ?? '';
        $teamBScores = $liveInfo['team_b_scores'] ?? $match['team_b_scores'] ?? '';
        $teamBOver = $liveInfo['team_b_over'] ?? $match['team_b_over'] ?? '';

        $series = $match['series'] ?? $liveInfo['series'] ?? 'Cricket Series';
        $matchs = $match['matchs'] ?? $liveInfo['matchs'] ?? 'Match';
        $venue = $match['venue'] ?? $liveInfo['venue'] ?? 'Venue info';
        $place = $match['place'] ?? $liveInfo['place'] ?? '';
        $matchType = $match['match_type'] ?? $liveInfo['match_type'] ?? 'Cricket Match';
        $toss = $match['toss'] ?? $liveInfo['toss'] ?? '';
        $umpire = $match['umpire'] ?? $liveInfo['umpire'] ?? '';
        $thirdUmpire = $match['third_umpire'] ?? $liveInfo['third_umpire'] ?? '';
        $referee = $match['referee'] ?? $liveInfo['referee'] ?? '';
        $manOfMatch = $match['man_of_match_player'] ?? $match['man_of_match'] ?? '';
        $result = $match['result'] ?? $liveInfo['result'] ?? '';
        $needRunBall = $liveInfo['need_run_ball'] ?? $match['need_run_ball'] ?? '';
        $matchDate = $match['match_date'] ?? $liveInfo['match_date'] ?? 'Upcoming';
        $matchTime = $match['match_time'] ?? $liveInfo['match_time'] ?? '';
        $dateTimeRaw = $match['date_time'] ?? $liveInfo['date_time'] ?? null;

        // Calculate ISO Target Timestamp for upcoming countdown
        $targetIsoTime = null;
        if (!empty($dateTimeRaw)) {
            try {
                $targetIsoTime = \Carbon\Carbon::parse($dateTimeRaw)->toIso8601String();
            } catch (\Throwable $e) {}
        }
        if (!$targetIsoTime && !empty($matchDate)) {
            try {
                $targetIsoTime = \Carbon\Carbon::parse($matchDate . ' ' . ($matchTime ?: '12:00 PM'))->toIso8601String();
            } catch (\Throwable $e) {}
        }

        $rawStatus = $match['match_status'] ?? $liveInfo['match_status'] ?? 'Live';
        $isLive = is_string($rawStatus) ? strtolower($rawStatus) === 'live' : ($rawStatus == 2 || $rawStatus == 1);
        $isFinished = !empty($result) || (is_string($rawStatus) && strtolower($rawStatus) === 'finished');
        $isUpcoming = $isUpcoming ?? (!$isLive && !$isFinished);

        $tossText = strtolower(trim($match['toss'] ?? $match['toss_decision'] ?? $toss ?? ''));
        $isTossPending = empty($tossText) || str_contains($tossText, 'yet to take place') || str_contains($tossText, 'pending') || str_contains($tossText, 'not conducted');

        // Ball indicator for live mode
        $currentBall = $liveInfo['last_ball_event'] ?? $liveInfo['last_ball'] ?? '•';

        // Requirement metrics
        $runNeeded = $liveInfo['run_needed'] ?? $match['run_needed'] ?? '-';
        $ballsRem = $liveInfo['balls_rem'] ?? $match['balls_rem'] ?? '-';
        $rrr = $liveInfo['rrr'] ?? $match['rrr'] ?? '-';
        $target = $liveInfo['target'] ?? $match['target'] ?? '-';

        // Safe Market rates & Dynamic Session Parsing
        $favTeam = $liveInfo['fav_team'] ?? $teamAShort ?? 'Fav';
        $rateMin = $liveInfo['min_rate'] ?? $liveInfo['rate_min'] ?? '-';
        $rateMax = $liveInfo['max_rate'] ?? $liveInfo['rate_max'] ?? '-';

        // Check dynamic session/fancy data from live payload or home data
        $rawSession = $liveInfo['session'] ?? $match['session'] ?? null;
        $sessionOver = null;
        $sessionMin = null;
        $sessionMax = null;

        if (is_array($rawSession) && !empty($rawSession)) {
            $sessionOver = $rawSession['over'] ?? $rawSession['s_ovr'] ?? $rawSession['session_over'] ?? null;
            $sessionMin = $rawSession['min'] ?? $rawSession['s_min'] ?? $rawSession['min_rate'] ?? $rawSession['no'] ?? null;
            $sessionMax = $rawSession['max'] ?? $rawSession['s_max'] ?? $rawSession['max_rate'] ?? $rawSession['yes'] ?? null;
        } else {
            // Check flat keys
            $candOver = $liveInfo['s_ovr'] ?? $match['s_ovr'] ?? $liveInfo['session_over'] ?? null;
            $candMin = $liveInfo['s_min'] ?? $match['s_min'] ?? null;
            $candMax = $liveInfo['s_max'] ?? $match['s_max'] ?? null;

            if (!empty($candOver) && (string)$candOver !== '0' && ((!empty($candMin) && (string)$candMin !== '0') || (!empty($candMax) && (string)$candMax !== '0'))) {
                $sessionOver = $candOver;
                $sessionMin = $candMin;
                $sessionMax = $candMax;
            }
        }

        $hasValidSession = !empty($sessionOver) && (string)$sessionOver !== '0' && ((!empty($sessionMin) && (string)$sessionMin !== '0') || (!empty($sessionMax) && (string)$sessionMax !== '0'));

        // Weather
        $weather = $match['venue_weather'] ?? [];

        // Squads & Playing 11
        $playing11A = $match['team_a_playing_xi'] ?? $match['team_a_squad'] ?? [];
        $playing11B = $match['team_b_playing_xi'] ?? $match['team_b_squad'] ?? [];
        $hasPlaying11 = !empty($match['team_a_playing_xi']) || !empty($match['playing_11_announced']);

        // Derive active inning and live batters / bowler from scorecard
        $activeInningKey = null;
        $activeInningData = null;
        if (!empty($scorecardData) && is_array($scorecardData)) {
            $keys = array_keys($scorecardData);
            rsort($keys, SORT_NUMERIC);
            $activeInningKey = $keys[0] ?? null;
            $activeInningData = $scorecardData[$activeInningKey] ?? null;
        }

        // Active Inning details
        $currentInningNum = (int) ($liveInfo['current_inning'] ?? $activeInningKey ?? 1);
        $activeInningTeam = $activeInningData['team'] ?? [];
        $currInningScore = (int) ($activeInningTeam['score'] ?? 0);
        $currInningWkts = (int) ($activeInningTeam['wicket'] ?? 0);
        $currInningOverStr = (string) ($activeInningTeam['over'] ?? '0.0');

        // Target and Requirement Calculation
        $targetVal = null;
        $runsNeededVal = null;
        $ballsRemVal = null;
        $rrrVal = null;
        $crrVal = null;
        $projCurr = null;
        $proj8 = null;
        $proj10 = null;

        // Total max overs for format (e.g. 50 for ODI, 20 for T20)
        $matchTypeStr = strtoupper(trim((string)($match['match_type'] ?? $liveInfo['match_type'] ?? '')));
        $formatOvers = str_contains($matchTypeStr, 'T20') ? 20 : (str_contains($matchTypeStr, 'ODI') ? 50 : 50);
        $totalMaxBalls = $formatOvers * 6;

        // Parse balls bowled from current over string (e.g., "4.4" => 4*6 + 4 = 28)
        $overParts = explode('.', $currInningOverStr);
        $compOvers = (int)($overParts[0] ?? 0);
        $extraBalls = (int)($overParts[1] ?? 0);
        $ballsBowled = ($compOvers * 6) + $extraBalls;

        if ($ballsBowled > 0) {
            $crrVal = number_format(($currInningScore / $ballsBowled) * 6, 2);
        } else {
            $crrVal = '0.00';
        }

        if ($currentInningNum >= 2) {
            // Target from 1st inning or liveInfo
            if (!empty($liveInfo['target']) && is_numeric($liveInfo['target'])) {
                $targetVal = (int) $liveInfo['target'];
            } elseif (isset($scorecardData['1']['team']['score'])) {
                $targetVal = ((int) $scorecardData['1']['team']['score']) + 1;
            } elseif (!empty($liveInfo['team_a_score']['1']['score'])) {
                $targetVal = ((int) $liveInfo['team_a_score']['1']['score']) + 1;
            }

            // Fallback: parse target from need_run_ball string (e.g., "India NEED 115 RUNS IN 42.2 OVERS TO WIN")
            $needRunBallText = $needRunBall ?: ($liveInfo['need_run_ball'] ?? '');
            if (!$targetVal && preg_match('/NEED\s+(\d+)\s+RUNS/i', $needRunBallText, $mNeed)) {
                $runsNeededVal = (int) $mNeed[1];
                $targetVal = $currInningScore + $runsNeededVal;
            }

            if ($targetVal) {
                $runsNeededVal = max(0, $targetVal - $currInningScore);
                $ballsRemVal = max(0, $totalMaxBalls - $ballsBowled);
                if (preg_match('/IN\s+([\d\.]+)\s+OVERS/i', $needRunBallText, $mOv)) {
                    $ovParts = explode('.', $mOv[1]);
                    $ballsRemVal = (((int)($ovParts[0] ?? 0)) * 6) + ((int)($ovParts[1] ?? 0));
                }
                if ($ballsRemVal > 0) {
                    $rrrVal = number_format(($runsNeededVal / $ballsRemVal) * 6, 2);
                } else {
                    $rrrVal = '0.00';
                }
            }
        } else {
            // 1st Innings: Project scores
            $crrFloat = (float) $crrVal;
            $remBalls = max(0, $totalMaxBalls - $ballsBowled);
            $projCurr = round($currInningScore + ($crrFloat * ($remBalls / 6)));
            $proj8 = round($currInningScore + (8 * ($remBalls / 6)));
            $proj10 = round($currInningScore + (10 * ($remBalls / 6)));
        }

        // Live Batters (Striker and Non-Striker)
        $liveBatters = [];
        $liveBowlers = [];
        $lastWicket = null;

        if ($activeInningData) {
            $allBatsmen = $activeInningData['batsman'] ?? [];
            foreach ($allBatsmen as $b) {
                if (strtolower(trim($b['out_by'] ?? '')) === 'not out') {
                    $liveBatters[] = $b;
                }
            }
            if (empty($liveBatters) && !empty($allBatsmen)) {
                $liveBatters = array_slice($allBatsmen, -2);
            }

            // Last Wicket extraction
            $fallWickets = $activeInningData['fallwicket'] ?? [];
            if (!empty($fallWickets)) {
                $lastW = end($fallWickets);
                if (!empty($lastW['player'])) {
                    $wName = $lastW['player'];
                    // Find player stats in batsman array if available
                    $wRuns = null;
                    $wBalls = null;
                    foreach ($allBatsmen as $b) {
                        if (str_contains(strtolower($b['name'] ?? ''), strtolower($wName)) || str_contains(strtolower($wName), strtolower($b['name'] ?? ''))) {
                            $wRuns = $b['run'] ?? null;
                            $wBalls = $b['ball'] ?? null;
                            break;
                        }
                    }
                    $lastWicket = [
                        'name' => $wName,
                        'runs' => $wRuns,
                        'balls' => $wBalls,
                        'score' => $lastW['score'] ?? '',
                        'wicket' => $lastW['wicket'] ?? '',
                        'over' => $lastW['over'] ?? '',
                    ];
                }
            }

            // Bowlers: Pick only active bowler (1 or at most 2 for live line tab)
            $allBowlers = $activeInningData['bolwer'] ?? $activeInningData['bowler'] ?? [];
            $liveBowlers = $allBowlers;
        }

        // Parse commentary list
        $allCommentaryItems = [];
        if (!empty($commentaryData) && is_array($commentaryData)) {
            foreach ($commentaryData as $cInningName => $cOvers) {
                if (is_array($cOvers)) {
                    foreach ($cOvers as $cOverKey => $cBalls) {
                        if (is_array($cBalls)) {
                            foreach ($cBalls as $cBall) {
                                if (isset($cBall['data'])) {
                                    $allCommentaryItems[] = $cBall['data'];
                                } elseif (isset($cBall['overs']) || isset($cBall['title'])) {
                                    $allCommentaryItems[] = $cBall;
                                }
                            }
                        }
                    }
                }
            }
        }
    @endphp

    <!-- Top Sticky Header -->
    <header class="sticky top-0 z-40 bg-slate-900/95 backdrop-blur-md border-b border-slate-800/80 px-4 sm:px-6 lg:px-8 py-3 shadow-sm">
        <div class="max-w-7xl mx-auto flex items-center justify-between">
            <div class="flex items-center space-x-3 overflow-hidden">
                <a href="{{ url('/') }}" wire:navigate class="p-1.5 -ml-1.5 rounded-lg bg-slate-800/80 hover:bg-slate-800 text-slate-300 hover:text-white transition active:scale-95" aria-label="Go back">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                    </svg>
                </a>
                <div class="truncate">
                    <h1 class="text-sm sm:text-base font-bold text-white tracking-tight flex items-center gap-1.5 truncate">
                        <span>{{ $teamAShort }} vs {{ $teamBShort }}</span>
                        <span class="text-slate-500 font-normal">|</span>
                        <span class="text-xs sm:text-sm text-amber-400 font-semibold truncate">{{ $matchs }}</span>
                    </h1>
                    <p class="text-[10px] sm:text-xs text-slate-400 truncate">{{ $series }}</p>
                </div>
            </div>

            <!-- Action Buttons: Pin, Skip, Sound -->
            <div class="flex items-center gap-1.5 shrink-0">
                <!-- Pin Button -->
                <button 
                    type="button" 
                    @click="togglePin()" 
                    class="p-2 rounded-lg bg-slate-800/80 hover:bg-slate-800 text-slate-300 hover:text-white transition active:scale-95"
                    :class="{ 'text-amber-400 bg-amber-500/20 border border-amber-500/40': isPinned }"
                    :title="isPinned ? 'Unpin match' : 'Pin match to top'"
                    aria-label="Pin Match"
                >
                    <span x-show="!isPinned" class="text-sm leading-none">📌</span>
                    <span x-show="isPinned" class="text-sm leading-none">⭐</span>
                </button>

                <!-- Skip Button -->
                <button 
                    type="button" 
                    @click="skipMatch()" 
                    class="p-2 rounded-lg bg-slate-800/80 hover:bg-slate-800 text-slate-400 hover:text-rose-400 transition active:scale-95"
                    title="Skip this match"
                    aria-label="Skip Match"
                >
                    <span class="text-xs font-bold leading-none">✕</span>
                </button>

                <!-- Sound Toggle Button -->
                <button 
                    type="button" 
                    @click="soundEnabled = !soundEnabled" 
                    class="p-2 rounded-lg bg-slate-800/80 hover:bg-slate-800 text-slate-300 hover:text-white transition active:scale-95"
                    :title="soundEnabled ? 'Mute commentary' : 'Unmute commentary'"
                    aria-label="Sound Toggle"
                >
                    <span x-show="soundEnabled" class="text-sm leading-none">🔊</span>
                    <span x-show="!soundEnabled" class="text-sm leading-none opacity-60">🔇</span>
                </button>
            </div>
        </div>
    </header>

    <!-- Sub-Navbar / Breadcrumb Strip -->
    <nav class="border-b border-slate-800/80 bg-slate-900/40 backdrop-blur py-2 px-4 sm:px-6 lg:px-8">
        <div class="max-w-7xl mx-auto flex items-center justify-between text-xs text-slate-400">
            <div class="flex items-center space-x-2 truncate">
                <a href="/" wire:navigate class="hover:text-amber-400 transition shrink-0">Home</a>
                <span>/</span>
                <a href="{{ route('schedule') }}" wire:navigate class="hover:text-amber-400 transition truncate hidden sm:inline">{{ $series }}</a>
                <span class="hidden sm:inline">/</span>
                <span class="text-amber-400 font-semibold truncate">{{ $teamAShort }} vs {{ $teamBShort }} ({{ strtoupper($tab) }})</span>
            </div>
            <div class="flex items-center space-x-1.5 text-[11px] text-slate-400 bg-slate-900 px-2.5 py-1 rounded-lg border border-slate-800 shrink-0">
                <span class="w-2 h-2 rounded-full {{ $isLive ? 'bg-rose-500 animate-ping' : 'bg-emerald-400' }}"></span>
                <span class="font-mono">{{ $isLive ? 'LIVE FEED' : 'IST (UTC+5:30)' }}</span>
            </div>
        </div>
    </nav>

    <!-- Main Container (8-Column Match Center + 4-Column Sidebar on Desktop) -->
    <div class="max-w-7xl mx-auto w-full px-3 sm:px-6 lg:px-8 pt-4 pb-20 flex-1">
        <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 sm:gap-8 items-start">

            <!-- LEFT/CENTER COLUMN: Primary Match Hub (lg:col-span-8) -->
            <div class="lg:col-span-8 space-y-4">
                @if($isLive)
        <!-- ========================================== -->
        <!-- LIVE MATCH SCREEN (CRICKET LINE GURU STYLE)-->
        <!-- ========================================== -->
        <div class="p-3 space-y-3">
            <!-- 1. Top Matte Black Live Box -->
            <div class="bg-black rounded-2xl p-4 border border-slate-800 text-center relative shadow-2xl overflow-hidden">
                <!-- Sound Toggle on Top-Left -->
                <button 
                    type="button" 
                    @click="soundEnabled = !soundEnabled" 
                    class="absolute top-3 left-3 p-1.5 rounded-lg bg-slate-900 border border-slate-800 text-slate-300 hover:text-white transition active:scale-95"
                    aria-label="Toggle Commentary Sound"
                >
                    <span x-show="soundEnabled" class="text-xs">🔊</span>
                    <span x-show="!soundEnabled" class="text-xs opacity-50">🔇</span>
                </button>

                <!-- Live Badge on Top-Right -->
                <div class="absolute top-3 right-3 flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-rose-500/20 text-rose-400 border border-rose-500/30">
                    <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-ping"></span>
                    <span>Live</span>
                </div>

                <!-- Match Label -->
                <div class="text-[10px] uppercase font-bold tracking-widest text-slate-500 mt-1 mb-2">
                    {{ $matchs }} • {{ $series }}
                </div>

                <!-- Large Centered Ball Indicator -->
                <div class="py-2 flex items-center justify-center">
                    <div class="w-16 h-16 rounded-full bg-slate-900 border-2 border-amber-400/80 flex items-center justify-center shadow-[0_0_20px_rgba(245,158,11,0.25)]">
                        <span class="text-3xl font-black font-mono text-white tracking-tighter">
                            {{ $currentBall }}
                        </span>
                    </div>
                </div>

                <!-- Live Ball Outcome / Event Label -->
                <div class="text-xs font-bold text-amber-400 mt-1 mb-3 animate-pulse">
                    @if($isTossPending && $isUpcoming)
                        Toss Pending
                    @else
                        {{ $needRunBall ?: 'Ball in Progress' }}
                    @endif
                </div>

                <!-- Bottom Score Bar inside the box -->
                <div class="bg-slate-950/90 rounded-xl p-2.5 border border-slate-800/90 flex items-center justify-between text-xs font-mono">
                    <div class="text-left">
                        <span class="font-bold text-slate-200 block text-xs">{{ $teamAShort }}</span>
                        <span class="text-sm font-black text-white">{{ $teamAScores ?: 'Yet to Bat' }}</span>
                        @if($teamAOver)<span class="text-[10px] text-slate-400">({{ $teamAOver }})</span>@endif
                    </div>
                    <div class="text-slate-600 font-bold font-sans text-xs">VS</div>
                    <div class="text-right">
                        <span class="font-bold text-slate-200 block text-xs">{{ $teamBShort }}</span>
                        <span class="text-sm font-black text-rose-400">{{ $teamBScores ?: 'Yet to Bat' }}</span>
                        @if($teamBOver)<span class="text-[10px] text-slate-400">({{ $teamBOver }})</span>@endif
                    </div>
                </div>
            </div>

            <!-- 2. Match Requirement Grid (4-Column Clean Stat Bar) -->
            @if($currentInningNum >= 2 && $targetVal)
                <div class="grid grid-cols-4 gap-2 text-center bg-slate-900/90 border border-slate-800 rounded-xl p-2.5 shadow-md">
                    <div class="border-r border-slate-800/80 pr-1">
                        <span class="text-[9px] uppercase font-semibold text-slate-400 block">Run Needed</span>
                        <span class="font-mono font-black text-amber-400 text-xs">{{ $runsNeededVal ?? '-' }}</span>
                    </div>
                    <div class="border-r border-slate-800/80 pr-1">
                        <span class="text-[9px] uppercase font-semibold text-slate-400 block">Balls Rem</span>
                        <span class="font-mono font-black text-white text-xs">{{ $ballsRemVal ?? '-' }}</span>
                    </div>
                    <div class="border-r border-slate-800/80 pr-1">
                        <span class="text-[9px] uppercase font-semibold text-slate-400 block">R.R.R</span>
                        <span class="font-mono font-black text-cyan-400 text-xs">{{ $rrrVal ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="text-[9px] uppercase font-semibold text-slate-400 block">Target</span>
                        <span class="font-mono font-black text-emerald-400 text-xs">{{ $targetVal ?? '-' }}</span>
                    </div>
                </div>
            @else
                <div class="grid grid-cols-4 gap-2 text-center bg-slate-900/90 border border-slate-800 rounded-xl p-2.5 shadow-md">
                    <div class="border-r border-slate-800/80 pr-1">
                        <span class="text-[9px] uppercase font-semibold text-slate-400 block">C.R.R</span>
                        <span class="font-mono font-black text-amber-400 text-xs">{{ $crrVal ?? '0.00' }}</span>
                    </div>
                    <div class="border-r border-slate-800/80 pr-1">
                        <span class="text-[9px] uppercase font-semibold text-slate-400 block">Proj. Rate</span>
                        <span class="font-mono font-black text-white text-xs">{{ $projCurr ?? '-' }}</span>
                    </div>
                    <div class="border-r border-slate-800/80 pr-1">
                        <span class="text-[9px] uppercase font-semibold text-slate-400 block">Proj. @ 8</span>
                        <span class="font-mono font-black text-cyan-400 text-xs">{{ $proj8 ?? '-' }}</span>
                    </div>
                    <div>
                        <span class="text-[9px] uppercase font-semibold text-slate-400 block">Proj. @ 10</span>
                        <span class="font-mono font-black text-emerald-400 text-xs">{{ $proj10 ?? '-' }}</span>
                    </div>
                </div>
            @endif

            <!-- 3. Safe Market Rates & Session Cards (Review Safe Mode Guard) -->
            @if(config('app.show_market_rates', true) && \App\Helpers\SystemSetting::isMarketRatesEnabled())
                <div class="space-y-2">
                    <!-- Fav Team Row -->
                    @if($rateMin !== '-' && $rateMin !== 0 && (string)$rateMin !== '0')
                        <div class="bg-slate-900/90 border border-slate-800 rounded-xl p-3 flex items-center justify-between shadow-md">
                            <div class="flex items-center space-x-2">
                                <span class="text-xs font-bold text-slate-300">Fav Team:</span>
                                <span class="px-2 py-0.5 rounded bg-slate-800 text-amber-300 font-bold text-xs border border-amber-500/30">
                                    {{ $favTeam }}
                                </span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <div class="bg-rose-600 text-white font-mono font-black text-xs px-3 py-1.5 rounded-lg shadow-sm text-center min-w-[54px]">
                                    {{ $rateMin }}
                                </div>
                                <div class="bg-emerald-600 text-white font-mono font-black text-xs px-3 py-1.5 rounded-lg shadow-sm text-center min-w-[54px]">
                                    {{ $rateMax }}
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Dynamic Session Row (Lambi / Over runs) -->
                    @if($hasValidSession)
                        <div class="bg-slate-900/90 border border-slate-800 rounded-xl p-3 flex items-center justify-between shadow-md">
                            <div class="text-xs font-bold text-slate-300 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                                <span>{{ $sessionOver }} Over Runs</span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <div class="bg-rose-600 text-white font-mono font-black text-xs px-3 py-1.5 rounded-lg shadow-sm text-center min-w-[54px]" title="No / Under">
                                    {{ $sessionMin ?? '-' }}
                                </div>
                                <div class="bg-emerald-600 text-white font-mono font-black text-xs px-3 py-1.5 rounded-lg shadow-sm text-center min-w-[54px]" title="Yes / Over">
                                    {{ $sessionMax ?? '-' }}
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Session Summary Accordion (Innings 1 & 2 Table) -->
                    @include('livewire.pages.partials.session-summary-table')
                </div>
            @endif

            <!-- 4. Mini Batsmen & Bowler Figures -->
            <div class="space-y-3">
                <!-- Batters Table -->
                <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-3 shadow-md">
                    <div class="text-[11px] font-bold text-amber-400 uppercase tracking-wider mb-2 flex items-center justify-between">
                        <span>Batters on Crease</span>
                        <span class="text-[9px] font-normal text-slate-500">* On Strike</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="text-slate-400 border-b border-slate-800 text-[10px] uppercase">
                                    <th class="pb-1.5 font-medium">Batter</th>
                                    <th class="pb-1.5 font-medium text-right">R</th>
                                    <th class="pb-1.5 font-medium text-right">B</th>
                                    <th class="pb-1.5 font-medium text-right">4s</th>
                                    <th class="pb-1.5 font-medium text-right">6s</th>
                                    <th class="pb-1.5 font-medium text-right">SR</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/50">
                                @forelse(array_slice($liveBatters, 0, 2) as $idx => $batter)
                                    @php
                                        $isOnStrike = $idx === 0;
                                    @endphp
                                    <tr class="{{ $isOnStrike ? 'bg-amber-500/5' : '' }}">
                                        <td class="py-2 font-bold text-white flex items-center gap-1.5">
                                            <span>{{ $batter['name'] ?? 'Batter' }}</span>
                                            @if($isOnStrike)
                                                <span class="text-amber-400 text-xs font-black" title="On strike">*</span>
                                            @endif
                                        </td>
                                        <td class="py-2 text-right font-mono font-bold text-white">{{ $batter['run'] ?? 0 }}</td>
                                        <td class="py-2 text-right font-mono text-slate-400">{{ $batter['ball'] ?? 0 }}</td>
                                        <td class="py-2 text-right font-mono text-slate-300">{{ $batter['fours'] ?? 0 }}</td>
                                        <td class="py-2 text-right font-mono text-slate-300">{{ $batter['sixes'] ?? 0 }}</td>
                                        <td class="py-2 text-right font-mono text-amber-400">{{ $batter['strike_rate'] ?? '0.00' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="py-3 text-center text-slate-500 text-xs">No live batters currently on crease.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>

                    <!-- Last Wicket Row (Directly below Batters on Crease) -->
                    @if(!empty($lastWicket) && $currInningWkts > 0)
                        <div class="mt-2.5 pt-2 border-t border-slate-800/80 flex items-center justify-between text-[11px] bg-slate-950/70 -mx-1 px-2.5 py-1.5 rounded-lg border border-slate-800/60">
                            <div class="flex items-center gap-1.5 truncate">
                                <span class="text-rose-400 font-bold text-xs shrink-0">🔻 Last Wicket:</span>
                                <span class="text-white font-semibold truncate">{{ $lastWicket['name'] }}</span>
                                @if($lastWicket['runs'] !== null)
                                    <span class="font-mono text-slate-400"><strong>{{ $lastWicket['runs'] }}</strong>({{ $lastWicket['balls'] ?? 0 }})</span>
                                @endif
                            </div>
                            <div class="shrink-0 font-mono text-[10px] text-slate-400">
                                <span class="text-amber-300 font-bold">{{ $lastWicket['score'] }}/{{ $lastWicket['wicket'] }}</span>
                                <span>({{ $lastWicket['over'] }} ov)</span>
                            </div>
                        </div>
                    @endif
                </div>

                <!-- Bowler Table (Live Line: Only Active / Last Bowler) -->
                <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-3 shadow-md">
                    <div class="text-[11px] font-bold text-cyan-400 uppercase tracking-wider mb-2 flex items-center justify-between">
                        <span>Current Bowlers</span>
                        <span class="text-[9px] font-normal text-slate-500">● Bowling</span>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="text-slate-400 border-b border-slate-800 text-[10px] uppercase">
                                    <th class="pb-1.5 font-medium">Bowler</th>
                                    <th class="pb-1.5 font-medium text-right">O</th>
                                    <th class="pb-1.5 font-medium text-right">M</th>
                                    <th class="pb-1.5 font-medium text-right">R</th>
                                    <th class="pb-1.5 font-medium text-right">W</th>
                                    <th class="pb-1.5 font-medium text-right">ECO</th>
                                </tr>
                            </thead>
                            <tbody>
                                @forelse(array_slice($liveBowlers, 0, 2) as $bIdx => $bowler)
                                    <tr>
                                        <td class="py-2 font-bold text-white flex items-center gap-1.5">
                                            <span>{{ trim($bowler['name'] ?? 'Bowler') }}</span>
                                            @if($bIdx === 0)
                                                <span class="w-1.5 h-1.5 rounded-full bg-cyan-400" title="Current bowler"></span>
                                            @endif
                                        </td>
                                        <td class="py-2 text-right font-mono text-slate-300">{{ $bowler['over'] ?? 0 }}</td>
                                        <td class="py-2 text-right font-mono text-slate-400">{{ $bowler['maiden'] ?? 0 }}</td>
                                        <td class="py-2 text-right font-mono font-bold text-white">{{ $bowler['run'] ?? 0 }}</td>
                                        <td class="py-2 text-right font-mono font-bold text-rose-400">{{ $bowler['wicket'] ?? 0 }}</td>
                                        <td class="py-2 text-right font-mono text-cyan-300">{{ $bowler['economy'] ?? '0.00' }}</td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="6" class="py-3 text-center text-slate-500 text-xs">No active bowling figures.</td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

    @elseif($isUpcoming)
        <!-- ========================================== -->
        <!-- UPCOMING FIXTURE SCREEN                   -->
        <!-- ========================================== -->
        <div class="p-3 space-y-4">
            <!-- Reference Style Match Header & Real-Time Countdown Timer -->
            <div class="space-y-1.5 pb-4 border-b border-slate-800">
                <div class="flex items-center justify-between gap-2 flex-wrap">
                    <h1 class="text-base sm:text-lg font-black text-white">
                        {{ $match['team_a'] ?? $teamAName ?? 'Team A' }} vs {{ $match['team_b'] ?? $teamBName ?? 'Team B' }}, {{ $match['match_stage'] ?? $matchs ?? 'Match' }}
                    </h1>
                    @if($isTossPending && $isUpcoming)
                        <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full bg-amber-500/10 border border-amber-500/30 text-amber-400 text-xs font-black uppercase tracking-wider">
                            <span class="w-2 h-2 rounded-full bg-amber-400 animate-ping"></span>
                            <span>🪙 Toss Pending</span>
                        </div>
                    @elseif(!$isUpcoming && in_array($match['status'] ?? $match['match_status'] ?? '', ['live', 'in_progress', 1, 2]))
                        <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-black uppercase tracking-wider">
                            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                            <span>● Ball In Progress</span>
                        </div>
                    @endif
                </div>

                <div class="flex flex-wrap items-center gap-2 text-xs text-slate-400">
                    <span>Date & Time: <strong class="text-slate-200">{{ $match['match_date'] ?? $matchDate ?? 'Wed, 11 Nov 2026' }} ● {{ $match['match_time'] ?? $matchTime ?? '01:40 PM' }}</strong></span>
                    <span>●</span>
                    <span class="text-sky-400 underline">{{ $match['venue'] ?? $venue ?? 'Stadium, City' }}</span>
                </div>

                <!-- Live Alpine Countdown Timer -->
                @if($isUpcoming && !empty($matchStartTime))
                    <div x-data="{
                            target: new Date('{{ $matchStartTime }}').getTime(),
                            timerStr: '',
                            expired: false,
                            update() {
                                const now = new Date().getTime();
                                const diff = this.target - now;
                                if (diff <= 0) {
                                    this.expired = true;
                                    this.timerStr = 'Match starting shortly...';
                                    return;
                                }
                                const h = Math.floor(diff / (1000 * 60 * 60));
                                const m = Math.floor((diff % (1000 * 60 * 60)) / (1000 * 60));
                                const s = Math.floor((diff % (1000 * 60)) / 1000);
                                this.timerStr = `${h}h : ${m}m : ${s}s`;
                            }
                         }" 
                         x-init="update(); setInterval(() => update(), 1000)" 
                         class="pt-2 flex items-center space-x-2 text-sm font-black">
                        <span class="text-amber-400 font-mono tracking-wider text-base" x-text="timerStr"></span>
                        <span class="text-[11px] text-slate-500 font-medium">Local Countdown</span>
                    </div>
                @endif
            </div>

            <!-- Teams Graphic Card -->
            <div class="bg-gradient-to-b from-slate-900 to-slate-950 border border-slate-800 rounded-2xl p-4 text-center shadow-lg">
                <div class="flex items-center justify-between text-xs mb-3">
                    <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-slate-800 text-amber-300 border border-amber-500/20">
                        Upcoming Fixture
                    </span>
                    <span class="text-[11px] font-semibold text-slate-400 truncate max-w-[200px]">
                        {{ $matchs }}
                    </span>
                </div>

                <!-- Teams Graphic -->
                <div class="grid grid-cols-[1fr_auto_1fr] items-center gap-3 py-2">
                    <div class="text-center">
                        @if(!empty($teamAImg))
                            <img src="{{ $teamAImg }}" alt="{{ $teamAName }}" class="w-12 h-12 rounded-full object-cover bg-slate-800 border border-white/10 mx-auto mb-1.5 shadow-sm" onerror="this.style.display='none'">
                        @else
                            <div class="w-12 h-12 rounded-full bg-slate-800 border border-white/10 text-sm font-black text-amber-400 flex items-center justify-center mx-auto mb-1.5">
                                {{ substr($teamAShort, 0, 2) }}
                            </div>
                        @endif
                        <h3 class="font-extrabold text-white text-sm">{{ $teamAName }}</h3>
                    </div>

                    <div class="text-center px-2">
                        <span class="w-8 h-8 rounded-full bg-slate-800 border border-slate-700 text-xs font-black text-amber-400 flex items-center justify-center shadow-inner">VS</span>
                    </div>

                    <div class="text-center">
                        @if(!empty($teamBImg))
                            <img src="{{ $teamBImg }}" alt="{{ $teamBName }}" class="w-12 h-12 rounded-full object-cover bg-slate-800 border border-white/10 mx-auto mb-1.5 shadow-sm" onerror="this.style.display='none'">
                        @else
                            <div class="w-12 h-12 rounded-full bg-slate-800 border border-white/10 text-sm font-black text-amber-400 flex items-center justify-center mx-auto mb-1.5">
                                {{ substr($teamBShort, 0, 2) }}
                            </div>
                        @endif
                        <h3 class="font-extrabold text-white text-sm">{{ $teamBName }}</h3>
                    </div>
                </div>

                <div class="mt-3 pt-3 border-t border-slate-800/80 flex items-center justify-center gap-2 text-xs font-medium text-slate-300">
                    <span>📅 {{ $matchDate }}</span>
                    <span>•</span>
                    <span class="text-amber-400 font-bold">⏰ {{ $matchTime ?: 'Scheduled' }}</span>
                </div>
            </div>

            <!-- 2. Venue & Weather Widget -->
            <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-3.5 shadow-md space-y-2">
                <div class="text-[11px] font-bold text-amber-400 uppercase tracking-wider pb-1.5 border-b border-slate-800/80 flex items-center justify-between">
                    <span>🏟️ Venue & Pitch Report</span>
                    <span class="text-[10px] text-slate-400 font-normal">{{ $venue }}</span>
                </div>

                <div class="grid grid-cols-2 gap-2 text-xs pt-1">
                    <div class="bg-slate-950 p-2.5 rounded-xl border border-slate-800">
                        <span class="text-[10px] text-slate-400 block mb-0.5">Pitch Condition</span>
                        <span class="font-bold text-white text-xs">Batting Friendly (High Bounce)</span>
                    </div>
                    <div class="bg-slate-950 p-2.5 rounded-xl border border-slate-800">
                        <span class="text-[10px] text-slate-400 block mb-0.5">Weather & Rain</span>
                        <span class="font-bold text-cyan-300 text-xs">
                            {{ $weather['temp_c'] ?? '28' }}°C • {{ $weather['weather'] ?? 'Clear (0% Rain)' }}
                        </span>
                    </div>
                </div>
            </div>

            <!-- 3. Squads & Playing 11 Section -->
            <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-3.5 shadow-md space-y-3">
                <div class="flex items-center justify-between pb-2 border-b border-slate-800">
                    <h3 class="text-xs font-bold text-white uppercase tracking-wider flex items-center gap-1.5">
                        <span>👥</span> Team Lineups & Squads
                    </h3>

                    @if($hasPlaying11)
                        <span class="px-2 py-0.5 rounded text-[10px] font-bold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                            Official Playing 11
                        </span>
                    @else
                        <span class="px-2 py-0.5 rounded text-[9px] font-bold bg-amber-500/20 text-amber-300 border border-amber-500/30">
                            ⚠️ Playing 11 Not Announced Yet
                        </span>
                    @endif
                </div>

                <div class="grid grid-cols-2 gap-3 text-xs">
                    <!-- Team A Squad -->
                    <div>
                        <div class="font-bold text-amber-400 text-[11px] mb-1.5">{{ $teamAShort }} Squad</div>
                        @if(!empty($playing11A))
                            <ul class="space-y-1 text-slate-300 text-[11px]">
                                @foreach($playing11A as $idx => $player)
                                    <li>{{ $loop->iteration }}. {{ is_array($player) ? ($player['name'] ?? 'Player') : $player }}</li>
                                @endforeach
                            </ul>
                        @else
                            <div class="text-[11px] text-slate-500 italic">Squad not yet announced.</div>
                        @endif
                    </div>

                    <!-- Team B Squad -->
                    <div>
                        <div class="font-bold text-cyan-400 text-[11px] mb-1.5">{{ $teamBShort }} Squad</div>
                        @if(!empty($playing11B))
                            <ul class="space-y-1 text-slate-300 text-[11px]">
                                @foreach($playing11B as $idx => $player)
                                    <li>{{ $loop->iteration }}. {{ is_array($player) ? ($player['name'] ?? 'Player') : $player }}</li>
                                @endforeach
                            </ul>
                        @else
                            <div class="text-[11px] text-slate-500 italic">Squad not yet announced.</div>
                        @endif
                    </div>
                </div>
            </div>

            <!-- 4. Interactive Match Polls (3 Pre-configured Polls) -->
            <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-3.5 shadow-md space-y-3">
                <div class="flex items-center justify-between pb-2 border-b border-slate-800">
                    <h3 class="text-xs font-bold text-amber-400 uppercase tracking-wider flex items-center gap-1.5">
                        <span>🗳️</span> Match Prediction Polls
                    </h3>
                    <span class="text-[10px] text-slate-400">1-Click Voting</span>
                </div>

                <div class="space-y-3">
                    @foreach($polls as $poll)
                        @php
                            $totalVotes = ($poll->votes_a ?? 0) + ($poll->votes_b ?? 0) + ($poll->votes_c ?? 0);
                            $pctA = $totalVotes > 0 ? round((($poll->votes_a ?? 0) / $totalVotes) * 100) : 33;
                            $pctB = $totalVotes > 0 ? round((($poll->votes_b ?? 0) / $totalVotes) * 100) : 33;
                            $pctC = $totalVotes > 0 ? round((($poll->votes_c ?? 0) / $totalVotes) * 100) : 34;
                        @endphp
                        <div class="bg-slate-950 p-3 rounded-xl border border-slate-800/90">
                            <h4 class="text-xs font-bold text-white mb-2">{{ $poll->question }}</h4>

                            <div class="space-y-1.5">
                                <!-- Option A -->
                                <button 
                                    type="button" 
                                    @click="castVote({{ $poll->id }}, 'option_a')" 
                                    class="w-full text-left p-2 rounded-lg border text-xs font-semibold relative overflow-hidden transition"
                                    :class="userVotes[{{ $poll->id }}] === 'option_a' ? 'border-amber-400 bg-amber-500/10' : 'border-slate-800 bg-slate-900/80 hover:border-slate-700'"
                                >
                                    <div class="absolute inset-0 bg-amber-500/15 pointer-events-none" style="width: {{ $pctA }}%"></div>
                                    <div class="relative flex justify-between items-center z-10">
                                        <span>{{ $poll->option_a }}</span>
                                        <span class="font-mono text-[11px] font-bold text-amber-300">{{ $pctA }}%</span>
                                    </div>
                                </button>

                                <!-- Option B -->
                                <button 
                                    type="button" 
                                    @click="castVote({{ $poll->id }}, 'option_b')" 
                                    class="w-full text-left p-2 rounded-lg border text-xs font-semibold relative overflow-hidden transition"
                                    :class="userVotes[{{ $poll->id }}] === 'option_b' ? 'border-cyan-400 bg-cyan-500/10' : 'border-slate-800 bg-slate-900/80 hover:border-slate-700'"
                                >
                                    <div class="absolute inset-0 bg-cyan-500/15 pointer-events-none" style="width: {{ $pctB }}%"></div>
                                    <div class="relative flex justify-between items-center z-10">
                                        <span>{{ $poll->option_b }}</span>
                                        <span class="font-mono text-[11px] font-bold text-cyan-300">{{ $pctB }}%</span>
                                    </div>
                                </button>

                                <!-- Option C (if exists) -->
                                @if(!empty($poll->option_c))
                                    <button 
                                        type="button" 
                                        @click="castVote({{ $poll->id }}, 'option_c')" 
                                        class="w-full text-left p-2 rounded-lg border text-xs font-semibold relative overflow-hidden transition"
                                        :class="userVotes[{{ $poll->id }}] === 'option_c' ? 'border-rose-400 bg-rose-500/10' : 'border-slate-800 bg-slate-900/80 hover:border-slate-700'"
                                    >
                                        <div class="absolute inset-0 bg-rose-500/15 pointer-events-none" style="width: {{ $pctC }}%"></div>
                                        <div class="relative flex justify-between items-center z-10">
                                            <span>{{ $poll->option_c }}</span>
                                            <span class="font-mono text-[11px] font-bold text-rose-300">{{ $pctC }}%</span>
                                        </div>
                                    </button>
                                @endif
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>

            <!-- 5. Featured News & Editorials Section -->
            @if(isset($featuredArticles) && $featuredArticles->count() > 0)
                <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-3.5 shadow-md space-y-2.5">
                    <div class="flex items-center justify-between pb-1.5 border-b border-slate-800">
                        <h3 class="text-xs font-bold text-amber-400 uppercase tracking-wider flex items-center gap-1.5">
                            <span>📰</span> Featured Editorials
                        </h3>
                        <a href="{{ route('news.index') }}" wire:navigate class="text-[10px] text-slate-400 hover:text-white transition">View All →</a>
                    </div>

                    <div class="space-y-2">
                        @foreach($featuredArticles as $article)
                            <a href="{{ route('news.show', ['article' => $article->slug]) }}" wire:navigate class="flex items-center space-x-3 p-2 rounded-xl bg-slate-950 border border-slate-800 hover:border-amber-500/40 transition group">
                                @if($article->resolved_image_url)
                                    <img src="{{ $article->resolved_image_url }}" alt="{{ $article->title }}" class="w-12 h-12 rounded-lg object-cover bg-slate-800 shrink-0">
                                @endif
                                <div class="overflow-hidden">
                                    <h4 class="text-xs font-bold text-slate-200 group-hover:text-amber-300 transition-colors line-clamp-2">
                                        {{ $article->title }}
                                    </h4>
                                    <span class="text-[10px] text-slate-500 mt-0.5 block">
                                        {{ $article->published_at ? $article->published_at->diffForHumans() : 'Recent' }}
                                    </span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                </div>
            @endif

            <!-- 6. In-Feed Banner Ad Space (Zero Whitespace Fallback) -->
            <x-ad-banner placement="upcoming_match" />
        </div>

    @else
        <!-- ========================================== -->
        <!-- FINISHED MATCH SUMMARY / STANDARD VIEW    -->
        <!-- ========================================== -->
        <section class="p-4 bg-gradient-to-b from-slate-900 via-slate-900/90 to-slate-950 border-b border-slate-800">
            <div class="flex items-center justify-between text-xs mb-3">
                <span class="text-[11px] font-semibold text-slate-400 truncate max-w-[220px]">
                    {{ $venue }}
                </span>
                <span class="px-2 py-0.5 rounded-md text-[10px] font-semibold bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                    Finished
                </span>
            </div>

            <div class="grid grid-cols-2 gap-3 items-center">
                <div class="flex items-center space-x-2.5">
                    <div class="truncate">
                        <div class="font-bold text-sm text-white truncate">{{ $teamAShort }}</div>
                        <div class="text-sm font-black text-white font-mono tracking-tight">{{ $teamAScores ?: '-' }}</div>
                    </div>
                </div>
                <div class="flex items-center justify-end space-x-2.5 text-right">
                    <div class="truncate">
                        <div class="font-bold text-sm text-white truncate">{{ $teamBShort }}</div>
                        <div class="text-sm font-black text-white font-mono tracking-tight">{{ $teamBScores ?: '-' }}</div>
                    </div>
                </div>
            </div>

            <div class="mt-3 pt-2.5 border-t border-slate-800/80 text-center">
                <p class="text-xs font-bold text-emerald-400">
                    {{ $result ?: 'Match completed' }}
                </p>
            </div>
        </section>
    @endif

    <!-- Sub-Tabs Navigation Bar (Live Line, Commentary, Scorecard, Squad, Odds, Info) -->
    <div class="sticky top-[53px] z-30 bg-slate-950/95 backdrop-blur-md border-b border-slate-800">
        <div class="flex items-center space-x-2 border-b border-slate-800 bg-slate-900/60 p-1.5 rounded-2xl overflow-x-auto no-scrollbar">
            @php
                $tabDefinitions = [
                    'live-line' => 'Live Line',
                    'commentary' => 'Commentary',
                    'scorecard' => 'Scorecard',
                    'squad' => 'Squad',
                ];
                if (config('app.show_market_rates', true) && \App\Helpers\SystemSetting::isMarketRatesEnabled()) {
                    $tabDefinitions['odds'] = 'Odds';
                }
                $tabDefinitions['info'] = 'Info';
            @endphp
            @foreach($tabDefinitions as $tKey => $tLabel)
                <button wire:click="setTab('{{ $tKey }}')" 
                        class="shrink-0 px-4 py-2 rounded-xl text-xs sm:text-sm font-bold uppercase transition {{ $tab === $tKey ? 'bg-amber-400 text-slate-950 shadow-md shadow-amber-400/20' : 'text-slate-400 hover:text-white hover:bg-slate-800/60' }}">
                    {{ $tLabel }}
                </button>
            @endforeach
        </div>
    </div>

    <!-- Tab Content -->
    <main class="flex-1 p-3.5 space-y-3.5">
        @if($tab === 'live-line')
            <!-- Ball-by-ball Over Bubbles -->
            <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-3 shadow-md">
                <div class="flex items-center justify-between text-[11px] font-semibold text-slate-400 mb-2">
                    <span>Recent Over: <strong class="text-white font-bold">This Over</strong></span>
                    <span class="text-slate-500 font-mono">Ball-by-Ball Live</span>
                </div>
                <div class="flex items-center gap-2 overflow-x-auto py-1">
                    @php
                        $recentBalls = array_slice($allCommentaryItems, 0, 8);
                    @endphp
                    @forelse($recentBalls as $cBall)
                        @php
                            $runs = (string) ($cBall['runs'] ?? '0');
                            $isWkt = !empty($cBall['wicket']) && $cBall['wicket'] != '0';
                            $isWide = !empty($cBall['wides']) && $cBall['wides'] != '0';
                            $isNoBall = !empty($cBall['noballs']) && $cBall['noballs'] != '0';
                            
                            $label = $runs;
                            $bgClass = 'bg-slate-800/80 border-slate-700/80 text-slate-200';
                            $textClass = 'text-xs';
                            
                            if ($isWkt) {
                                $label = 'W';
                                $bgClass = 'bg-rose-600 border-rose-500 text-white animate-pulse shadow-sm shadow-rose-600/50';
                            } elseif ($isWide) {
                                $label = 'wd';
                                $bgClass = 'bg-cyan-500/20 border-cyan-400 text-cyan-300';
                                $textClass = 'text-[10px] lowercase';
                            } elseif ($isNoBall) {
                                $label = 'nb';
                                $bgClass = 'bg-cyan-500/20 border-cyan-400 text-cyan-300';
                                $textClass = 'text-[10px] lowercase';
                            } elseif ($runs === '4') {
                                $bgClass = 'bg-emerald-500/20 border-emerald-400 text-emerald-300 shadow-sm shadow-emerald-500/20';
                            } elseif ($runs === '6') {
                                $bgClass = 'bg-amber-500/20 border-amber-400 text-amber-300 shadow-sm shadow-amber-500/30';
                            } elseif ($runs === '0') {
                                $label = '0';
                                $bgClass = 'bg-slate-900 border-slate-700/60 text-slate-400';
                            }
                        @endphp
                        <span class="w-8 h-8 rounded-full border font-mono font-bold flex items-center justify-center shrink-0 shadow-sm {{ $textClass }} {{ $bgClass }}" title="{{ $cBall['overs'] ?? '' }} ov">
                            {{ $label }}
                        </span>
                    @empty
                        <span class="w-8 h-8 rounded-full bg-slate-900 border border-slate-700/60 text-slate-300 font-mono font-bold text-xs flex items-center justify-center shrink-0 shadow-sm">
                            {{ $currentBall }}
                        </span>
                    @endforelse
                </div>
            </div>

        @elseif($tab === 'scorecard')
            <!-- SCORECARD TAB -->
            <div class="space-y-4">
                @forelse($scorecardData as $inKey => $inning)
                    @php
                        $inTeam = $inning['team'] ?? [];
                        $inTeamName = $inTeam['name'] ?? $inTeam['short_name'] ?? "Inning {$inKey}";
                        $inScore = $inTeam['score'] ?? 0;
                        $inWkts = $inTeam['wicket'] ?? 0;
                        $inOver = $inTeam['over'] ?? '0.0';
                        $inBatsmen = $inning['batsman'] ?? [];
                        $inBowlers = $inning['bolwer'] ?? $inning['bowler'] ?? [];
                    @endphp
                    <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-3.5 shadow-md">
                        <div class="flex items-center justify-between pb-2 border-b border-slate-800 mb-2">
                            <h3 class="text-xs font-black text-white uppercase tracking-wide flex items-center gap-1.5">
                                <span>{{ $inTeamName }}</span>
                                <span class="text-slate-500">•</span>
                                <span class="text-amber-400 font-mono">{{ $inScore }}/{{ $inWkts }}</span>
                            </h3>
                            <span class="text-[11px] text-slate-400 font-mono font-medium">{{ $inOver }} Overs</span>
                        </div>

                        <!-- Batsmen Table -->
                        <div class="overflow-x-auto mb-3">
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="text-slate-400 border-b border-slate-800/80 text-[10px] uppercase">
                                        <th class="pb-1.5">Batter</th>
                                        <th class="pb-1.5 text-right">R</th>
                                        <th class="pb-1.5 text-right">B</th>
                                        <th class="pb-1.5 text-right">4s</th>
                                        <th class="pb-1.5 text-right">6s</th>
                                        <th class="pb-1.5 text-right">SR</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800/40 font-mono">
                                    @forelse($inBatsmen as $b)
                                        <tr>
                                            <td class="py-1.5 font-sans font-medium text-slate-200">
                                                <div>{{ $b['name'] ?? 'Batter' }}</div>
                                                <div class="text-[9px] text-slate-500 font-normal">{{ $b['out_by'] ?? '' }}</div>
                                            </td>
                                            <td class="py-1.5 text-right font-bold text-white">{{ $b['run'] ?? 0 }}</td>
                                            <td class="py-1.5 text-right text-slate-400">{{ $b['ball'] ?? 0 }}</td>
                                            <td class="py-1.5 text-right text-slate-300">{{ $b['fours'] ?? 0 }}</td>
                                            <td class="py-1.5 text-right text-slate-300">{{ $b['sixes'] ?? 0 }}</td>
                                            <td class="py-1.5 text-right text-slate-400">{{ $b['strike_rate'] ?? '0.00' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="py-2 text-center text-slate-500 text-xs">No batting data available.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Bowlers Table -->
                        @if(!empty($inBowlers))
                            <div class="pt-2 border-t border-slate-800/70 overflow-x-auto">
                                <div class="text-[10px] font-bold text-cyan-400 uppercase tracking-wider mb-1.5">Bowling</div>
                                <table class="w-full text-left text-xs">
                                    <thead>
                                        <tr class="text-slate-400 border-b border-slate-800/80 text-[10px] uppercase">
                                            <th class="pb-1.5 font-medium">Bowler</th>
                                            <th class="pb-1.5 font-medium text-right">O</th>
                                            <th class="pb-1.5 font-medium text-right">M</th>
                                            <th class="pb-1.5 font-medium text-right">R</th>
                                            <th class="pb-1.5 font-medium text-right">W</th>
                                            <th class="pb-1.5 font-medium text-right">ECO</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-800/40 font-mono">
                                        @foreach($inBowlers as $bowl)
                                            <tr>
                                                <td class="py-1.5 font-sans font-medium text-slate-200">{{ trim($bowl['name'] ?? 'Bowler') }}</td>
                                                <td class="py-1.5 text-right text-slate-300">{{ $bowl['over'] ?? 0 }}</td>
                                                <td class="py-1.5 text-right text-slate-400">{{ $bowl['maiden'] ?? 0 }}</td>
                                                <td class="py-1.5 text-right font-bold text-white">{{ $bowl['run'] ?? 0 }}</td>
                                                <td class="py-1.5 text-right font-bold text-rose-400">{{ $bowl['wicket'] ?? 0 }}</td>
                                                <td class="py-1.5 text-right text-cyan-300">{{ $bowl['economy'] ?? '0.00' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif
                    </div>
                @empty
                    <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-6 text-center text-slate-400 text-xs">
                        Scorecard is not available yet for this match.
                    </div>
                @endforelse
            </div>

        @elseif($tab === 'commentary')
            <!-- COMMENTARY TAB -->
            <div class="space-y-3">
                <!-- Full Squad in Commentary Tab (Text-Only Names, No Table / No Photos) -->
                <div class="bg-slate-900/60 border border-slate-800 rounded-2xl p-4 mb-4 text-xs">
                    <div class="flex items-center justify-between pb-2 mb-2 border-b border-slate-800 text-slate-400 font-black uppercase text-[11px]">
                        <span>Squads</span>
                        <span class="text-[10px] font-normal text-slate-500">Playing Roster</span>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3 text-slate-300">
                        <!-- Team A Text List -->
                        <div>
                            <strong class="text-amber-400 block mb-1 font-bold">{{ $match['team_a'] ?? $teamAName ?? 'Team A' }}:</strong>
                            <p class="leading-relaxed text-slate-300">
                                {{ collect($teamASquad ?? [])->map(fn($p) => is_array($p) ? ($p['name'] ?? null) : $p)->filter()->join(', ') ?: 'Squad announcement awaited' }}
                            </p>
                        </div>

                        <!-- Team B Text List -->
                        <div>
                            <strong class="text-cyan-400 block mb-1 font-bold">{{ $match['team_b'] ?? $teamBName ?? 'Team B' }}:</strong>
                            <p class="leading-relaxed text-slate-300">
                                {{ collect($teamBSquad ?? [])->map(fn($p) => is_array($p) ? ($p['name'] ?? null) : $p)->filter()->join(', ') ?: 'Squad announcement awaited' }}
                            </p>
                        </div>
                    </div>
                </div>

                <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-3.5 shadow-md">
                    <div class="flex items-center justify-between pb-2.5 border-b border-slate-800 mb-2.5">
                        <h3 class="text-xs font-bold text-amber-400 uppercase tracking-wider flex items-center gap-1.5">
                            <span>🎙️</span>
                            <span>Live Ball-by-Ball Commentary</span>
                        </h3>
                    </div>

                    <div class="space-y-2.5">
                        @forelse(array_slice($allCommentaryItems, 0, 30) as $comm)
                            <div class="p-2.5 rounded-xl bg-slate-950/80 border border-slate-800/80">
                                <div class="flex items-center justify-between text-xs mb-1">
                                    <span class="px-2 py-0.5 rounded bg-amber-500/20 text-amber-300 font-mono font-bold text-[11px]">
                                        Over {{ $comm['overs'] ?? '•' }}
                                    </span>
                                    @if(!empty($comm['wicket']) && $comm['wicket'] != '0')
                                        <span class="text-rose-400 font-bold text-[11px]">WICKET!</span>
                                    @elseif(isset($comm['runs']) && $comm['runs'] == '6')
                                        <span class="text-emerald-400 font-bold text-[11px]">SIX!</span>
                                    @elseif(isset($comm['runs']) && $comm['runs'] == '4')
                                        <span class="text-blue-400 font-bold text-[11px]">FOUR!</span>
                                    @endif
                                </div>
                                @if(!empty($comm['title']))
                                    <div class="text-xs font-semibold text-white mb-0.5">{{ $comm['title'] }}</div>
                                @endif
                                @if(!empty($comm['description']))
                                    <p class="text-xs text-slate-300 leading-relaxed">{{ $comm['description'] }}</p>
                                @endif
                            </div>
                        @empty
                            <div class="py-6 text-center text-slate-500 text-xs">
                                Commentary is not yet available for this match.
                            </div>
                        @endforelse
                    </div>
                </div>
            </div>

        @elseif($tab === 'squad')
            <!-- SQUAD TAB -->
            <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4 sm:p-6 shadow-xl space-y-6">
                
                <!-- Team Header Labels -->
                <div class="grid grid-cols-2 gap-4 pb-3 border-b border-slate-800 text-xs sm:text-sm font-black uppercase tracking-wider">
                    <div class="flex items-center space-x-2 text-amber-400">
                        <img src="{{ $match['team_a_img'] ?? $match['team_a_flag'] ?? asset('images/default-team.png') }}" class="w-6 h-6 rounded-full object-cover" onerror="this.src='{{ asset('images/default-team.png') }}'" />
                        <span>{{ $match['team_a'] ?? $teamAName ?? 'Team A' }}</span>
                    </div>
                    <div class="flex items-center justify-end space-x-2 text-cyan-400 text-right">
                        <span>{{ $match['team_b'] ?? $teamBName ?? 'Team B' }}</span>
                        <img src="{{ $match['team_b_img'] ?? $match['team_b_flag'] ?? asset('images/default-team.png') }}" class="w-6 h-6 rounded-full object-cover" onerror="this.src='{{ asset('images/default-team.png') }}'" />
                    </div>
                </div>

                <div class="text-center text-[11px] font-bold text-slate-500 uppercase tracking-widest -my-2">
                    Squads
                </div>

                <!-- Comparative Players Grid -->
                <div class="grid grid-cols-2 gap-4 sm:gap-6 divide-x divide-slate-800/60 text-xs">
                    
                    <!-- Team A Squad List -->
                    <div class="space-y-3.5 pr-2 sm:pr-4">
                        @forelse($teamASquad ?? [] as $player)
                            <div class="flex items-center space-x-3 group">
                                <div class="w-10 h-10 rounded-full overflow-hidden bg-slate-950 border border-slate-800 shrink-0 shadow">
                                    <img src="{{ is_array($player) ? ($player['image'] ?? $player['photo'] ?? asset('images/default-avatar.png')) : asset('images/default-avatar.png') }}" 
                                         alt="{{ is_array($player) ? ($player['name'] ?? 'Player') : $player }}" 
                                         class="w-full h-full object-cover" 
                                         onerror="this.src='{{ asset('images/default-avatar.png') }}'">
                                </div>
                                <div class="min-w-0">
                                    <div class="flex items-center space-x-1.5 flex-wrap">
                                        <span class="font-bold text-white text-xs group-hover:text-amber-400 transition truncate">
                                            {{ is_array($player) ? ($player['name'] ?? 'Player Name') : $player }}
                                        </span>
                                        @if(is_array($player) && (!empty($player['is_captain']) || str_contains($player['role_desc'] ?? '', '(C)')))
                                            <span class="text-[10px] text-amber-400 font-black">(C)</span>
                                        @endif
                                        @if(is_array($player) && (!empty($player['is_keeper']) || str_contains($player['role_desc'] ?? '', '(WK)')))
                                            <span class="text-[10px] text-sky-400 font-black">(WK)</span>
                                        @endif
                                        @if(is_array($player) && !empty($player['is_overseas']))
                                            <span class="text-[10px]" title="Overseas Player">✈</span>
                                        @endif
                                    </div>
                                    <span class="text-[11px] text-slate-400 block truncate">
                                        {{ is_array($player) ? ($player['role'] ?? $player['playing_role'] ?? 'Player') : 'Player' }}
                                    </span>
                                </div>
                            </div>
                        @empty
                            <p class="text-xs text-slate-500 py-4">Squad details pending announcement.</p>
                        @endforelse
                    </div>

                    <!-- Team B Squad List -->
                    <div class="space-y-3.5 pl-4 sm:pl-6">
                        @forelse($teamBSquad ?? [] as $player)
                            <div class="flex items-center justify-between group">
                                <div class="min-w-0 text-left sm:text-right flex-1 pr-2 sm:pr-3">
                                    <div class="flex items-center justify-start sm:justify-end space-x-1.5 flex-wrap">
                                        @if(is_array($player) && !empty($player['is_overseas']))
                                            <span class="text-[10px]" title="Overseas Player">✈</span>
                                        @endif
                                        @if(is_array($player) && (!empty($player['is_keeper']) || str_contains($player['role_desc'] ?? '', '(WK)')))
                                            <span class="text-[10px] text-sky-400 font-black">(WK)</span>
                                        @endif
                                        @if(is_array($player) && (!empty($player['is_captain']) || str_contains($player['role_desc'] ?? '', '(C)')))
                                            <span class="text-[10px] text-amber-400 font-black">(C)</span>
                                        @endif
                                        <span class="font-bold text-white text-xs group-hover:text-cyan-400 transition truncate">
                                            {{ is_array($player) ? ($player['name'] ?? 'Player Name') : $player }}
                                        </span>
                                    </div>
                                    <span class="text-[11px] text-slate-400 block truncate">
                                        {{ is_array($player) ? ($player['role'] ?? $player['playing_role'] ?? 'Player') : 'Player' }}
                                    </span>
                                </div>
                                <div class="w-10 h-10 rounded-full overflow-hidden bg-slate-950 border border-slate-800 shrink-0 shadow">
                                    <img src="{{ is_array($player) ? ($player['image'] ?? $player['photo'] ?? asset('images/default-avatar.png')) : asset('images/default-avatar.png') }}" 
                                         alt="{{ is_array($player) ? ($player['name'] ?? 'Player') : $player }}" 
                                         class="w-full h-full object-cover" 
                                         onerror="this.src='{{ asset('images/default-avatar.png') }}'">
                                </div>
                            </div>
                        @empty
                            <p class="text-xs text-slate-500 py-4">Squad details pending announcement.</p>
                        @endforelse
                    </div>

                </div>

                <!-- Legend Footer -->
                <div class="pt-4 border-t border-slate-800/60 text-[11px] text-slate-500 flex items-center space-x-2">
                    <span>✈</span>
                    <span>Overseas player / Foreign player</span>
                </div>

            </div>

        @elseif($tab === 'odds')
            <!-- ODDS TAB (Review Safe Mode Guard) -->
            @if(config('app.show_market_rates', true) && \App\Helpers\SystemSetting::isMarketRatesEnabled())
                <div class="space-y-3">
                    <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-3.5 shadow-md">
                        <div class="flex items-center justify-between pb-2.5 border-b border-slate-800 mb-2.5">
                            <h3 class="text-xs font-bold text-amber-400 uppercase tracking-wider">
                                Match Rates &amp; Metrics
                            </h3>
                            <span class="text-[10px] text-emerald-400 font-medium bg-emerald-500/10 px-2 py-0.5 rounded">Active</span>
                        </div>

                        <div class="space-y-2">
                            <div class="flex items-center justify-between bg-slate-950 p-2.5 rounded-xl border border-slate-800">
                                <div>
                                    <div class="font-bold text-white text-xs">{{ $teamAName }}</div>
                                    <div class="text-[10px] text-slate-400">{{ $teamAShort }}</div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <div class="bg-rose-600 text-white font-mono font-black text-xs px-3 py-1.5 rounded-lg text-center min-w-[50px]">
                                        {{ $liveInfo['min_rate_1'] ?? $liveInfo['min_prob_1'] ?? $rateMin }}
                                    </div>
                                    <div class="bg-emerald-600 text-white font-mono font-black text-xs px-3 py-1.5 rounded-lg text-center min-w-[50px]">
                                        {{ $liveInfo['max_rate_1'] ?? $liveInfo['max_prob_1'] ?? $rateMax }}
                                    </div>
                                </div>
                            </div>

                            <div class="flex items-center justify-between bg-slate-950 p-2.5 rounded-xl border border-slate-800">
                                <div>
                                    <div class="font-bold text-white text-xs">{{ $teamBName }}</div>
                                    <div class="text-[10px] text-amber-400 font-semibold">{{ $favTeam === $teamBShort ? 'Favorite' : $teamBShort }}</div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <div class="bg-rose-600 text-white font-mono font-black text-xs px-3 py-1.5 rounded-lg text-center min-w-[50px]">
                                        {{ $liveInfo['min_rate_2'] ?? $liveInfo['min_prob_2'] ?? $rateMin }}
                                    </div>
                                    <div class="bg-emerald-600 text-white font-mono font-black text-xs px-3 py-1.5 rounded-lg text-center min-w-[50px]">
                                        {{ $liveInfo['max_rate_2'] ?? $liveInfo['max_prob_2'] ?? $rateMax }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Session Summary Accordion (Innings 1 & 2 Table) -->
                    @include('livewire.pages.partials.session-summary-table')
                </div>
            @endif

        @elseif($tab === 'info')
            <!-- INFO TAB: Complete Match Specifications & Technical Overview -->
            <div class="space-y-4">
                <!-- 1. Match Specifications Table -->
                <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-4 sm:p-5 shadow-lg">
                    <h3 class="text-xs sm:text-sm font-black text-amber-400 uppercase tracking-wider mb-3 pb-2.5 border-b border-slate-800 flex items-center justify-between">
                        <span class="flex items-center gap-2">
                            <span>📋</span>
                            <span>Match Specifications</span>
                        </span>
                        <span class="text-[10px] text-slate-400 font-mono">MATCH #{{ $matchId }}</span>
                    </h3>
                    <div class="divide-y divide-slate-800/60 text-xs sm:text-sm">
                        <div class="py-2.5 flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                            <span class="text-slate-400 font-medium">Match</span>
                            <span class="font-bold text-white">{{ $matchs }} ({{ $teamAShort }} vs {{ $teamBShort }})</span>
                        </div>
                        <div class="py-2.5 flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                            <span class="text-slate-400 font-medium">Series / Tournament</span>
                            <span class="font-bold text-amber-400 sm:text-right max-w-md truncate">{{ $series }}</span>
                        </div>
                        <div class="py-2.5 flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                            <span class="text-slate-400 font-medium">Format</span>
                            <span class="font-bold text-white uppercase">{{ $matchType }}</span>
                        </div>
                        <div class="py-2.5 flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                            <span class="text-slate-400 font-medium">Date &amp; Scheduled Time</span>
                            <span class="font-bold text-white font-mono">
                                {{ $matchDate }} • {{ $matchTime ?: 'Time to be confirmed' }}
                            </span>
                        </div>
                        <div class="py-2.5 flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                            <span class="text-slate-400 font-medium">Venue &amp; City</span>
                            <span class="font-bold text-white sm:text-right max-w-md">
                                {{ $venue }} {{ $place ? "({$place})" : '' }}
                            </span>
                        </div>
                        <div class="py-2.5 flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                            <span class="text-slate-400 font-medium">Toss Decision</span>
                            <span class="font-bold text-cyan-300 sm:text-right max-w-md">{{ $toss ?: 'Toss yet to take place' }}</span>
                        </div>
                        @if(!empty($umpire))
                            <div class="py-2.5 flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                                <span class="text-slate-400 font-medium">On-Field Umpires</span>
                                <span class="font-semibold text-white sm:text-right">{{ $umpire }}</span>
                            </div>
                        @endif
                        @if(!empty($thirdUmpire))
                            <div class="py-2.5 flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                                <span class="text-slate-400 font-medium">Third Umpire</span>
                                <span class="font-semibold text-white sm:text-right">{{ $thirdUmpire }}</span>
                            </div>
                        @endif
                        @if(!empty($referee))
                            <div class="py-2.5 flex flex-col sm:flex-row sm:items-center justify-between gap-1">
                                <span class="text-slate-400 font-medium">Match Referee</span>
                                <span class="font-semibold text-white sm:text-right">{{ $referee }}</span>
                            </div>
                        @endif
                        @if(!empty($manOfMatch))
                            <div class="py-2.5 flex flex-col sm:flex-row sm:items-center justify-between gap-1 bg-amber-500/5 -mx-4 px-4 rounded-xl">
                                <span class="text-amber-400 font-bold">Player of the Match</span>
                                <span class="font-black text-amber-300 sm:text-right">🏆 {{ $manOfMatch }}</span>
                            </div>
                        @endif
                        @if(!empty($result))
                            <div class="py-2.5 flex flex-col sm:flex-row sm:items-center justify-between gap-1 bg-emerald-500/5 -mx-4 px-4 rounded-xl">
                                <span class="text-emerald-400 font-bold">Match Result</span>
                                <span class="font-black text-emerald-300 sm:text-right">🏆 {{ $result }}</span>
                            </div>
                        @endif
                    </div>
                </div>

                <!-- 2. Venue & Weather Details -->
                <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-4 sm:p-5 shadow-lg space-y-3">
                    <h3 class="text-xs sm:text-sm font-black text-white uppercase tracking-wider pb-2 border-b border-slate-800 flex items-center justify-between">
                        <span class="flex items-center gap-2">
                            <span>🏟️</span>
                            <span>Venue &amp; Environment</span>
                        </span>
                        <span class="text-[10px] text-amber-400 font-mono">{{ $place ?: 'International Ground' }}</span>
                    </h3>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                        <div class="p-3 rounded-xl bg-slate-950 border border-slate-800 space-y-1">
                            <span class="text-[11px] font-bold text-slate-400 block">Ground Name</span>
                            <span class="text-white font-extrabold text-sm block">{{ $venue }}</span>
                            <span class="text-slate-500 text-[10px]">Pace &amp; Bounce Friendly Surface</span>
                        </div>
                        <div class="p-3 rounded-xl bg-slate-950 border border-slate-800 space-y-1">
                            <span class="text-[11px] font-bold text-slate-400 block">Weather Conditions</span>
                            <span class="text-cyan-300 font-extrabold text-sm block">
                                {{ $weather['temp_c'] ?? '26' }}°C • {{ $weather['weather'] ?? 'Clear Conditions' }}
                            </span>
                            <span class="text-slate-500 text-[10px]">Humidity: {{ $weather['humidity'] ?? '58%' }} | Wind: {{ $weather['wind'] ?? '14 km/h' }}</span>
                        </div>
                    </div>
                </div>
            </div>
        @endif
    </main>
            </div>

            <!-- RIGHT COLUMN: Sticky Sidebar Widgets (lg:col-span-4) -->
            <aside class="hidden lg:block lg:col-span-4 space-y-6 lg:sticky lg:top-20">

                <!-- 1. Follow Cricwire Community Card (YouTube, Instagram, Telegram) -->
                <div class="bg-gradient-to-br from-slate-900 via-slate-900/90 to-amber-950/20 border border-amber-500/20 rounded-2xl p-5 shadow-xl space-y-4">
                    <div class="flex items-center space-x-3 pb-3 border-b border-white/5">
                        <div class="w-10 h-10 rounded-xl bg-amber-400/10 border border-amber-400/30 flex items-center justify-center text-xl shadow-inner shrink-0">
                            📢
                        </div>
                        <div>
                            <h3 class="text-sm font-black text-white uppercase tracking-wide flex items-center gap-2">
                                <span>Follow Cricwire</span>
                                <span class="px-1.5 py-0.5 rounded text-[9px] font-black uppercase tracking-wider bg-amber-400 text-slate-950">
                                    LIVE ALERTS
                                </span>
                            </h3>
                            <p class="text-[11px] text-slate-400">Instant ball updates, tips &amp; team alerts</p>
                        </div>
                    </div>

                    <div class="space-y-2">
                        <!-- Telegram Button -->
                        <a 
                            href="{{ $socialLinks['telegram']['url'] ?? 'https://t.me/cricwire' }}" 
                            target="_blank" 
                            rel="noopener noreferrer" 
                            class="flex items-center justify-between p-3 rounded-xl bg-gradient-to-r from-sky-500/10 via-sky-500/5 to-transparent border border-sky-500/30 hover:border-sky-400 text-slate-200 hover:text-white transition group shadow-sm"
                        >
                            <div class="flex items-center space-x-3">
                                <div class="w-8 h-8 rounded-lg bg-sky-500 flex items-center justify-center text-slate-950 font-black shadow-md text-sm group-hover:scale-105 transition-transform">
                                    ✈️
                                </div>
                                <div>
                                    <span class="text-xs font-bold block text-white group-hover:text-sky-300 transition">Join Telegram Channel</span>
                                    <span class="text-[10px] text-slate-400 block -mt-0.5">Fastest Session Calls &amp; News</span>
                                </div>
                            </div>
                            <span class="text-sky-400 text-xs font-bold">Join →</span>
                        </a>

                        <!-- YouTube Button -->
                        <a 
                            href="{{ $socialLinks['youtube']['url'] ?? 'https://youtube.com/@cricwire' }}" 
                            target="_blank" 
                            rel="noopener noreferrer" 
                            class="flex items-center justify-between p-3 rounded-xl bg-gradient-to-r from-red-500/10 via-red-500/5 to-transparent border border-red-500/30 hover:border-red-400 text-slate-200 hover:text-white transition group shadow-sm"
                        >
                            <div class="flex items-center space-x-3">
                                <div class="w-8 h-8 rounded-lg bg-red-600 flex items-center justify-center text-white font-black shadow-md text-sm group-hover:scale-105 transition-transform">
                                    ▶️
                                </div>
                                <div>
                                    <span class="text-xs font-bold block text-white group-hover:text-red-300 transition">Subscribe on YouTube</span>
                                    <span class="text-[10px] text-slate-400 block -mt-0.5">Match Reviews &amp; Analysis</span>
                                </div>
                            </div>
                            <span class="text-red-400 text-xs font-bold">Watch →</span>
                        </a>

                        <!-- Instagram Button -->
                        <a 
                            href="{{ $socialLinks['instagram']['url'] ?? 'https://instagram.com/cricwire' }}" 
                            target="_blank" 
                            rel="noopener noreferrer" 
                            class="flex items-center justify-between p-3 rounded-xl bg-gradient-to-r from-pink-500/10 via-pink-500/5 to-transparent border border-pink-500/30 hover:border-pink-400 text-slate-200 hover:text-white transition group shadow-sm"
                        >
                            <div class="flex items-center space-x-3">
                                <div class="w-8 h-8 rounded-lg bg-gradient-to-tr from-pink-500 via-rose-500 to-amber-500 flex items-center justify-center text-white font-black shadow-md text-sm group-hover:scale-105 transition-transform">
                                    📸
                                </div>
                                <div>
                                    <span class="text-xs font-bold block text-white group-hover:text-pink-300 transition">Follow on Instagram</span>
                                    <span class="text-[10px] text-slate-400 block -mt-0.5">Behind-the-scenes &amp; Reels</span>
                                </div>
                            </div>
                            <span class="text-pink-400 text-xs font-bold">Follow →</span>
                        </a>
                    </div>
                </div>

                <!-- 2. App Download Promo Card -->
                <div class="bg-gradient-to-b from-slate-900 to-slate-950 border border-slate-800 rounded-2xl p-5 shadow-lg relative overflow-hidden group">
                    <div class="absolute -right-8 -bottom-8 w-32 h-32 bg-amber-500/10 rounded-full blur-2xl pointer-events-none group-hover:bg-amber-500/15 transition"></div>

                    <div class="flex items-center justify-between pb-3 mb-3 border-b border-slate-800/80">
                        <div class="flex items-center space-x-2">
                            <x-cricwire-logo size="small" />
                        </div>
                        <span class="px-2 py-0.5 rounded text-[9px] font-black uppercase tracking-wider bg-emerald-500/20 text-emerald-400 border border-emerald-500/30">
                            MOBILE APP
                        </span>
                    </div>

                    <h4 class="text-sm font-extrabold text-white mb-1">
                        Download Cricwire Pro App
                    </h4>
                    <p class="text-xs text-slate-400 mb-4 leading-relaxed">
                        Get 0.1s ultra-fast audio live line, instant wicket notifications, and session fancy rates on Android &amp; iOS.
                    </p>

                    <div class="grid grid-cols-2 gap-2">
                        <a 
                            href="javascript:void(0)" 
                            onclick="alert('Android APK Download is available soon on Google Play Store!')" 
                            class="flex items-center justify-center space-x-2 py-2 px-3 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-xs font-bold text-white transition shadow-sm"
                        >
                            <span>🤖</span>
                            <span>Google Play</span>
                        </a>
                        <a 
                            href="javascript:void(0)" 
                            onclick="alert('iOS App Store release coming in next build update!')" 
                            class="flex items-center justify-center space-x-2 py-2 px-3 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-xs font-bold text-white transition shadow-sm"
                        >
                            <span>🍏</span>
                            <span>App Store</span>
                        </a>
                    </div>
                </div>

                <!-- 3. Admin Managed Sidebar Ad Banner (Strict Zero Whitespace Rule) -->
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
                    <x-ad-slot slot="match_sidebar" />
                @endif

                <!-- 4. Quick Tournament & Fixture Links -->
                <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-5 backdrop-blur shadow-md space-y-3">
                    <h3 class="text-xs font-bold text-slate-200 uppercase tracking-wider pb-2 border-b border-slate-800 flex items-center justify-between">
                        <span>🏆 Tournaments Hub</span>
                        <a href="{{ route('schedule') }}" wire:navigate class="text-[11px] text-amber-400 hover:underline font-medium">All Fixtures →</a>
                    </h3>
                    <div class="space-y-1.5 text-xs">
                        <a href="{{ route('schedule', ['status' => 'upcoming', 'category' => 't20-leagues']) }}" wire:navigate class="flex items-center justify-between p-2.5 rounded-xl bg-slate-950/60 hover:bg-slate-800 border border-slate-800 hover:border-amber-400/30 text-slate-300 hover:text-amber-400 transition">
                            <span class="font-medium truncate">T20 Leagues 2026</span>
                            <span class="text-slate-600 text-[10px]">→</span>
                        </a>
                        <a href="{{ route('schedule', ['status' => 'upcoming', 'category' => 'odi']) }}" wire:navigate class="flex items-center justify-between p-2.5 rounded-xl bg-slate-950/60 hover:bg-slate-800 border border-slate-800 hover:border-amber-400/30 text-slate-300 hover:text-amber-400 transition">
                            <span class="font-medium truncate">International Series &amp; ODIs</span>
                            <span class="text-slate-600 text-[10px]">→</span>
                        </a>
                        <a href="{{ route('news.index') }}" wire:navigate class="flex items-center justify-between p-2.5 rounded-xl bg-slate-950/60 hover:bg-slate-800 border border-slate-800 hover:border-amber-400/30 text-slate-300 hover:text-amber-400 transition">
                            <span class="font-medium truncate">Cricket Editorials &amp; News</span>
                            <span class="text-slate-600 text-[10px]">→</span>
                        </a>
                    </div>
                </div>

            </aside>
        </div>
    </div>

    <!-- 7. Footer Legal Compliance Disclaimer -->
    <footer class="mt-4 px-4 py-3 bg-slate-950/80 border-t border-slate-900 text-center">
        <p class="text-[10px] leading-relaxed text-slate-500 max-w-sm mx-auto">
            cricwire.in does not promote or support real-money gaming or betting. Please avoid clicking on speculative advertisements. Strictly for informational and analytics purposes only.
        </p>
    </footer>

    <!-- Mobile Fixed Bottom Navigation Bar -->
    <nav class="fixed bottom-0 left-0 right-0 z-50 bg-slate-900/95 backdrop-blur-lg border-t border-slate-800/80 shadow-2xl">
        <div class="max-w-md mx-auto grid grid-cols-4 h-14">
            <a href="{{ url('/') }}" wire:navigate class="flex flex-col items-center justify-center text-rose-500 transition-colors">
                <svg class="w-5 h-5 mb-0.5" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm-1 14.5v-9l6 4.5-6 4.5z"/>
                </svg>
                <span class="text-[10px] font-bold tracking-tight">Live</span>
            </a>

            <a href="{{ route('schedule') }}" wire:navigate class="flex flex-col items-center justify-center text-slate-400 hover:text-slate-200 transition-colors">
                <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
                <span class="text-[10px] font-medium tracking-tight">Schedule</span>
            </a>

            <a href="{{ route('news.index') }}" wire:navigate class="flex flex-col items-center justify-center text-slate-400 hover:text-slate-200 transition-colors">
                <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z" />
                </svg>
                <span class="text-[10px] font-medium tracking-tight">News</span>
            </a>

            <a href="{{ route('polls') }}" wire:navigate class="flex flex-col items-center justify-center text-slate-400 hover:text-slate-200 transition-colors">
                <svg class="w-5 h-5 mb-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z" />
                </svg>
                <span class="text-[10px] font-medium tracking-tight">Polls</span>
            </a>
        </div>
    </nav>
</div>
