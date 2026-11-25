<div 
    class="min-h-screen bg-slate-950 text-slate-100 pb-24 font-sans antialiased flex flex-col selection:bg-rose-500 selection:text-white transition-all duration-300"
    :class="{ 'ring-2 ring-amber-400': isFlashing }"
    x-data="matchDetailEngine({ matchId: '{{ $matchId }}', tab: '{{ $tab }}', isLive: {{ (!empty($isLive) && empty($isCompleted)) ? 'true' : 'false' }}, isFinished: {{ (!empty($isFinished) || !empty($isCompleted)) ? 'true' : 'false' }}, pollSeconds: {{ $pollSeconds ?? 10 }}, pollMs: {{ ($pollSeconds ?? 10) * 1000 }}, isLocal: @js(app()->environment('local')), allowPolling: {{ empty($isCompleted) ? 'true' : 'false' }}, live: { batsman: @js(array_values(array_slice($liveBatters ?? [], 0, 2))), bowler: @js(array_values(array_slice($liveBowlers ?? [], 0, 2))) } })"
>
    <script src="{{ asset('js/match-detail-engine.js') }}"></script>

    @php
        // Unified Score Resolution (Normalized, Never empty!)
        $scoreA = \App\Helpers\CricketMatchFormatter::normalizeScoreString($match['team_a_scores'] ?? $match['score_a'] ?? $match['team_a_score'] ?? $liveInfo['team_a_scores'] ?? $liveInfo['score_a'] ?? $liveInfo['team_a_score'] ?? '');
        $scoreB = \App\Helpers\CricketMatchFormatter::normalizeScoreString($match['team_b_scores'] ?? $match['score_b'] ?? $match['team_b_score'] ?? $liveInfo['team_b_scores'] ?? $liveInfo['score_b'] ?? $liveInfo['team_b_score'] ?? '');
        $combinedScore = \App\Helpers\CricketMatchFormatter::normalizeScoreString($match['score'] ?? $liveInfo['score'] ?? '');

        // Merge match detail with liveInfo fallback
        $teamAName = $match['team_a'] ?? $liveInfo['team_a'] ?? 'Team A';
        $teamAShort = $match['team_a_short'] ?? $liveInfo['team_a_short'] ?? substr($teamAName, 0, 3);
        $teamAImg = $match['team_a_img'] ?? $liveInfo['team_a_img'] ?? '';
        $teamAScores = $scoreA;
        $teamAOver = \App\Helpers\CricketMatchFormatter::normalizeOvers($liveInfo['team_a_over'] ?? $match['team_a_over'] ?? '');

        $teamBName = $match['team_b'] ?? $liveInfo['team_b'] ?? 'Team B';
        $teamBShort = $match['team_b_short'] ?? $liveInfo['team_b_short'] ?? substr($teamBName, 0, 3);
        $teamBImg = $match['team_b_img'] ?? $liveInfo['team_b_img'] ?? '';
        $teamBScores = $scoreB;
        $teamBOver = \App\Helpers\CricketMatchFormatter::normalizeOvers($liveInfo['team_b_over'] ?? $match['team_b_over'] ?? '');

        $series = $match['series'] ?? $liveInfo['series'] ?? $match['series_name'] ?? 'Cricket Series';
        $seriesSlug = \App\Helpers\CricketSlugHelper::makeSeriesSlug($match);
        $matchs = $match['matchs'] ?? $liveInfo['matchs'] ?? $match['match_desc'] ?? 'Match';
        $venue = $match['venue'] ?? $liveInfo['venue'] ?? 'Venue info';
        $place = $match['place'] ?? $liveInfo['place'] ?? '';
        $matchType = $match['match_type'] ?? $liveInfo['match_type'] ?? $match['format'] ?? 'Cricket Match';
        $toss = $match['toss'] ?? $liveInfo['toss'] ?? '';
        $umpire = $match['umpire'] ?? $liveInfo['umpire'] ?? '';
        $thirdUmpire = $match['third_umpire'] ?? $liveInfo['third_umpire'] ?? '';
        $referee = $match['referee'] ?? $liveInfo['referee'] ?? '';
        $manOfMatch = $match['man_of_match_player'] ?? $match['man_of_match'] ?? '';
        $result = $match['result'] ?? $liveInfo['result'] ?? $match['status'] ?? $match['state'] ?? '';
        $needRunBall = $liveInfo['trail_lead'] ?? $match['trail_lead'] ?? $liveInfo['need_run_ball'] ?? $match['need_run_ball'] ?? '';
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
        $favTeam = $liveInfo['fav_team'] ?? $match['fav_team'] ?? $teamAShort ?? 'Fav';
        $rateMin = $liveInfo['min_rate'] ?? $liveInfo['rate_min'] ?? $match['min_rate'] ?? $match['rate_min'] ?? '-';
        $rateMax = $liveInfo['max_rate'] ?? $liveInfo['rate_max'] ?? $match['max_rate'] ?? $match['rate_max'] ?? '-';

        if ($rateMin === 0 || $rateMin === '0' || $rateMin === '0.00') {
            $rateMin = '-';
        }
        if ($rateMax === 0 || $rateMax === '0' || $rateMax === '0.00') {
            $rateMax = '-';
        }

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
            $sessionOver = $liveInfo['s_ovr'] ?? $match['s_ovr'] ?? $liveInfo['session_over'] ?? null;
            $sessionMin = $liveInfo['s_min'] ?? $match['s_min'] ?? null;
            $sessionMax = $liveInfo['s_max'] ?? $match['s_max'] ?? null;
        }

        $isSessionOverValid = !empty($sessionOver) && (int)$sessionOver > 0;
        $isSessionRatesValid = (!empty($sessionMin) && (string)$sessionMin !== '0' && (float)$sessionMin > 0) || 
                               (!empty($sessionMax) && (string)$sessionMax !== '0' && (float)$sessionMax > 0);
        $hasValidSession = $isSessionOverValid && $isSessionRatesValid;

        if (!$hasValidSession) {
            $sessionOver = null;
            $sessionMin = '-';
            $sessionMax = '-';
        }

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
        $currentInningNum = (int) ($liveInfo['current_inning'] ?? $match['live_inning'] ?? $activeInningKey ?? 1);
        $activeInningTeam = $activeInningData['team'] ?? [];
        $currInningScore = (int) ($activeInningTeam['score'] ?? $activeInningData['score'] ?? 0);
        $currInningWkts = (int) ($activeInningTeam['wicket'] ?? $activeInningData['wickets'] ?? 0);
        $currInningOverStr = (string) ($activeInningTeam['over'] ?? $activeInningData['overs'] ?? '0.0');

        // Fallback: If active inning score/overs are empty, extract from scoreA / scoreB / combinedScore (e.g., "TAS 288/6 (89.6 ov)")
        if ($currInningScore === 0 && ($currInningOverStr === '0.0' || empty($currInningOverStr))) {
            $rawScoreToParse = $scoreA ?: ($scoreB ?: $combinedScore);
            if (!empty($rawScoreToParse) && preg_match('/(\d+)(?:\/(\d+))?\s*(?:\(([\d\.]+)(?:\s*ov)?\))?/i', $rawScoreToParse, $mScore)) {
                $currInningScore = (int) $mScore[1];
                if (isset($mScore[2]) && $mScore[2] !== '') {
                    $currInningWkts = (int) $mScore[2];
                }
                if (isset($mScore[3]) && $mScore[3] !== '') {
                    $currInningOverStr = (string) $mScore[3];
                }
            }
        }

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

        // Parse balls bowled from current over string (e.g., "89.6 ov" => 89*6 + 6 = 540)
        preg_match('/(\d+)(?:\.(\d+))?/', $currInningOverStr, $mOvers);
        $compOvers = (int) ($mOvers[1] ?? 0);
        $extraBalls = (int) ($mOvers[2] ?? 0);
        $ballsBowled = ($compOvers * 6) + $extraBalls;

        if ($ballsBowled > 0) {
            $crrVal = number_format(($currInningScore / $ballsBowled) * 6, 2);
        } else {
            $crrVal = '0.00';
        }

        $isSecondInnings = (count($scorecardData) >= 2) || (!empty($scoreB) && !empty($scoreA)) || ((int)($liveInfo['current_inning'] ?? $match['current_inning'] ?? 1) >= 2) || preg_match('/need\s+(\d+)\s+runs/i', (string)($needRunBall ?: ($liveInfo['need_run_ball'] ?? '')));

        if ($isSecondInnings || $currentInningNum >= 2) {
            // Target from 1st inning or liveInfo / match
            if (!empty($liveInfo['target']) && is_numeric($liveInfo['target'])) {
                $targetVal = (int) $liveInfo['target'];
            } elseif (!empty($match['target']) && is_numeric($match['target'])) {
                $targetVal = (int) $match['target'];
            } elseif (!empty($scorecardData[0]['score']) && preg_match('/^(\d+)/', (string)$scorecardData[0]['score'], $mInn1)) {
                $targetVal = ((int)$mInn1[1]) + 1;
            } elseif (!empty($scoreA) && preg_match('/^(\d+)/', (string)$scoreA, $mScA)) {
                $targetVal = ((int)$mScA[1]) + 1;
            } elseif (isset($scorecardData['1']['team']['score'])) {
                $targetVal = ((int) $scorecardData['1']['team']['score']) + 1;
            } elseif (!empty($liveInfo['team_a_score']['1']['score'])) {
                $targetVal = ((int) $liveInfo['team_a_score']['1']['score']) + 1;
            }

            // Fallback: parse target from need_run_ball / status string (e.g., "Zimbabwe Women need 81 runs in 36 balls")
            $needRunBallText = $needRunBall ?: ($liveInfo['need_run_ball'] ?? ($match['status_note'] ?? ($match['status'] ?? '')));
            if (preg_match('/need\s+(\d+)\s+runs/i', $needRunBallText, $mNeed)) {
                $runsNeededVal = (int) $mNeed[1];
                if (!$targetVal && $currInningScore > 0) {
                    $targetVal = $currInningScore + $runsNeededVal;
                }
            }

            if ($targetVal) {
                if ($runsNeededVal === null) {
                    $runsNeededVal = max(0, $targetVal - $currInningScore);
                }
                $ballsRemVal = max(0, $totalMaxBalls - $ballsBowled);
                if (preg_match('/in\s+(\d+)\s+balls/i', $needRunBallText, $mBalls)) {
                    $ballsRemVal = (int) $mBalls[1];
                } elseif (preg_match('/in\s+([\d\.]+)\s+ov/i', $needRunBallText, $mOv)) {
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
        $liveBatters = !empty($liveBatters) ? $liveBatters : ($match['live_batters'] ?? $match['batters'] ?? []);
        $liveBowlers = !empty($liveBowlers) ? $liveBowlers : ($match['live_bowlers'] ?? $match['bowler'] ?? []);
        $lastWicket = null;

        if (empty($liveBatters) && $activeInningData) {
            $allBatsmen = $activeInningData['batsmen'] ?? $activeInningData['batsman'] ?? [];
            foreach ($allBatsmen as $b) {
                $out = strtolower(trim($b['out_desc'] ?? $b['out_by'] ?? ''));
                if ($out === 'batting' || $out === 'not out' || $out === '' || str_contains($out, 'not out')) {
                    $liveBatters[] = $b;
                }
            }
            if (empty($liveBatters) && !empty($allBatsmen)) {
                $liveBatters = array_slice($allBatsmen, -2);
            }
        }

        if ($activeInningData) {
            $allBatsmen = $activeInningData['batsmen'] ?? $activeInningData['batsman'] ?? [];

            // Last Wicket extraction
            $fallWickets = $activeInningData['fall_of_wickets'] ?? $activeInningData['fallwicket'] ?? [];
            if (!empty($fallWickets)) {
                $lastW = end($fallWickets);
                if (!empty($lastW['player'])) {
                    $wName = $lastW['player'];
                    // Find player stats in batsman array if available
                    $wRuns = null;
                    $wBalls = null;
                    foreach ($allBatsmen as $b) {
                        if (str_contains(strtolower($b['name'] ?? ''), strtolower($wName)) || str_contains(strtolower($wName), strtolower($b['name'] ?? ''))) {
                            $wRuns = $b['runs'] ?? $b['run'] ?? null;
                            $wBalls = $b['balls'] ?? $b['ball'] ?? null;
                            break;
                        }
                    }
                    $lastWicket = [
                        'name' => $wName,
                        'runs' => $wRuns,
                        'balls' => $wBalls,
                        'score' => $lastW['runs'] ?? $lastW['score'] ?? '',
                        'wicket' => $lastW['wkt_num'] ?? $lastW['wicket'] ?? '',
                        'over' => $lastW['over'] ?? '',
                    ];
                }
            }

            // Bowlers: Pick only active bowler (1 or at most 2 for live line tab)
            $allBowlers = $activeInningData['bowlers'] ?? $activeInningData['bowler'] ?? $activeInningData['bolwer'] ?? [];
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

        // Active bowler list resolution
        $activeBowlerList = [];
        if (!empty($match['live_bowler']) && is_array($match['live_bowler']) && !empty($match['live_bowler']['name']) && $match['live_bowler']['name'] !== '-') {
            $activeBowlerList[] = $match['live_bowler'];
        } elseif (!empty($liveBowler) && is_array($liveBowler) && !empty($liveBowler['name']) && $liveBowler['name'] !== '-') {
            $activeBowlerList[] = $liveBowler;
        } elseif (!empty($liveBowlers) && is_array($liveBowlers)) {
            $activeBowlerList = array_slice($liveBowlers, 0, 2);
        }

        // Prepare pure Alpine initial live state
        $initialLiveState = [
            'team_a_scores' => $teamAScores,
            'team_a_over' => $teamAOver,
            'team_b_scores' => $teamBScores,
            'team_b_over' => $teamBOver,
            'score_a' => $scoreA,
            'score_b' => $scoreB,
            'score' => $combinedScore,
            'current_ball' => $currentBall,
            'need_run_ball' => $needRunBall,
            'result' => $result,
            'fav_team' => $favTeam,
            'min_rate' => $rateMin,
            'max_rate' => $rateMax,
            's_ovr' => $sessionOver,
            's_min' => $sessionMin,
            's_max' => $sessionMax,
            'batsman' => array_values(array_slice($liveBatters, 0, 2)),
            'batters' => array_values(array_slice($liveBatters, 0, 2)),
            'bowler' => array_values($activeBowlerList),
            'recent_balls' => array_values(array_slice($allCommentaryItems, 0, 12)),
            'last_wicket' => $lastWicket,
        ];
    @endphp

    <div x-init="live = Object.assign(@js($initialLiveState), live)"></div>

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
                <a href="{{ route('series.detail', ['seriesSlug' => $seriesSlug, 'tab' => 'matches']) }}" wire:navigate class="hover:text-amber-400 transition truncate hidden sm:inline">{{ $series }}</a>
                <span class="hidden sm:inline">/</span>
                <span class="text-amber-400 font-semibold truncate">{{ $teamAShort }} vs {{ $teamBShort }} ({{ strtoupper($tab) }})</span>
            </div>
            <div class="flex items-center space-x-2">
                <!-- User Inactivity Auto-Pause Pill -->
                <button 
                    type="button" 
                    x-show="isInactivePaused" 
                    @click="resumeFromInactivity()" 
                    x-cloak 
                    class="flex items-center space-x-1.5 text-[11px] font-semibold text-amber-300 bg-amber-500/15 hover:bg-amber-500/25 border border-amber-500/40 px-2.5 py-1 rounded-lg transition active:scale-95 shadow-sm animate-pulse"
                >
                    <span class="w-2 h-2 rounded-full bg-amber-400"></span>
                    <span>Live updates paused (Inactive) — Tap to resume</span>
                </button>

                <div class="flex items-center space-x-1.5 text-[11px] text-slate-400 bg-slate-900 px-2.5 py-1 rounded-lg border border-slate-800 shrink-0">
                    <span class="w-2 h-2 rounded-full {{ $isLive ? 'bg-rose-500 animate-ping' : 'bg-emerald-400' }}"></span>
                    <span class="font-mono" x-show="!live.match_id">{{ $isLive ? 'LIVE FEED' : 'IST (UTC+5:30)' }}</span>
                    <span class="font-mono text-emerald-400 font-semibold" x-show="live.match_id" x-cloak>
                        ● Live • Updated: <span x-text="lastUpdatedTimeStr">--:--:--</span> (<span x-text="lastUpdatedAgo">just now</span>)
                    </span>
                </div>
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
            <!-- 1. Top Matte Black Live Box (TV Broadcast Scoreboard Style) -->
            <div 
                class="bg-[#050811] rounded-2xl border p-2.5 sm:p-4 shadow-xl relative overflow-hidden transition-all duration-300"
                :class="{
                    'border-slate-800/90': !isFlashing,
                    'border-blue-500 shadow-[0_0_30px_rgba(59,130,246,0.6)]': isFlashing && flashColor === 'blue',
                    'border-emerald-500 shadow-[0_0_30px_rgba(16,185,129,0.6)]': isFlashing && flashColor === 'emerald',
                    'border-rose-500 shadow-[0_0_30px_rgba(244,63,94,0.6)]': isFlashing && flashColor === 'red',
                    'border-amber-400 shadow-[0_0_30px_rgba(251,191,36,0.6)]': isFlashing && flashColor === 'amber'
                }"
            >
                <!-- Top Row: Sound Toggle, Match Title, Live Pill -->
                <div class="flex items-center justify-between gap-2 mb-2 sm:mb-3">
                    <!-- Left: Compact Sound Toggle -->
                    <button 
                        type="button" 
                        @click="soundEnabled = !soundEnabled" 
                        class="w-7 h-7 sm:w-8 sm:h-8 rounded-lg bg-slate-900 border border-slate-800 text-slate-300 hover:text-white transition active:scale-95 flex items-center justify-center shrink-0"
                        aria-label="Toggle Commentary Sound"
                    >
                        <span x-show="soundEnabled" class="text-xs">🔊</span>
                        <span x-show="!soundEnabled" class="text-xs opacity-50">🔇</span>
                    </button>

                    <!-- Center: Crisp Compact Match Title -->
                    <div class="text-[10px] sm:text-xs font-bold tracking-wider text-slate-400 truncate uppercase text-center flex-1 px-1">
                        {{ $matchs }} • <a href="{{ route('series.detail', ['seriesSlug' => $seriesSlug, 'tab' => 'matches']) }}" wire:navigate class="hover:text-amber-400 transition underline-offset-2 hover:underline">{{ $series }}</a>
                    </div>

                    <!-- Right: Sleek TV-style LIVE pill -->
                    <div class="px-2 py-0.5 rounded-full bg-rose-950/90 border border-rose-500/40 text-rose-400 text-[10px] sm:text-xs font-black tracking-wider flex items-center gap-1 shrink-0">
                        <span class="w-1.5 h-1.5 rounded-full bg-rose-500 animate-ping"></span>
                        <span>LIVE</span>
                    </div>
                </div>

                <!-- TV-Broadcast Center Event Pill & Status -->
                <div class="py-1 sm:py-2 flex flex-col items-center justify-center">
                    <div 
                        class="w-11 h-11 sm:w-14 sm:h-14 rounded-full bg-slate-950 border-2 transition-all duration-300 flex items-center justify-center mx-auto"
                        :class="{
                            'border-amber-400/80 shadow-[0_0_15px_rgba(251,191,36,0.25)]': !isFlashing,
                            'border-blue-400 ring-4 ring-blue-500/70 shadow-[0_0_25px_rgba(59,130,246,0.8)] scale-110 animate-bounce': isFlashing && flashColor === 'blue',
                            'border-emerald-400 ring-4 ring-emerald-500/70 shadow-[0_0_25px_rgba(16,185,129,0.8)] scale-110 animate-bounce': isFlashing && flashColor === 'emerald',
                            'border-rose-500 ring-4 ring-rose-500/70 shadow-[0_0_25px_rgba(244,63,94,0.8)] scale-110 animate-bounce': isFlashing && flashColor === 'red',
                            'border-amber-400 ring-4 ring-amber-500/70 shadow-[0_0_25px_rgba(251,191,36,0.8)] scale-110 animate-pulse': isFlashing && flashColor === 'amber'
                        }"
                    >
                        <span class="text-base sm:text-xl font-black font-mono text-white leading-none tracking-tighter" x-text="live.ball_event || live.last_ball_event || live.current_ball || '{{ $currentBall }}'">
                            {{ $currentBall }}
                        </span>
                    </div>

                    <!-- Status text right below -->
                    <div class="text-[11px] sm:text-xs font-bold text-amber-400 mt-1.5 text-center truncate max-w-full px-2">
                        @if($isTossPending && $isUpcoming)
                            Toss Pending
                        @else
                            <span x-text="live.need_run_ball || '{{ $needRunBall ?: 'Ball in Progress' }}'">{{ $needRunBall ?: 'Ball in Progress' }}</span>
                        @endif
                    </div>
                </div>

                <!-- TV Score Strip (Bottom Score Bar Inside Card) -->
                <div class="mt-2.5 sm:mt-3 bg-slate-900/90 border border-slate-800/80 rounded-xl px-3 py-2 sm:px-4 sm:py-2.5 flex items-center justify-between gap-2">
                    <!-- Left Team (Team A) -->
                    <div class="text-left min-w-0">
                        <div class="flex items-center gap-1.5 truncate">
                            <span class="text-[11px] sm:text-xs font-bold text-slate-300 uppercase truncate">{{ $teamAShort }}</span>
                            <span class="text-[10px] text-slate-400 font-medium truncate hidden sm:inline" title="{{ $teamAName }}">({{ $teamAName }})</span>
                        </div>
                        <span class="text-[9px] text-slate-400 font-medium block truncate max-w-[120px] sm:hidden" title="{{ $teamAName }}">{{ $teamAName }}</span>
                        @php
                            $hasActivePlay = !empty($liveBatters) || !empty($targetVal) || !empty($runsNeededVal) || !empty($ballsRemVal) || !empty($combinedScore) || $isSecondInnings;
                            $scoreAFallback = $scoreA ?: ($combinedScore ?: ($hasActivePlay ? 'In Progress' : 'Yet to Bat'));
                            $scoreBFallback = $scoreB ?: ($hasActivePlay ? ($isSecondInnings && $targetVal ? 'Chasing ' . $targetVal : 'In Progress') : 'Yet to Bat');
                        @endphp
                        <div class="text-sm sm:text-base font-black text-white tracking-tight flex items-center gap-1 flex-wrap">
                            <span x-text="live.score_a || live.team_a_scores || live.team_a_score || '{{ $scoreAFallback }}'">{{ $scoreAFallback }}</span>
                            <span class="text-[10px] sm:text-xs font-normal text-slate-400" x-show="live.team_a_over || '{{ $teamAOver }}'" x-text="'(' + (live.team_a_over || '{{ $teamAOver }}') + ' ov)'">@if($teamAOver)({{ $teamAOver }} ov)@endif</span>
                        </div>
                    </div>

                    <!-- Center Divider -->
                    <div class="text-center px-1 shrink-0">
                        <span class="text-[10px] font-black text-slate-500 px-1.5 py-0.5 rounded bg-slate-950 border border-slate-800">VS</span>
                        <template x-if="!(live.score_a || live.team_a_scores) && !(live.score_b || live.team_b_scores) && (live.score || '{{ $combinedScore }}')">
                            <span class="block px-2 py-0.5 mt-1 rounded bg-amber-500/10 border border-amber-500/30 text-amber-400 text-[10px] font-bold" x-text="live.score || '{{ $combinedScore }}'">{{ $combinedScore }}</span>
                        </template>
                    </div>

                    <!-- Right Team (Team B) -->
                    <div class="text-right min-w-0">
                        <div class="flex items-center justify-end gap-1.5 truncate">
                            <span class="text-[10px] text-slate-400 font-medium truncate hidden sm:inline" title="{{ $teamBName }}">({{ $teamBName }})</span>
                            <span class="text-[11px] sm:text-xs font-bold text-slate-300 uppercase truncate text-right">{{ $teamBShort }}</span>
                        </div>
                        <span class="text-[9px] text-slate-400 font-medium block truncate max-w-[120px] sm:hidden text-right ml-auto" title="{{ $teamBName }}">{{ $teamBName }}</span>
                        <div class="text-sm sm:text-base font-black tracking-tight flex items-center justify-end gap-1 flex-wrap"
                             :class="(live.score_b || live.team_b_scores || live.team_b_score || '{{ $scoreB }}') ? 'text-amber-400' : 'text-rose-400 text-xs sm:text-sm font-extrabold'">
                            <span x-text="live.score_b || live.team_b_scores || live.team_b_score || '{{ $scoreBFallback }}'">{{ $scoreBFallback }}</span>
                            <span class="text-[10px] sm:text-xs font-normal text-slate-400" x-show="live.team_b_over || '{{ $teamBOver }}'" x-text="'(' + (live.team_b_over || '{{ $teamBOver }}') + ' ov)'">@if($teamBOver)({{ $teamBOver }} ov)@endif</span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- 2. Match Requirement Grid (4-Column Clean Stat Bar) -->
            @if(($isSecondInnings || $currentInningNum >= 2) && $targetVal)
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

            <!-- 3. Safe Market Rates & Session Cards (Safe Mode Guard) -->
            @if(\App\Helpers\SystemSetting::isMarketRatesEnabled())
                <div class="space-y-2">

                    <!-- Dynamic Session Row (Lambi / Over runs) -->
                    <div x-show="live.s_ovr || '{{ $sessionOver }}'" class="bg-slate-900/90 border border-slate-800 rounded-xl p-3 flex items-center justify-between shadow-md">
                        <div class="text-xs font-bold text-slate-300 flex items-center gap-1.5">
                            <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                            <span><span x-text="live.s_ovr || '{{ $sessionOver }}'">{{ $sessionOver }}</span> Over Runs</span>
                        </div>
                        <div class="flex items-center space-x-2">
                            <div class="bg-rose-600 text-white font-mono font-black text-xs px-3 py-1.5 rounded-lg shadow-sm text-center min-w-[54px]" title="No / Under" x-text="live.s_min || '{{ $sessionMin }}'">
                                {{ $sessionMin }}
                            </div>
                            <div class="bg-emerald-600 text-white font-mono font-black text-xs px-3 py-1.5 rounded-lg shadow-sm text-center min-w-[54px]" title="Yes / Over" x-text="live.s_max || '{{ $sessionMax }}'">
                                {{ $sessionMax }}
                            </div>
                        </div>
                    </div>

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
                                <template x-for="(batter, idx) in ((live.batsman && live.batsman.length) ? live.batsman : @js(array_values(array_slice($liveBatters, 0, 2))))" :key="idx">
                                    <tr :class="idx === 0 ? 'bg-amber-500/5' : ''">
                                        <td class="py-2 font-bold text-white flex items-center gap-1.5">
                                            <span x-text="batter.name || 'Batter'"></span>
                                            <template x-if="idx === 0 || batter.is_striker">
                                                <span class="text-amber-400 text-xs font-black" title="On strike">*</span>
                                            </template>
                                        </td>
                                        <td class="py-2 text-right font-mono font-bold text-white" x-text="batter.runs ?? (batter.run ?? 0)"></td>
                                        <td class="py-2 text-right font-mono text-slate-400" x-text="batter.balls ?? (batter.ball ?? 0)"></td>
                                        <td class="py-2 text-right font-mono text-slate-300" x-text="batter.fours ?? 0"></td>
                                        <td class="py-2 text-right font-mono text-slate-300" x-text="batter.sixes ?? 0"></td>
                                        <td class="py-2 text-right font-mono text-amber-400" x-text="batter.strike_rate || '0.00'"></td>
                                    </tr>
                                </template>
                                <template x-if="!(live.batsman && live.batsman.length) && !{{ count($liveBatters) }}">
                                    <tr>
                                        <td colspan="6" class="py-3 text-center text-slate-500 text-xs">No live batters currently on crease.</td>
                                    </tr>
                                </template>
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

                <!-- Bowler Table (Live Line: Current & Previous Bowlers) -->
                @php
                    $activeBowlerList = [];
                    if (!empty($liveBowlers) && is_array($liveBowlers)) {
                        $activeBowlerList = array_slice($liveBowlers, 0, 2);
                    } elseif (!empty($liveBowler) && is_array($liveBowler) && !empty($liveBowler['name']) && $liveBowler['name'] !== '-') {
                        $activeBowlerList[] = $liveBowler;
                    }
                @endphp
                <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-3 shadow-md">
                    <div class="text-[11px] font-bold text-cyan-400 uppercase tracking-wider mb-2 flex items-center justify-between">
                        <span>Current & Previous Bowler</span>
                        <span class="text-[9px] font-normal text-slate-500">● Live Bowling Figures</span>
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
                                <template x-for="(bowler, bIdx) in (activeBowlers.length ? activeBowlers : @js(array_values(array_slice($activeBowlerList, 0, 2))))" :key="bIdx">
                                    <tr>
                                        <td class="py-2 font-bold text-white flex items-center gap-1.5">
                                            <span x-text="bowler.name ? bowler.name.trim() : 'Bowler'"></span>
                                            <template x-if="bIdx === 0">
                                                <span class="px-1.5 py-0.5 rounded text-[9px] font-black bg-cyan-500/20 text-cyan-300 border border-cyan-500/30">Current</span>
                                            </template>
                                            <template x-if="bIdx > 0">
                                                <span class="px-1.5 py-0.5 rounded text-[9px] font-semibold bg-slate-800 text-slate-400 border border-slate-700/50">Previous</span>
                                            </template>
                                        </td>
                                        <td class="py-2 text-right font-mono text-slate-300" x-text="bowler.overs ?? (bowler.over ?? '0.0')"></td>
                                        <td class="py-2 text-right font-mono text-slate-400" x-text="bowler.maidens ?? (bowler.maiden ?? 0)"></td>
                                        <td class="py-2 text-right font-mono font-bold text-white" x-text="bowler.runs ?? (bowler.run ?? 0)"></td>
                                        <td class="py-2 text-right font-mono font-bold text-rose-400" x-text="bowler.wickets ?? (bowler.wicket ?? 0)"></td>
                                        <td class="py-2 text-right font-mono text-cyan-300" x-text="bowler.economy || '0.00'"></td>
                                    </tr>
                                </template>
                                <template x-if="activeBowlers.length === 0 && !{{ count($activeBowlerList) }}">
                                    <tr>
                                        <td colspan="6" class="py-3 text-center text-slate-500 text-xs">No active bowling figures.</td>
                                    </tr>
                                </template>
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
                    @if(!empty($managedMatch?->toss_fetched) || (!empty($toss) && !$isTossPending))
                        <div class="inline-flex items-center space-x-2 px-3 py-1 rounded-full bg-emerald-500/10 border border-emerald-500/30 text-emerald-400 text-xs font-black uppercase tracking-wider">
                            <span>🪙 {{ $managedMatch?->toss_text ?: $toss }}</span>
                        </div>
                    @elseif($isTossPending && $isUpcoming)
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

                @if(!empty($managedMatch?->toss_fetched) && $isUpcoming)
                    <!-- Prominent Toss Banner with IST Start Time & Countdown -->
                    <div class="p-3 rounded-xl bg-gradient-to-r from-amber-500/15 via-emerald-500/15 to-sky-500/15 border border-amber-500/30 flex flex-col sm:flex-row sm:items-center justify-between gap-2 shadow-md">
                        <div class="flex items-center space-x-2 text-xs sm:text-sm font-bold text-amber-300">
                            <span class="text-base">🪙</span>
                            <span>{{ $managedMatch->toss_text }}</span>
                        </div>
                        <div class="flex items-center space-x-2 text-xs font-semibold text-slate-300">
                            <span>⏰ Match starts at {{ $managedMatch->start_time_ist ? $managedMatch->start_time_ist->timezone('Asia/Kolkata')->format('h:i A') . ' IST' : ($match['match_time'] ?? 'soon') }}</span>
                        </div>
                    </div>
                @endif

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
                        @if(!empty($scoreA))
                            <div class="mt-1 font-mono font-black text-amber-400 text-xs">{{ $scoreA }}</div>
                        @endif
                    </div>

                    <div class="text-center px-2">
                        <span class="w-8 h-8 rounded-full bg-slate-800 border border-slate-700 text-xs font-black text-amber-400 flex items-center justify-center shadow-inner">VS</span>
                        @if(empty($scoreA) && empty($scoreB) && !empty($combinedScore))
                            <div class="mt-1 font-mono text-[10px] text-amber-400 font-bold">{{ $combinedScore }}</div>
                        @endif
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
                        @if(!empty($scoreB))
                            <div class="mt-1 font-mono font-black text-rose-400 text-xs">{{ $scoreB }}</div>
                        @endif
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

            <!-- Match FAQs Accordion (SEO & Schema FAQPage Alignment) -->
            @if(!empty($faqs) && count($faqs) > 0)
                <div x-data="{ activeFaq: 0 }" class="bg-slate-900/90 border border-slate-800 rounded-2xl p-3.5 shadow-md space-y-3">
                    <div class="flex items-center justify-between pb-2 border-b border-slate-800">
                        <h3 class="text-xs font-bold text-white uppercase tracking-wider flex items-center gap-1.5">
                            <span class="text-amber-400">❓</span>
                            <span>Match FAQs & Key Questions</span>
                        </h3>
                        <span class="text-[10px] font-mono text-slate-400 bg-slate-950 px-2 py-0.5 rounded border border-slate-800">Verified Info</span>
                    </div>

                    <div class="space-y-2">
                        @foreach($faqs as $fIndex => $faqItem)
                            <div class="border border-slate-800 rounded-xl overflow-hidden bg-slate-950/70 transition-colors">
                                <button type="button"
                                        @click="activeFaq = (activeFaq === {{ $fIndex }} ? null : {{ $fIndex }})"
                                        class="w-full flex items-center justify-between p-3 text-left text-xs font-bold text-slate-200 hover:text-amber-400 focus:outline-none transition-colors">
                                    <span class="flex items-center gap-2 pr-2">
                                        <span class="w-5 h-5 rounded-full bg-slate-900 border border-slate-800 text-amber-400 flex items-center justify-center text-[10px] font-mono font-bold shrink-0">{{ $fIndex + 1 }}</span>
                                        <span>{{ $faqItem['question'] }}</span>
                                    </span>
                                    <svg class="w-4 h-4 text-slate-400 transform transition-transform duration-200 shrink-0"
                                         :class="activeFaq === {{ $fIndex }} ? 'rotate-180 text-amber-400' : ''"
                                         fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                <div x-show="activeFaq === {{ $fIndex }}"
                                     x-collapse
                                     class="px-3 pb-3 pt-0 text-xs text-slate-400 border-t border-slate-900 leading-relaxed font-sans">
                                    {{ $faqItem['answer'] }}
                                </div>
                            </div>
                        @endforeach
                    </div>
                </div>
            @endif

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
                        <div class="text-sm font-black text-white font-mono tracking-tight">{{ $scoreA ?: ($combinedScore ?: '-') }}</div>
                    </div>
                </div>
                <div class="flex items-center justify-end space-x-2.5 text-right">
                    <div class="truncate">
                        <div class="font-bold text-sm text-white truncate">{{ $teamBShort }}</div>
                        <div class="text-sm font-black text-white font-mono tracking-tight">{{ $scoreB ?: '-' }}</div>
                    </div>
                </div>
            </div>

            <div class="mt-3 pt-2.5 border-t border-slate-800/80 text-center">
                <p class="text-xs font-bold text-emerald-400">
                    {{ $result ?: ($match['status'] ?? ($match['state'] ?? 'Match completed')) }}
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
                    'highlights' => 'Highlights',
                ];
                if (\App\Helpers\SystemSetting::isMarketRatesEnabled()) {
                    $tabDefinitions['odds'] = 'Odds';
                }
                $tabDefinitions['info'] = 'Info';
            @endphp
            @foreach($tabDefinitions as $tKey => $tLabel)
                <button type="button" 
                        wire:key="match-subtab-{{ $tKey }}"
                        wire:click="setTab('{{ $tKey }}')"
                        @click="activeTab = '{{ $tKey }}'" 
                        class="shrink-0 px-4 py-2 rounded-xl text-xs sm:text-sm font-bold uppercase transition cursor-pointer"
                        :class="activeTab === '{{ $tKey }}' ? 'bg-amber-400 text-slate-950 shadow-md shadow-amber-400/20 font-black' : 'text-slate-400 hover:text-white hover:bg-slate-800/60 font-semibold'">
                    {{ $tLabel }}
                </button>
            @endforeach
        </div>
    </div>

    <!-- Tab Content -->
    <main class="flex-1 p-3.5 space-y-3.5">
        @if($tab === 'live-line')
            <!-- Ball-by-ball Over Bubbles (Recent Overs & Live Ball Strip) -->
            <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-3 shadow-md space-y-3">
                <div class="flex items-center justify-between text-[11px] font-semibold text-slate-400">
                    <span class="flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-pulse"></span>
                        <span>Recent Overs: <strong class="text-white font-bold">Ball-by-Ball</strong></span>
                    </span>
                    <span class="text-slate-500 font-mono text-[10px] uppercase">Live Over History</span>
                </div>

                @if(!empty($recentOvers))
                    <!-- Grouped Over-by-Over History List (Latest First) -->
                    <div class="space-y-2">
                        @foreach(array_slice($recentOvers, 0, 4) as $oData)
                            <div class="flex items-center gap-2 overflow-x-auto py-1 border-b border-slate-800/40 pb-2 last:border-b-0 last:pb-0 no-scrollbar">
                                <div class="shrink-0 flex items-center gap-1.5 bg-slate-950 px-2 py-1 rounded-lg border border-slate-800 text-xs">
                                    <span class="font-bold text-amber-400 font-mono">Ov {{ $oData['over_number'] }}:</span>
                                    @if(!empty($oData['bowler']))
                                        <span class="text-slate-400 text-[10px] truncate max-w-[70px]">{{ $oData['bowler'] }}</span>
                                    @endif
                                    <span class="font-black text-slate-300 text-[10px] font-mono bg-slate-900 px-1 rounded">{{ $oData['runs_in_over'] ?? 0 }}r</span>
                                </div>
                                <div class="flex items-center gap-1.5 shrink-0">
                                    @foreach($oData['balls'] as $b)
                                        @php
                                            $bLabel = is_array($b) ? ($b['label'] ?? '') : (string)$b;
                                            $bRuns = is_array($b) ? ($b['runs'] ?? 0) : (is_numeric($b) ? (int)$b : 0);
                                            $isW = is_array($b) ? (!empty($b['wicket']) || $bLabel === 'W') : ($bLabel === 'W');
                                            $isWd = is_array($b) ? ($bLabel === 'wd') : ($bLabel === 'wd');
                                            $isNb = is_array($b) ? ($bLabel === 'nb') : ($bLabel === 'nb');
                                        @endphp
                                        <span class="w-7 h-7 sm:w-8 sm:h-8 rounded-full border font-mono font-bold flex items-center justify-center shrink-0 shadow-sm text-xs
                                            {{ $isW ? 'bg-rose-600 border-rose-500 text-white animate-pulse shadow-sm shadow-rose-600/50' : '' }}
                                            {{ ($isWd || $isNb) ? 'bg-amber-500/20 border-amber-400 text-amber-300 text-[10px] lowercase' : '' }}
                                            {{ (!$isW && !$isWd && !$isNb && (string)$bLabel === '6') ? 'bg-emerald-500/20 border-emerald-400 text-emerald-300 shadow-sm shadow-emerald-500/30' : '' }}
                                            {{ (!$isW && !$isWd && !$isNb && (string)$bLabel === '4') ? 'bg-sky-500/20 border-sky-400 text-sky-300 shadow-sm shadow-sky-500/20' : '' }}
                                            {{ (!$isW && !$isWd && !$isNb && ((string)$bLabel === '0' || $bLabel === '•')) ? 'bg-slate-900 border-slate-700/60 text-slate-400' : '' }}
                                            {{ (!$isW && !$isWd && !$isNb && in_array((string)$bLabel, ['1','2','3','5'], true)) ? 'bg-slate-800/80 border-slate-700/80 text-slate-200' : '' }}
                                        ">
                                            {{ $bLabel ?: '•' }}
                                        </span>
                                    @endforeach
                                </div>
                            </div>
                        @endforeach
                    </div>
                @else
                    <!-- Fallback: Dynamic Ball-by-Ball Strip from Realtime Live / Commentary Items -->
                    <div class="flex items-center gap-1.5 overflow-x-auto py-1 no-scrollbar">
                        <template x-for="(cBall, bIndex) in (live.recent_balls && live.recent_balls.length ? live.recent_balls : @js(array_values(array_slice($allCommentaryItems, 0, 12))))" :key="bIndex">
                            <span class="w-7 h-7 sm:w-8 sm:h-8 rounded-full border font-mono font-bold flex items-center justify-center shrink-0 shadow-sm text-xs"
                                :class="{
                                    'bg-rose-600 border-rose-500 text-white animate-pulse shadow-sm shadow-rose-600/50': (cBall.wicket && cBall.wicket != '0'),
                                    'bg-amber-500/20 border-amber-400 text-amber-300 text-[10px] lowercase': ((cBall.wides && cBall.wides != '0') || (cBall.noballs && cBall.noballs != '0')),
                                    'bg-sky-500/20 border-sky-400 text-sky-300 shadow-sm shadow-sky-500/20': (String(cBall.runs) === '4'),
                                    'bg-emerald-500/20 border-emerald-400 text-emerald-300 shadow-sm shadow-emerald-500/30': (String(cBall.runs) === '6'),
                                    'bg-slate-900 border-slate-700/60 text-slate-400': (String(cBall.runs) === '0' && !cBall.wicket && !cBall.wides && !cBall.noballs),
                                    'bg-slate-800/80 border-slate-700/80 text-slate-200': (String(cBall.runs) !== '4' && String(cBall.runs) !== '6' && String(cBall.runs) !== '0' && !cBall.wicket && !cBall.wides && !cBall.noballs)
                                }"
                                x-text="(cBall.wicket && cBall.wicket != '0') ? 'W' : ((cBall.wides && cBall.wides != '0') ? 'wd' : ((cBall.noballs && cBall.noballs != '0') ? 'nb' : (cBall.runs ?? '•')))"
                                :title="(cBall.overs || '') + ' ov'">
                            </span>
                        </template>
                        <template x-if="!(live.recent_balls && live.recent_balls.length) && !{{ count($allCommentaryItems) }}">
                            <span class="w-8 h-8 rounded-full bg-slate-900 border border-slate-700/60 text-slate-300 font-mono font-bold text-xs flex items-center justify-center shrink-0 shadow-sm" x-text="live.ball_event || live.current_ball || '{{ $currentBall }}'">
                                {{ $currentBall }}
                            </span>
                        </template>
                    </div>
                @endif
            </div>

        @elseif($tab === 'scorecard')
            <!-- SCORECARD TAB -->
            <div class="space-y-4">
                @php
                    // Ensure all innings (e.g. 1, 2, 3, 4 for 4-innings Test matches) are iterated properly
                    $resolvedScorecard = [];
                    if (!empty($scorecardData) && is_array($scorecardData)) {
                        // Check if scorecardData has a nested 'innings' key
                        $inningsSource = isset($scorecardData['innings']) && is_array($scorecardData['innings'])
                            ? $scorecardData['innings']
                            : $scorecardData;

                        // Normalize numeric or 1-based / 0-based keys into an array with continuous 1-based indexing for display
                        $idx = 1;
                        foreach ($inningsSource as $key => $inn) {
                            if (!is_array($inn)) continue;
                            // Skip non-inning associative metadata
                            if (in_array((string)$key, ['match_header', 'match_id', 'raw'], true)) continue;
                            $resolvedScorecard[$idx] = $inn;
                            $idx++;
                        }
                    }
                @endphp
                @forelse($resolvedScorecard as $inKey => $inning)
                    @php
                        $inTeam = $inning['team'] ?? [];
                        $inTeamName = $inning['bat_team'] ?? $inTeam['name'] ?? $inTeam['short_name'] ?? $inning['bat_team_short'] ?? "Innings {$inKey}";
                        $inScore = $inTeam['score'] ?? $inning['score'] ?? 0;
                        $inWkts = $inTeam['wicket'] ?? $inning['wickets'] ?? 0;
                        $inOver = $inTeam['over'] ?? $inning['overs'] ?? '0.0';
                        $inExtras = $inning['extras'] ?? ($inTeam['extras'] ?? null);
                        $inBatsmen = $inning['batsmen'] ?? $inning['batsman'] ?? [];
                        $inBowlers = $inning['bowlers'] ?? $inning['bolwer'] ?? $inning['bowler'] ?? [];
                        $inFallWickets = $inning['fall_of_wickets'] ?? $inning['fallwicket'] ?? [];
                        $inPartnerships = $inning['partnerships'] ?? $inning['partnership'] ?? [];
                        $isDeclared = !empty($inTeam['test_declare']) || !empty($inTeam['is_declared']) || !empty($inning['is_declared']) || !empty($inning['is_declare']);
                    @endphp
                    <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-3.5 sm:p-4 shadow-md space-y-3.5">
                        <div class="flex items-center justify-between pb-2.5 border-b border-slate-800">
                            <h3 class="text-xs sm:text-sm font-black text-white uppercase tracking-wide flex items-center gap-2">
                                @if(!empty($inTeam['flag']) || !empty($inTeam['team_image_url']))
                                    <img src="{{ $inTeam['flag'] ?? $inTeam['team_image_url'] }}" class="w-5 h-5 rounded-full object-cover" onerror="this.style.display='none'">
                                @endif
                                <span>{{ $inTeamName }}</span>
                                <span class="text-slate-500">•</span>
                                <span class="text-[10px] text-slate-400 font-bold uppercase tracking-wider">Innings {{ $inKey }}</span>
                                <span class="text-slate-500">•</span>
                                <span class="text-amber-400 font-mono">{{ is_numeric($inScore) ? "{$inScore}/{$inWkts}" : $inScore }}</span>
                                @if($isDeclared)
                                    <span class="text-[9px] text-rose-400 font-bold uppercase px-1.5 py-0.5 rounded bg-rose-500/10 border border-rose-500/30">d</span>
                                @endif
                            </h3>
                            <div class="text-right">
                                <span class="text-[11px] sm:text-xs text-slate-300 font-mono font-bold">{{ $inOver }} Overs</span>
                                @if(!empty($inExtras))
                                    <span class="block text-[10px] text-slate-400">Extras: {{ is_array($inExtras) ? json_encode($inExtras) : $inExtras }}</span>
                                @endif
                            </div>
                        </div>

                        <!-- Batsmen Table -->
                        <div class="overflow-x-auto">
                            <div class="text-[10px] font-bold text-amber-400 uppercase tracking-wider mb-1.5">Batting</div>
                            <table class="w-full text-left text-xs">
                                <thead>
                                    <tr class="text-slate-400 border-b border-slate-800/80 text-[10px] uppercase font-semibold">
                                        <th class="pb-2">Batter</th>
                                        <th class="pb-2 text-right">R</th>
                                        <th class="pb-2 text-right">B</th>
                                        <th class="pb-2 text-right">4s</th>
                                        <th class="pb-2 text-right">6s</th>
                                        <th class="pb-2 text-right">SR</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-800/40 font-mono">
                                    @forelse($inBatsmen as $b)
                                        <tr>
                                            <td class="py-2 font-sans font-medium text-slate-200">
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    <span class="font-bold text-white">{{ $b['name'] ?? $b['short_name'] ?? 'Batter' }}</span>
                                                    @if(isset($b['impact_status']) && (string)$b['impact_status'] === '2')
                                                        <span class="px-1 py-0.2 rounded text-[8px] font-black uppercase bg-amber-500/20 text-amber-300 border border-amber-500/40" title="Impact Player">IMP</span>
                                                    @endif
                                                </div>
                                                <div class="text-[10px] text-slate-500 font-normal">{{ $b['out_desc'] ?? $b['out_by'] ?? '' }}</div>
                                            </td>
                                            <td class="py-2 text-right font-bold text-white text-xs">{{ $b['runs'] ?? $b['run'] ?? 0 }}</td>
                                            <td class="py-2 text-right text-slate-400">{{ $b['balls'] ?? $b['ball'] ?? 0 }}</td>
                                            <td class="py-2 text-right text-slate-300">{{ $b['fours'] ?? 0 }}</td>
                                            <td class="py-2 text-right text-slate-300">{{ $b['sixes'] ?? 0 }}</td>
                                            <td class="py-2 text-right text-slate-400">{{ $b['strike_rate'] ?? '0.00' }}</td>
                                        </tr>
                                    @empty
                                        <tr>
                                            <td colspan="6" class="py-3 text-center text-slate-500 text-xs">No batting data available.</td>
                                        </tr>
                                    @endforelse
                                </tbody>
                            </table>
                        </div>

                        <!-- Bowlers Table (API: bolwer / bowlers) -->
                        @if(!empty($inBowlers))
                            <div class="pt-2 border-t border-slate-800/70 overflow-x-auto">
                                <div class="text-[10px] font-bold text-cyan-400 uppercase tracking-wider mb-1.5">Bowling</div>
                                <table class="w-full text-left text-xs">
                                    <thead>
                                        <tr class="text-slate-400 border-b border-slate-800/80 text-[10px] uppercase font-semibold">
                                            <th class="pb-2">Bowler</th>
                                            <th class="pb-2 text-right">O</th>
                                            <th class="pb-2 text-right">M</th>
                                            <th class="pb-2 text-right">R</th>
                                            <th class="pb-2 text-right">W</th>
                                            <th class="pb-2 text-right">DOTS</th>
                                            <th class="pb-2 text-right">ECO</th>
                                        </tr>
                                    </thead>
                                    <tbody class="divide-y divide-slate-800/40 font-mono">
                                        @foreach($inBowlers as $bowl)
                                            <tr>
                                                <td class="py-2 font-sans font-medium text-slate-200">
                                                    <div class="flex items-center gap-1.5">
                                                        <span class="font-bold text-white">{{ trim($bowl['name'] ?? $bowl['short_name'] ?? 'Bowler') }}</span>
                                                        @if(isset($bowl['impact_status']) && (string)$bowl['impact_status'] === '2')
                                                            <span class="px-1 py-0.2 rounded text-[8px] font-black uppercase bg-cyan-500/20 text-cyan-300 border border-cyan-500/40" title="Impact Player">IMP</span>
                                                        @endif
                                                    </div>
                                                </td>
                                                <td class="py-2 text-right text-slate-300">{{ $bowl['overs'] ?? $bowl['over'] ?? 0 }}</td>
                                                <td class="py-2 text-right text-slate-400">{{ $bowl['maidens'] ?? $bowl['maiden'] ?? 0 }}</td>
                                                <td class="py-2 text-right font-bold text-white">{{ $bowl['runs'] ?? $bowl['run'] ?? 0 }}</td>
                                                <td class="py-2 text-right font-bold text-rose-400">{{ $bowl['wickets'] ?? $bowl['wicket'] ?? 0 }}</td>
                                                <td class="py-2 text-right text-slate-400">{{ $bowl['dots'] ?? $bowl['dot_ball'] ?? '-' }}</td>
                                                <td class="py-2 text-right text-cyan-300">{{ $bowl['economy'] ?? '0.00' }}</td>
                                            </tr>
                                        @endforeach
                                    </tbody>
                                </table>
                            </div>
                        @endif

                        <!-- Fall of Wickets (fallwicket) Accordion -->
                        @if(!empty($inFallWickets))
                            <div x-data="{ openFow: false }" class="pt-2 border-t border-slate-800/60">
                                <button 
                                    type="button" 
                                    @click="openFow = !openFow" 
                                    class="w-full flex items-center justify-between text-left py-1.5 text-[11px] font-bold text-slate-300 hover:text-white transition"
                                >
                                    <span class="flex items-center gap-1.5">
                                        <span>🔻</span>
                                        <span>Fall of Wickets ({{ count($inFallWickets) }})</span>
                                    </span>
                                    <svg class="w-3.5 h-3.5 text-slate-400 transition-transform" :class="{ 'rotate-180': openFow }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                <div x-show="openFow" x-collapse class="pt-2 space-y-1.5">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs font-mono">
                                        @foreach($inFallWickets as $fow)
                                            <div class="p-2 rounded-lg bg-slate-950/80 border border-slate-800/80 flex items-center justify-between">
                                                <div class="truncate mr-2">
                                                    <span class="text-amber-400 font-bold">{{ $fow['wkt_num'] ?? $fow['wicket'] ?? '' }}w:</span>
                                                    <span class="text-white font-sans truncate">{{ $fow['player'] ?? '' }}</span>
                                                </div>
                                                <div class="text-slate-400 shrink-0 text-[11px]">
                                                    <span class="text-white font-bold">{{ $fow['runs'] ?? $fow['score'] ?? '' }}</span>
                                                    <span>({{ $fow['over'] ?? '' }} ov)</span>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif

                        <!-- Partnerships (partnership) Accordion -->
                        @if(!empty($inPartnerships))
                            <div x-data="{ openPart: false }" class="pt-2 border-t border-slate-800/60">
                                <button 
                                    type="button" 
                                    @click="openPart = !openPart" 
                                    class="w-full flex items-center justify-between text-left py-1.5 text-[11px] font-bold text-slate-300 hover:text-white transition"
                                >
                                    <span class="flex items-center gap-1.5">
                                        <span>🤝</span>
                                        <span>Partnerships ({{ count($inPartnerships) }})</span>
                                    </span>
                                    <svg class="w-3.5 h-3.5 text-slate-400 transition-transform" :class="{ 'rotate-180': openPart }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                                    </svg>
                                </button>
                                <div x-show="openPart" x-collapse class="pt-2 space-y-1.5">
                                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs font-mono">
                                        @foreach($inPartnerships as $part)
                                            <div class="p-2 rounded-lg bg-slate-950/80 border border-slate-800/80 flex items-center justify-between">
                                                <div class="truncate mr-2 font-sans">
                                                    <span class="text-white font-semibold truncate">{{ $part['players_name'] ?? (($part['bat1_name'] ?? $part['player_a_name'] ?? '') . ' & ' . ($part['bat2_name'] ?? $part['player_b_name'] ?? '')) }}</span>
                                                </div>
                                                <div class="text-amber-400 font-bold shrink-0 text-xs">
                                                    <span>{{ $part['total_runs'] ?? $part['run'] ?? 0 }}</span>
                                                    <span class="text-slate-500 font-normal text-[10px]">({{ $part['total_balls'] ?? $part['ball'] ?? 0 }}b)</span>
                                                </div>
                                            </div>
                                        @endforeach
                                    </div>
                                </div>
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
                @if(!empty($isCompleted) || !empty($commentaryUnavailable))
                    <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-8 text-center shadow-lg">
                        <div class="w-12 h-12 rounded-full bg-slate-800/80 border border-slate-700/60 flex items-center justify-center mx-auto mb-3 text-slate-400 text-xl">
                            🎙️
                        </div>
                        <h4 class="text-sm font-bold text-slate-300">Commentary not available</h4>
                        <p class="text-xs text-slate-500 mt-1 max-w-sm mx-auto">
                            Commentary is disabled for completed matches. You can check the complete innings details in the Scorecard tab.
                        </p>
                    </div>
                @else
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
                @endif
            </div>

        @elseif($tab === 'squad')
            <!-- SQUAD TAB -->
            @php
                $teamAPlaying = $playingXI['team_a']['player'] ?? $teamASquad ?? [];
                $teamBPlaying = $playingXI['team_b']['player'] ?? $teamBSquad ?? [];
                $teamAImpact = $impactPlayers['team_a']['player'] ?? [];
                $teamBImpact = $impactPlayers['team_b']['player'] ?? [];
            @endphp
            <div class="space-y-6">
                <!-- 1. Playing XI Section -->
                <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-4 sm:p-6 shadow-xl space-y-5">
                    <!-- Team Header Labels -->
                    <div class="grid grid-cols-2 gap-4 pb-3 border-b border-slate-800 text-xs sm:text-sm font-black uppercase tracking-wider">
                        <div class="flex items-center space-x-2 text-amber-400">
                            @if(!empty($match['team_a_img'] ?? $playingXI['team_a']['flag'] ?? null))
                                <img src="{{ $match['team_a_img'] ?? $playingXI['team_a']['flag'] }}" class="w-6 h-6 rounded-full object-cover" onerror="this.style.display='none'" />
                            @endif
                            <span>{{ $match['team_a'] ?? $teamAName ?? 'Team A' }}</span>
                        </div>
                        <div class="flex items-center justify-end space-x-2 text-cyan-400 text-right">
                            <span>{{ $match['team_b'] ?? $teamBName ?? 'Team B' }}</span>
                            @if(!empty($match['team_b_img'] ?? $playingXI['team_b']['flag'] ?? null))
                                <img src="{{ $match['team_b_img'] ?? $playingXI['team_b']['flag'] }}" class="w-6 h-6 rounded-full object-cover" onerror="this.style.display='none'" />
                            @endif
                        </div>
                    </div>

                    <div class="text-center text-[11px] font-bold text-slate-400 uppercase tracking-widest">
                        Squads & Playing XI
                    </div>

                    <!-- Comparative Playing XI Grid -->
                    <div class="grid grid-cols-2 gap-4 sm:gap-6 divide-x divide-slate-800/60 text-xs">
                        <!-- Team A Playing XI -->
                        <div class="space-y-3.5 pr-2 sm:pr-4">
                            @forelse($teamAPlaying as $player)
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
                                            @if(is_array($player) && (!empty($player['is_captain']) || str_contains($player['play_role'] ?? $player['role_desc'] ?? '', '(C)')))
                                                <span class="text-[10px] text-amber-400 font-black">(C)</span>
                                            @endif
                                            @if(is_array($player) && (!empty($player['is_keeper']) || str_contains($player['play_role'] ?? $player['role_desc'] ?? '', '(WK)')))
                                                <span class="text-[10px] text-sky-400 font-black">(WK)</span>
                                            @endif
                                            @if(is_array($player) && (!empty($player['is_international']) || !empty($player['is_overseas'])))
                                                <span class="text-[10px]" title="Overseas / International Player">✈</span>
                                            @endif
                                        </div>
                                        <span class="text-[11px] text-slate-400 block truncate">
                                            {{ is_array($player) ? ($player['play_role'] ?? $player['role'] ?? $player['playing_role'] ?? 'Player') : 'Player' }}
                                        </span>
                                    </div>
                                </div>
                            @empty
                                <p class="text-xs text-slate-500 py-4">Playing XI pending announcement.</p>
                            @endforelse
                        </div>

                        <!-- Team B Playing XI -->
                        <div class="space-y-3.5 pl-4 sm:pl-6">
                            @forelse($teamBPlaying as $player)
                                <div class="flex items-center justify-between group">
                                    <div class="min-w-0 text-left sm:text-right flex-1 pr-2 sm:pr-3">
                                        <div class="flex items-center justify-start sm:justify-end space-x-1.5 flex-wrap">
                                            @if(is_array($player) && (!empty($player['is_international']) || !empty($player['is_overseas'])))
                                                <span class="text-[10px]" title="Overseas / International Player">✈</span>
                                            @endif
                                            @if(is_array($player) && (!empty($player['is_keeper']) || str_contains($player['play_role'] ?? $player['role_desc'] ?? '', '(WK)')))
                                                <span class="text-[10px] text-sky-400 font-black">(WK)</span>
                                            @endif
                                            @if(is_array($player) && (!empty($player['is_captain']) || str_contains($player['play_role'] ?? $player['role_desc'] ?? '', '(C)')))
                                                <span class="text-[10px] text-amber-400 font-black">(C)</span>
                                            @endif
                                            <span class="font-bold text-white text-xs group-hover:text-cyan-400 transition truncate">
                                                {{ is_array($player) ? ($player['name'] ?? 'Player Name') : $player }}
                                            </span>
                                        </div>
                                        <span class="text-[11px] text-slate-400 block truncate">
                                            {{ is_array($player) ? ($player['play_role'] ?? $player['role'] ?? $player['playing_role'] ?? 'Player') : 'Player' }}
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
                                <p class="text-xs text-slate-500 py-4">Playing XI pending announcement.</p>
                            @endforelse
                        </div>
                    </div>
                </div>

                <!-- 2. Impact Substitutes Section (if announced) -->
                @if(!empty($teamAImpact) || !empty($teamBImpact))
                    <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-4 sm:p-6 shadow-xl space-y-4">
                        <div class="text-xs font-black text-amber-400 uppercase tracking-wider flex items-center justify-between pb-2 border-b border-slate-800">
                            <span>Impact Substitutes</span>
                            <span class="text-[10px] text-slate-500 font-normal">Bench &amp; Reserves</span>
                        </div>
                        <div class="grid grid-cols-2 gap-4 sm:gap-6 divide-x divide-slate-800/60 text-xs">
                            <div class="space-y-3 pr-2 sm:pr-4">
                                @foreach($teamAImpact as $player)
                                    <div class="flex items-center space-x-2.5">
                                        <div class="w-8 h-8 rounded-full overflow-hidden bg-slate-950 border border-slate-800 shrink-0">
                                            <img src="{{ $player['image'] ?? asset('images/default-avatar.png') }}" class="w-full h-full object-cover" onerror="this.src='{{ asset('images/default-avatar.png') }}'">
                                        </div>
                                        <div class="min-w-0">
                                            <span class="font-bold text-white text-xs block truncate">{{ $player['name'] ?? 'Player' }}</span>
                                            <span class="text-[10px] text-slate-400 block truncate">{{ $player['play_role'] ?? 'Substitute' }}</span>
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                            <div class="space-y-3 pl-4 sm:pl-6">
                                @foreach($teamBImpact as $player)
                                    <div class="flex items-center justify-between">
                                        <div class="min-w-0 text-left sm:text-right flex-1 pr-2.5">
                                            <span class="font-bold text-white text-xs block truncate">{{ $player['name'] ?? 'Player' }}</span>
                                            <span class="text-[10px] text-slate-400 block truncate">{{ $player['play_role'] ?? 'Substitute' }}</span>
                                        </div>
                                        <div class="w-8 h-8 rounded-full overflow-hidden bg-slate-950 border border-slate-800 shrink-0">
                                            <img src="{{ $player['image'] ?? asset('images/default-avatar.png') }}" class="w-full h-full object-cover" onerror="this.src='{{ asset('images/default-avatar.png') }}'">
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </div>
                    </div>
                @endif

                <!-- Legend Footer -->
                <div class="pt-2 text-[11px] text-slate-500 flex items-center space-x-2">
                    <span>✈</span>
                    <span>Overseas player / Foreign player</span>
                </div>
            </div>

        @elseif($tab === 'odds')
            <!-- ODDS TAB (Safe Mode Guard) -->
            @if(\App\Helpers\SystemSetting::isMarketRatesEnabled())
                <div class="space-y-3">
                    <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-3.5 shadow-md">
                        <div class="flex items-center justify-between pb-2.5 border-b border-slate-800 mb-2.5">
                            <h3 class="text-xs font-bold text-amber-400 uppercase tracking-wider">
                                Match Rates &amp; Metrics
                            </h3>
                            <span class="text-[10px] text-emerald-400 font-medium bg-emerald-500/10 px-2 py-0.5 rounded">Active</span>
                        </div>

                        @php
                            $rate1Min = $liveInfo['min_rate_1'] ?? $liveInfo['min_prob_1'] ?? ($favTeam === $teamAShort ? $rateMin : '-');
                            $rate1Max = $liveInfo['max_rate_1'] ?? $liveInfo['max_prob_1'] ?? ($favTeam === $teamAShort ? $rateMax : '-');
                            $rate2Min = $liveInfo['min_rate_2'] ?? $liveInfo['min_prob_2'] ?? ($favTeam === $teamBShort ? $rateMin : '-');
                            $rate2Max = $liveInfo['max_rate_2'] ?? $liveInfo['max_prob_2'] ?? ($favTeam === $teamBShort ? $rateMax : '-');

                            if ($rate1Min === 0 || $rate1Min === '0' || $rate1Min === '0.00') $rate1Min = '-';
                            if ($rate1Max === 0 || $rate1Max === '0' || $rate1Max === '0.00') $rate1Max = '-';
                            if ($rate2Min === 0 || $rate2Min === '0' || $rate2Min === '0.00') $rate2Min = '-';
                            if ($rate2Max === 0 || $rate2Max === '0' || $rate2Max === '0.00') $rate2Max = '-';
                        @endphp

                        <div class="space-y-2">
                            <!-- Fav Team Row -->
                            @if(!empty($favTeam) && $favTeam !== 'Fav' && (($rateMin !== '-' && !empty($rateMin)) || ($rateMax !== '-' && !empty($rateMax))))
                                <div class="flex items-center justify-between bg-slate-950 p-2.5 rounded-xl border border-amber-500/20 mb-2">
                                    <div class="flex items-center space-x-2">
                                        <span class="text-xs font-bold text-slate-300">Fav Team:</span>
                                        <span class="px-2 py-0.5 rounded bg-slate-800 text-amber-300 font-bold text-xs border border-amber-500/30">
                                            {{ $favTeam }}
                                        </span>
                                    </div>
                                    <div class="flex items-center space-x-2">
                                        <div class="bg-rose-600 text-white font-mono font-black text-xs px-3 py-1.5 rounded-lg shadow-sm text-center min-w-[50px]">
                                            {{ $rateMin }}
                                        </div>
                                        <div class="bg-emerald-600 text-white font-mono font-black text-xs px-3 py-1.5 rounded-lg shadow-sm text-center min-w-[50px]">
                                            {{ $rateMax }}
                                        </div>
                                    </div>
                                </div>
                            @endif

                            <div class="flex items-center justify-between bg-slate-950 p-2.5 rounded-xl border border-slate-800">
                                <div>
                                    <div class="font-bold text-white text-xs">{{ $teamAName }}</div>
                                    <div class="text-[10px] text-slate-400">{{ $teamAShort }}</div>
                                </div>
                                <div class="flex items-center space-x-2">
                                    <div class="bg-rose-600 text-white font-mono font-black text-xs px-3 py-1.5 rounded-lg text-center min-w-[50px]">
                                        {{ $rate1Min }}
                                    </div>
                                    <div class="bg-emerald-600 text-white font-mono font-black text-xs px-3 py-1.5 rounded-lg text-center min-w-[50px]">
                                        {{ $rate1Max }}
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
                                        {{ $rate2Min }}
                                    </div>
                                    <div class="bg-emerald-600 text-white font-mono font-black text-xs px-3 py-1.5 rounded-lg text-center min-w-[50px]">
                                        {{ $rate2Max }}
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- Dynamic Session Row (Lambi / Over runs) -->
                    @if($hasValidSession)
                        <div class="bg-slate-900/90 border border-slate-800 rounded-xl p-3 flex items-center justify-between shadow-md">
                            <div class="text-xs font-bold text-slate-300 flex items-center gap-1.5">
                                <span class="w-2 h-2 rounded-full bg-amber-400 animate-pulse"></span>
                                <span>{{ $sessionOver }} Over Runs</span>
                            </div>
                            <div class="flex items-center space-x-2">
                                <div class="bg-rose-600 text-white font-mono font-black text-xs px-3 py-1.5 rounded-lg shadow-sm text-center min-w-[54px]" title="No / Under">
                                    {{ $sessionMin }}
                                </div>
                                <div class="bg-emerald-600 text-white font-mono font-black text-xs px-3 py-1.5 rounded-lg shadow-sm text-center min-w-[54px]" title="Yes / Over">
                                    {{ $sessionMax }}
                                </div>
                            </div>
                        </div>
                    @endif

                    <!-- Session Summary Accordion (Innings 1 & 2 Table) -->
                    @include('livewire.pages.partials.session-summary-table')
                </div>
            @endif

        @elseif($tab === 'highlights')
            <!-- HIGHLIGHTS TAB: Video Highlights & Replays -->
            <div class="space-y-4">
                <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-4 sm:p-5 shadow-lg">
                    <h3 class="text-xs sm:text-sm font-black text-amber-400 uppercase tracking-wider mb-4 pb-2.5 border-b border-slate-800 flex items-center justify-between">
                        <span class="flex items-center gap-2">
                            <span>🎬</span>
                            <span>Match Highlights & Video Clips</span>
                        </span>
                        <span class="text-[10px] text-slate-400 font-mono">HLS STREAM READY</span>
                    </h3>

                    @php
                        $highlightsList = $highlights ?? $match['highlights'] ?? [];
                    @endphp

                    @if(!empty($highlightsList))
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            @foreach($highlightsList as $hl)
                                @php
                                    $hlTitle = $hl['title'] ?? 'Match Highlight';
                                    $hlThumb = $hl['thumbnail'] ?? '';
                                    $hlUrl = $hl['hls_url'] ?? '';
                                    $hlDuration = $hl['duration'] ?? '';
                                @endphp
                                <div class="bg-slate-950 border border-slate-800/80 rounded-xl overflow-hidden group hover:border-amber-400/40 transition">
                                    @if($hlThumb)
                                        <div class="relative aspect-video bg-slate-900 overflow-hidden">
                                            <img src="{{ $hlThumb }}" alt="{{ $hlTitle }}" class="w-full h-full object-cover group-hover:scale-105 transition duration-300" />
                                            @if($hlDuration)
                                                <span class="absolute bottom-2 right-2 px-1.5 py-0.5 rounded text-[10px] font-mono font-bold bg-black/80 text-white">
                                                    {{ $hlDuration }}
                                                </span>
                                            @endif
                                            @if($hlUrl)
                                                <a href="{{ $hlUrl }}" target="_blank" rel="noopener noreferrer" class="absolute inset-0 flex items-center justify-center bg-black/40 group-hover:bg-black/20 transition">
                                                    <span class="w-10 h-10 rounded-full bg-amber-400 text-slate-950 flex items-center justify-center shadow-lg font-bold pl-0.5">▶</span>
                                                </a>
                                            @endif
                                        </div>
                                    @endif
                                    <div class="p-3">
                                        <h4 class="text-xs font-bold text-white line-clamp-2 mb-2">{{ $hlTitle }}</h4>
                                        @if($hlUrl)
                                            <a href="{{ $hlUrl }}" target="_blank" rel="noopener noreferrer" class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-amber-400 hover:underline">
                                                <span>Watch Stream</span>
                                                <span>→</span>
                                            </a>
                                        @endif
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    @else
                        <div class="text-center py-12 bg-slate-950/60 rounded-xl border border-slate-800/60">
                            <span class="text-3xl block mb-2">📹</span>
                            <p class="text-slate-400 text-xs font-medium">No video highlights uploaded for this fixture yet.</p>
                            <p class="text-slate-600 text-[11px] mt-1">Highlights will appear once video streams are processed.</p>
                        </div>
                    @endif
                </div>
            </div>

        @elseif($tab === 'info')
            <!-- INFO TAB: Complete Match Specifications & Technical Overview -->
            @php
                $venueWeather = $match['venue_weather'] ?? [];
                $venuePattern = $match['venue_scoring_pattern'] ?? [];
                $paceSpin = $match['pace_spin'] ?? [];
                $teamComp = $match['team_comparison'] ?? [];
                $forms = $match['forms'] ?? [];
                $tossComp = $match['toss_comparison'] ?? [];
                $headToHead = $match['head_to_head'] ?? [];
            @endphp
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

                <!-- 2. Match Tracker Stats (from /matchTracker if available) -->
                @if(!empty($trackerData) && (isset($trackerData['total_runs']) || isset($trackerData['total_wkts'])))
                    <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-4 sm:p-5 shadow-lg space-y-3">
                        <h3 class="text-xs sm:text-sm font-black text-white uppercase tracking-wider pb-2 border-b border-slate-800 flex items-center justify-between">
                            <span class="flex items-center gap-2">
                                <span>📈</span>
                                <span>Match Tracker Summary</span>
                            </span>
                            <span class="text-[10px] text-amber-400 font-mono">Aggregated Key Stats</span>
                        </h3>
                        <div class="grid grid-cols-3 sm:grid-cols-6 gap-2.5 text-center font-mono">
                            <div class="p-2.5 rounded-xl bg-slate-950 border border-slate-800">
                                <span class="text-[10px] text-slate-400 uppercase font-sans block">Runs</span>
                                <span class="text-base font-black text-white">{{ $trackerData['total_runs'] ?? 0 }}</span>
                            </div>
                            <div class="p-2.5 rounded-xl bg-slate-950 border border-slate-800">
                                <span class="text-[10px] text-slate-400 uppercase font-sans block">Wickets</span>
                                <span class="text-base font-black text-rose-400">{{ $trackerData['total_wkts'] ?? 0 }}</span>
                            </div>
                            <div class="p-2.5 rounded-xl bg-slate-950 border border-slate-800">
                                <span class="text-[10px] text-slate-400 uppercase font-sans block">Fours</span>
                                <span class="text-base font-black text-blue-400">{{ $trackerData['total_fours'] ?? 0 }}</span>
                            </div>
                            <div class="p-2.5 rounded-xl bg-slate-950 border border-slate-800">
                                <span class="text-[10px] text-slate-400 uppercase font-sans block">Sixes</span>
                                <span class="text-base font-black text-amber-400">{{ $trackerData['total_sixes'] ?? 0 }}</span>
                            </div>
                            <div class="p-2.5 rounded-xl bg-slate-950 border border-slate-800">
                                <span class="text-[10px] text-slate-400 uppercase font-sans block">50s</span>
                                <span class="text-base font-black text-emerald-400">{{ $trackerData['total_fifty'] ?? 0 }}</span>
                            </div>
                            <div class="p-2.5 rounded-xl bg-slate-950 border border-slate-800">
                                <span class="text-[10px] text-slate-400 uppercase font-sans block">100s</span>
                                <span class="text-base font-black text-cyan-400">{{ $trackerData['total_hundred'] ?? 0 }}</span>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- 3. Venue & Weather Details (from /info venue_weather) -->
                @if(!empty($venueWeather) || !empty($venue))
                    <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-4 sm:p-5 shadow-lg space-y-3">
                        <h3 class="text-xs sm:text-sm font-black text-white uppercase tracking-wider pb-2 border-b border-slate-800 flex items-center justify-between">
                            <span class="flex items-center gap-2">
                                <span>🏟️</span>
                                <span>Venue &amp; Live Weather</span>
                            </span>
                            <span class="text-[10px] text-amber-400 font-mono">{{ $place ?: 'Match Stadium' }}</span>
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3 text-xs">
                            <div class="p-3.5 rounded-xl bg-slate-950 border border-slate-800 space-y-1">
                                <span class="text-[11px] font-bold text-slate-400 block">Ground</span>
                                <span class="text-white font-extrabold text-sm block">{{ $venue }}</span>
                                <span class="text-slate-500 text-[10px]">{{ $place ? "City: {$place}" : 'Official Venue' }}</span>
                            </div>
                            <div class="p-3.5 rounded-xl bg-slate-950 border border-slate-800 flex items-center justify-between">
                                <div class="space-y-1">
                                    <span class="text-[11px] font-bold text-slate-400 block">Weather</span>
                                    <span class="text-cyan-300 font-extrabold text-sm block">
                                        {{ $venueWeather['temp_c'] ?? '-' }}°C • {{ $venueWeather['weather'] ?? 'Conditions Normal' }}
                                    </span>
                                    <span class="text-slate-500 text-[10px]">
                                        Humidity: {{ $venueWeather['humidity'] ?? '-' }}% | Wind: {{ $venueWeather['wind_kph'] ?? '-' }} km/h
                                    </span>
                                </div>
                                @if(!empty($venueWeather['weather_icon']))
                                    <img src="{{ $venueWeather['weather_icon'] }}" class="w-12 h-12 object-contain" alt="Weather Icon">
                                @endif
                            </div>
                        </div>
                    </div>
                @endif

                <!-- 4. Venue Scoring Pattern (from /info venue_scoring_pattern) -->
                @if(!empty($venuePattern) && isset($venuePattern['first_avg_score']))
                    <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-4 sm:p-5 shadow-lg space-y-3">
                        <h3 class="text-xs sm:text-sm font-black text-white uppercase tracking-wider pb-2 border-b border-slate-800 flex items-center justify-between">
                            <span class="flex items-center gap-2">
                                <span>📊</span>
                                <span>Venue Scoring Pattern</span>
                            </span>
                            <span class="text-[10px] text-slate-400 font-mono">{{ $venuePattern['total_matches'] ?? 0 }} Matches Analyzed</span>
                        </h3>
                        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 text-center font-mono text-xs">
                            <div class="p-3 rounded-xl bg-slate-950 border border-slate-800">
                                <span class="text-[10px] text-slate-400 uppercase font-sans block mb-1">1st Avg Score</span>
                                <span class="text-sm font-black text-amber-400">{{ $venuePattern['first_avg_score'] ?? '-' }}</span>
                            </div>
                            <div class="p-3 rounded-xl bg-slate-950 border border-slate-800">
                                <span class="text-[10px] text-slate-400 uppercase font-sans block mb-1">2nd Avg Score</span>
                                <span class="text-sm font-black text-white">{{ $venuePattern['second_avg_score'] ?? '-' }}</span>
                            </div>
                            <div class="p-3 rounded-xl bg-slate-950 border border-slate-800">
                                <span class="text-[10px] text-slate-400 uppercase font-sans block mb-1">Highest Score</span>
                                <span class="text-sm font-black text-emerald-400">{{ $venuePattern['high_score'] ?? '-' }}</span>
                            </div>
                            <div class="p-3 rounded-xl bg-slate-950 border border-slate-800">
                                <span class="text-[10px] text-slate-400 uppercase font-sans block mb-1">Lowest Score</span>
                                <span class="text-sm font-black text-rose-400">{{ $venuePattern['low_score'] ?? '-' }}</span>
                            </div>
                        </div>
                        <div class="grid grid-cols-2 gap-2.5 text-center font-mono text-xs pt-1">
                            <div class="p-2.5 rounded-xl bg-slate-950/80 border border-slate-800/80 flex items-center justify-between">
                                <span class="text-slate-400 font-sans text-[11px]">Win Batting 1st</span>
                                <span class="text-cyan-400 font-bold">{{ $venuePattern['first_win_batting_per'] ?? '0' }}%</span>
                            </div>
                            <div class="p-2.5 rounded-xl bg-slate-950/80 border border-slate-800/80 flex items-center justify-between">
                                <span class="text-slate-400 font-sans text-[11px]">Win Bowling 1st</span>
                                <span class="text-cyan-400 font-bold">{{ $venuePattern['second_win_batting_per'] ?? '0' }}%</span>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- 5. Team Form & Toss Form (from /info forms & toss_comparison) -->
                @if(!empty($forms) || !empty($tossComp))
                    <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-4 sm:p-5 shadow-lg space-y-3.5">
                        <h3 class="text-xs sm:text-sm font-black text-white uppercase tracking-wider pb-2 border-b border-slate-800 flex items-center justify-between">
                            <span class="flex items-center gap-2">
                                <span>⚡</span>
                                <span>Recent Team Form (Last 5)</span>
                            </span>
                            <span class="text-[10px] text-slate-500 font-mono">Recent matches</span>
                        </h3>
                        <div class="space-y-3">
                            <!-- Team A Form -->
                            <div class="flex items-center justify-between bg-slate-950 p-3 rounded-xl border border-slate-800">
                                <span class="text-xs font-bold text-white">{{ $teamAName }} ({{ $teamAShort }})</span>
                                <div class="flex items-center gap-1.5 font-mono font-bold text-xs">
                                    @forelse($forms['team_a'] ?? [] as $res)
                                        <span class="w-6 h-6 rounded-md flex items-center justify-center text-[11px] {{ strtoupper($res) === 'W' ? 'bg-emerald-600 text-white' : 'bg-rose-600 text-white' }}">
                                            {{ strtoupper($res) }}
                                        </span>
                                    @empty
                                        <span class="text-slate-500 text-xs">-</span>
                                    @endforelse
                                </div>
                            </div>
                            <!-- Team B Form -->
                            <div class="flex items-center justify-between bg-slate-950 p-3 rounded-xl border border-slate-800">
                                <span class="text-xs font-bold text-white">{{ $teamBName }} ({{ $teamBShort }})</span>
                                <div class="flex items-center gap-1.5 font-mono font-bold text-xs">
                                    @forelse($forms['team_b'] ?? [] as $res)
                                        <span class="w-6 h-6 rounded-md flex items-center justify-center text-[11px] {{ strtoupper($res) === 'W' ? 'bg-emerald-600 text-white' : 'bg-rose-600 text-white' }}">
                                            {{ strtoupper($res) }}
                                        </span>
                                    @empty
                                        <span class="text-slate-500 text-xs">-</span>
                                    @endforelse
                                </div>
                            </div>
                        </div>
                    </div>
                @endif

                <!-- 6. Head to Head Matches (from /info head_to_head) -->
                @if(!empty($headToHead) && !empty($headToHead['matches']))
                    <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-4 sm:p-5 shadow-lg space-y-3">
                        <div class="flex items-center justify-between pb-2 border-b border-slate-800">
                            <h3 class="text-xs sm:text-sm font-black text-white uppercase tracking-wider flex items-center gap-2">
                                <span>🥊</span>
                                <span>Head-to-Head History</span>
                            </h3>
                            <div class="text-[11px] font-mono text-slate-400">
                                <span class="text-amber-400 font-bold">{{ $teamAShort }}: {{ $headToHead['team_a_win_count'] ?? 0 }}</span>
                                <span class="mx-1">•</span>
                                <span class="text-cyan-400 font-bold">{{ $teamBShort }}: {{ $headToHead['team_b_win_count'] ?? 0 }}</span>
                            </div>
                        </div>
                        <div class="space-y-2">
                            @foreach(array_slice($headToHead['matches'], 0, 5) as $hMatch)
                                <div class="p-3 rounded-xl bg-slate-950 border border-slate-800 text-xs space-y-1">
                                    <div class="flex items-center justify-between text-[11px] text-slate-400">
                                        <span class="font-bold text-slate-300">{{ $hMatch['matchs'] ?? 'Match' }}</span>
                                        <span class="font-mono text-amber-400">{{ $hMatch['team_a_score'] ?? '' }} vs {{ $hMatch['team_b_score'] ?? '' }}</span>
                                    </div>
                                    <p class="text-[11px] text-emerald-400 font-medium">{{ $hMatch['result'] ?? '' }}</p>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @endif
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
                            href="{{ $socialLinks['telegram']['url'] ?? 'https://t.me/cricwireofficial' }}" 
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
                            href="{{ $socialLinks['youtube']['url'] ?? 'https://www.youtube.com/@cricwire.official' }}" 
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
                            href="{{ $socialLinks['instagram']['url'] ?? 'https://instagram.com/cricwire.in' }}" 
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