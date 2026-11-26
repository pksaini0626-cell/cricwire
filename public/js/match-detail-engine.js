/**
 * Match Detail Real-Time & Static Polling Engine Definition
 */
function normalizeOvers(ov) {
    if (!ov) return '';
    const clean = String(ov).trim().replace(/\s*ov(?:ers)?\b/gi, '');
    const m = clean.match(/^(\d+)\.6$/);
    if (m) {
        return String(parseInt(m[1], 10) + 1);
    }
    return clean;
}

function normalizeScoreString(sc) {
    if (!sc) return '';
    const trimmed = String(sc).trim();
    if (/^(yet to bat|null|none|-)$/i.test(trimmed)) return '';
    return trimmed.replace(/\((\d+)\.6(\s*ov)?\)/gi, function (match, p1, p2) {
        return '(' + (parseInt(p1, 10) + 1) + (p2 || ' ov') + ')';
    });
}

function createMatchDetailEngine(config) {
    const initialConfig = config || {};
    const matchIdStr = String(initialConfig.matchId || '');
    const initLive = initialConfig.live || {};

    // Preserve polled live state across Livewire DOM morphs
    if (typeof window !== 'undefined') {
        window.__cricwireLiveStore = window.__cricwireLiveStore || {};
    }
    const persisted = (typeof window !== 'undefined' && matchIdStr && window.__cricwireLiveStore[matchIdStr])
        ? window.__cricwireLiveStore[matchIdStr]
        : null;

    const initRecentBalls = Array.isArray(initialConfig.recentBalls) && initialConfig.recentBalls.length > 0
        ? initialConfig.recentBalls
        : (Array.isArray(initLive.recentBalls) && initLive.recentBalls.length > 0
            ? initLive.recentBalls
            : (Array.isArray(initLive.recent_balls) ? initLive.recent_balls : []));

    const baseLiveState = {
        scoreA: initLive.scoreA || initLive.score_a || initLive.team_a_scores || '',
        scoreB: initLive.scoreB || initLive.score_b || initLive.team_b_scores || '',
        combinedScore: initLive.combinedScore || initLive.score || '',
        team_a_scores: initLive.scoreA || initLive.team_a_scores || '',
        team_b_scores: initLive.scoreB || initLive.team_b_scores || '',
        team_a_over: initLive.team_a_over || '',
        team_b_over: initLive.team_b_over || '',
        score_a: initLive.scoreA || initLive.score_a || '',
        score_b: initLive.scoreB || initLive.score_b || '',
        score: initLive.combinedScore || initLive.score || '',
        statusText: initLive.statusText || '',
        current_ball: initLive.current_ball || '•',
        ball_event: initLive.ball_event || '',
        last_ball_event: initLive.last_ball_event || '',
        need_run_ball: initLive.need_run_ball || '',
        result: initLive.result || '',
        toss: initLive.toss || '',
        show_toss_banner: Boolean(initLive.show_toss_banner),
        toss_status: initLive.toss_status || 'none',
        toss_text: initLive.toss_text || '',
        fav_team: '',
        min_rate: '',
        max_rate: '',
        s_ovr: '',
        s_min: '',
        s_max: '',
        batsman: initLive.batsman || initLive.batters || [],
        batters: initLive.batters || initLive.batsman || [],
        bowler: initLive.bowler || [],
        recent_balls: initRecentBalls,
        recentBalls: initRecentBalls,
        commentary: initLive.commentary || [],
        serverUpdatedAt: initialConfig.serverUpdatedAt || '',
        last_wicket: null,
        ...initLive
    };

    const mergedInitialLive = persisted ? { ...baseLiveState, ...persisted } : baseLiveState;
    const resolvedInitialRecentBalls = (Array.isArray(mergedInitialLive.recentBalls) && mergedInitialLive.recentBalls.length > 0 && mergedInitialLive.recentBalls[0]?.event_type)
        ? mergedInitialLive.recentBalls
        : initRecentBalls;
    mergedInitialLive.recentBalls = resolvedInitialRecentBalls;
    mergedInitialLive.recent_balls = resolvedInitialRecentBalls;

    return {
        soundEnabled: false,
        isFlashing: false,
        lastEvent: '',
        lastBallSignature: '',
        flashColor: 'amber',
        serverFetchedAt: persisted?.serverUpdatedAt || initialConfig.serverUpdatedAt || null,
        serverUpdatedAt: persisted?.serverUpdatedAt || initialConfig.serverUpdatedAt || '',
        lastUpdatedAgo: 'just now',
        lastUpdatedTime: Date.now(),
        lastUpdatedTimeStr: new Date().toLocaleTimeString(),
        matchId: matchIdStr,
        isLive: Boolean(initialConfig.isLive === true && !initialConfig.isFinished),
        isFinished: Boolean(initialConfig.isFinished === true),
        isLocal: Boolean(initialConfig.isLocal === true),
        hasAutoPollParam: typeof window !== 'undefined' && new URLSearchParams(window.location.search).get('autopoll') === '1',
        isRefreshing: false,
        isFetching: false,
        allowPolling: initialConfig.allowPolling !== undefined ? Boolean(initialConfig.allowPolling) : true,
        lastInteraction: Date.now(),
        lastActivityTime: Date.now(),
        isInactivePaused: false,
        activeTab: String(initialConfig.tab || 'live-line'),
        updatedAt: String(initialConfig.updatedAt || initialConfig.serverUpdatedAt || ''),
        pollMs: (Number(initialConfig.pollMs) > 0 && Number(initialConfig.pollMs) <= 30000)
            ? Number(initialConfig.pollMs)
            : ((Number(initialConfig.pollSeconds) > 0 && Number(initialConfig.pollSeconds) <= 30) ? Number(initialConfig.pollSeconds) * 1000 : 8000),
        pollTimer: null,
        heartbeatTimer: null,
        agoTimer: null,
        inactivityTimer: null,
        isPinned: false,
        isSkipped: false,
        userVotes: {},
        recentBalls: resolvedInitialRecentBalls,
        live: mergedInitialLive,

        get activeBowlers() {
            const b = this.live?.bowler;
            if (!b) return [];
            if (Array.isArray(b)) {
                return b.filter(item => item && item.name && item.name !== '-');
            }
            return (b.name && b.name !== '-') ? [b] : [];
        },

        get hasFirstBallStarted() {
            const check = (s) => {
                if (!s) return false;
                const t = String(s).trim().toLowerCase();
                if (t === '' || t === 'yet to bat' || t === 'null' || t === '-' || t === '0/0' || t === '0/0 (0)' || t === '0/0 (0.0 ov)') return false;
                return /[1-9]/.test(t);
            };
            return check(this.live?.scoreA || this.live?.score_a || this.live?.team_a_scores || this.live?.team_a_score)
                || check(this.live?.scoreB || this.live?.score_b || this.live?.team_b_scores || this.live?.team_b_score)
                || check(this.live?.combinedScore || this.live?.score);
        },

        get showLiveTossBanner() {
            return Boolean(this.live?.show_toss_banner)
                && !this.isFinished
                && !this.hasFirstBallStarted
                && ['pending', 'delayed', 'decided'].includes(String(this.live?.toss_status || ''));
        },

        sendHeartbeat() {
            if (!document.hidden && this.matchId && !this.isFinished) {
                fetch('/api/matches/' + this.matchId + '/ping', {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''
                    }
                }).catch(() => { });
            }
        },

        isUserActive() {
            // Check 2-minute inactivity limit (120,000ms) and document visibility
            const inactiveDuration = Date.now() - this.lastInteraction;
            if (inactiveDuration >= 120000 || document.hidden) {
                this.isInactivePaused = true;
                return false;
            }
            this.isInactivePaused = false;
            return true;
        },

        resumeFromInactivity() {
            this.lastInteraction = Date.now();
            this.lastActivityTime = Date.now();
            this.isInactivePaused = false;
            this.sendHeartbeat();
            if ((this.isLive || this.allowPolling) && !this.isFinished) {
                this.fetchLiveMatch();
                this.startPoller();
            }
        },

        init() {
            this.syncPreferences();
            window.addEventListener('match-pin-updated', () => this.syncPreferences());
            window.addEventListener('pinned-matches-updated', () => this.syncPreferences());

            // Track user interaction (mousemove, touchstart, scroll, keydown)
            const handleUserInteraction = () => {
                this.lastInteraction = Date.now();
                this.lastActivityTime = Date.now();
                if (this.isInactivePaused) {
                    this.isInactivePaused = false;
                    this.startPoller();
                }
            };

            ['mousemove', 'touchstart', 'scroll', 'keydown', 'mousedown'].forEach(evt => {
                window.addEventListener(evt, handleUserInteraction, { passive: true });
            });

            // Check inactivity timer every 10 seconds (2 minutes = 120,000ms)
            this.inactivityTimer = setInterval(() => {
                if (Date.now() - this.lastInteraction >= 120000) {
                    this.isInactivePaused = true;
                }
            }, 10000);

            // If finished, completely skip poller and heartbeats
            if (this.isFinished) {
                this.userVotes = JSON.parse(localStorage.getItem('user_poll_votes') || '{}');
                return;
            }

            // On local without ?autopoll=1, do not start auto-polling or heartbeats
            if (this.isLocal && !this.hasAutoPollParam) {
                return;
            }

            if (this.allowPolling) {
                // Heartbeat: ping once on mount and every 20s while tab is visible
                this.sendHeartbeat();
                        if (this.heartbeatTimer) clearInterval(this.heartbeatTimer);
                        this.heartbeatTimer = setInterval(() => {
                            if (!document.hidden && (this.isLive || this.allowPolling) && !this.isFinished) {
                                this.sendHeartbeat();
                            }
                        }, 20000);

                            // Zero-Refresh Fast Live Poller hitting /api/live/match/{matchId} every 8s when live (or 15s during 30m pre-match toss window)
                            if ((this.isLive || this.allowPolling) && !document.hidden && !this.isFinished) {
                                this.fetchLiveMatch();
                            }
                            this.startPoller();
                        }

                        // Relative time ticker synced from server_updated_at if available
                        this.agoTimer = setInterval(() => {
                            let referenceTs = this.lastUpdatedTime;
                            if (this.serverFetchedAt) {
                                const serverTs = new Date(this.serverFetchedAt).getTime();
                                if (!isNaN(serverTs) && serverTs > 0) {
                                    referenceTs = serverTs;
                                }
                            }
                            const elapsedSec = Math.max(0, Math.floor((Date.now() - referenceTs) / 1000));
                            if (elapsedSec < 5) {
                                this.lastUpdatedAgo = 'just now';
                            } else if (elapsedSec < 60) {
                                this.lastUpdatedAgo = elapsedSec + 's ago';
                            } else {
                                this.lastUpdatedAgo = Math.floor(elapsedSec / 60) + 'm ago';
                            }
                        }, 1000);

                        document.addEventListener('visibilitychange', () => {
                            if (!document.hidden && !this.isFinished) {
                                    if (this.allowPolling) {
                                        this.sendHeartbeat();
                                        if (this.isLive || this.allowPolling) {
                                            this.fetchLiveMatch();
                                            this.startPoller();
                                        }
                                    }
                                }
                            });

                        // Sync user local preferences
                        this.userVotes = JSON.parse(localStorage.getItem('user_poll_votes') || '{}');
                    },

                    syncPreferences() {
                        const pinned = JSON.parse(localStorage.getItem('pinned_matches') || '[]');
                        const skipped = JSON.parse(localStorage.getItem('skipped_matches') || '[]');
                        this.isPinned = pinned.some(id => String(id) === String(this.matchId));
                        this.isSkipped = skipped.some(id => String(id) === String(this.matchId));
                    },

                    destroy() {
                        if (this.pollTimer) clearInterval(this.pollTimer);
                        if (this.heartbeatTimer) clearInterval(this.heartbeatTimer);
                        if (this.agoTimer) clearInterval(this.agoTimer);
                        if (this.inactivityTimer) clearInterval(this.inactivityTimer);
                    },

                    startPoller() {
                        if (this.isFinished || !this.allowPolling) return;
                        if (this.pollTimer) clearInterval(this.pollTimer);
                        const interval = Math.max(5000, Math.min(30000, Number(this.pollMs) || 8000));
                        this.pollTimer = setInterval(() => {
                                if (!document.hidden && (this.isLive || this.allowPolling) && !this.isFinished) {
                                    this.fetchLiveMatch();
                                }
                            }, interval);
                    },

        async refreshOnce() {
                        this.isRefreshing = true;
                        this.lastInteraction = Date.now();
                        this.lastActivityTime = Date.now();
                        this.isInactivePaused = false;
                        await this.fetchLiveMatch();
                        setTimeout(() => { this.isRefreshing = false; }, 600);
                    },

        async fetchLiveMatch() {
                        if (!this.matchId) return;
                        if (document.hidden) return;
                        if (this.isFetching) return;

                        this.isFetching = true;
                        try {
                            const includeCommParam = (this.activeTab === 'commentary' && !document.hidden) ? '&include_commentary=1' : '';
                            const res = await fetch(`/api/live/match/${this.matchId}?_=${Date.now()}${includeCommParam}`, {
                    cache: 'no-store',
                                headers: {
                                    'Accept': 'application/json',
                                    'Cache-Control': 'no-cache'
                                }
                            });

                            if (!res.ok) return;

                            const data = await res.json();
                            if (!data || Object.keys(data).length === 0) return;

                            // Adjust dynamic polling interval if returned from server (clamped to <= 15s for live responsiveness)
                            if (data.poll_interval_ms && Number(data.poll_interval_ms) > 0) {
                                const newPollMs = Math.max(5000, Math.min(15000, Number(data.poll_interval_ms)));
                                if (Math.abs(newPollMs - this.pollMs) >= 1000) {
                                    this.pollMs = newPollMs;
                                    this.startPoller();
                                }
                            }

                                const serverTs = data.server_updated_at || data.server_fetched_at || data.updated_at || '';
                                if (serverTs) {
                                    this.serverFetchedAt = serverTs;
                                    this.serverUpdatedAt = serverTs;
                                }
                                this.lastUpdatedTime = Date.now();
                                this.lastUpdatedTimeStr = new Date().toLocaleTimeString();
                                this.lastUpdatedAgo = 'just now';

                                // Merge into live reactively without overwriting existing non-empty values with empty strings/nulls
                                const updated = { ...this.live };
                                for (const [key, val] of Object.entries(data)) {
                                    if (val !== null && val !== undefined && val !== '') {
                                        updated[key] = val;
                                    } else if (updated[key] === undefined) {
                                        updated[key] = val;
                                    }
                                }

                                // Never revert a decided toss back to pending/none
                                if (this.live?.toss_status === 'decided' && data.toss_status !== 'decided') {
                                    updated.toss_status = 'decided';
                                    updated.toss_text = this.live.toss_text;
                                    updated.toss = this.live.toss || this.live.toss_text;
                                    updated.show_toss_banner = this.live.show_toss_banner;
                                }

                                if (data.is_live === true && !data.is_completed) {
                                    this.isLive = true;
                                }

                                // Stop polling immediately if match is completed
                                if (data.is_completed === true || data.isFinished === true || this.isFinished) {
                                    this.isFinished = true;
                                    this.isLive = false;
                                    this.allowPolling = false;
                                    if (this.pollTimer) {
                                        clearInterval(this.pollTimer);
                                        this.pollTimer = null;
                                    }
                                    if (data.result && !/^(match in progress|live|ball in progress)$/i.test(String(data.result).trim())) {
                                        updated.result = data.result;
                                    }
                                }

                                // Explicit reactive score & status fields
                                const rawScoreA = data.scoreA || data.score_a || data.team_a_scores || data.team_a_score || '';
                                const rawScoreB = data.scoreB || data.score_b || data.team_b_scores || data.team_b_score || '';
                                const rawCombined = data.combinedScore || data.score || rawScoreA || '';
                                const scoreA = normalizeScoreString(rawScoreA);
                                const scoreB = normalizeScoreString(rawScoreB);
                                const combinedScore = normalizeScoreString(rawCombined);

                                if (scoreA) {
                                    updated.scoreA = scoreA;
                                    updated.team_a_scores = scoreA;
                                    updated.score_a = scoreA;
                                    updated.team_a_score = scoreA;
                                }
                                if (scoreB) {
                                    updated.scoreB = scoreB;
                                    updated.team_b_scores = scoreB;
                                    updated.score_b = scoreB;
                                    updated.team_b_score = scoreB;
                                }
                                if (combinedScore) {
                                    updated.combinedScore = combinedScore;
                                    updated.score = combinedScore;
                                }
                                if (data.team_a_over || data.over_a) {
                                    updated.team_a_over = normalizeOvers(data.team_a_over || data.over_a);
                                }
                                if (data.team_b_over || data.over_b) {
                                    updated.team_b_over = normalizeOvers(data.team_b_over || data.over_b);
                                }

                                const statusVal = data.statusText || data.result || data.status_note || data.status || data.state || '';
                                if (statusVal) {
                                    updated.statusText = statusVal;
                                    if (['finished', 'completed', 'complete', '3'].includes(String(statusVal).toLowerCase()) || Boolean(data.result)) {
                                        this.isLive = false;
                                    }
                                }

                                // Batters reactive state
                                const battersList = data.batters || data.live_batters || data.batsman || [];
                                if (Array.isArray(battersList) && battersList.length) {
                                    updated.batters = battersList;
                                    updated.batsman = battersList;
                                }

                                // Bowler reactive state (standardized strictly as bowler array)
                                const bowlerData = data.bowler || (data.live_bowler ? [data.live_bowler] : []);
                                if (Array.isArray(bowlerData) && bowlerData.length) {
                                    updated.bowler = bowlerData.map(b => ({
                                        ...b,
                                        overs: normalizeOvers(b.overs || b.over || '0.0'),
                                        over: normalizeOvers(b.overs || b.over || '0.0')
                                    }));
                                } else if (bowlerData && typeof bowlerData === 'object' && bowlerData.name && bowlerData.name !== '-') {
                                    updated.bowler = [{
                                        ...bowlerData,
                                        overs: normalizeOvers(bowlerData.overs || bowlerData.over || '0.0'),
                                        over: normalizeOvers(bowlerData.overs || bowlerData.over || '0.0')
                                    }];
                                }

                                // Recent balls reactive state (never overwrite with empty array)
                                const recentBallsList = (Array.isArray(data.recent_balls) && data.recent_balls.length > 0)
                                    ? data.recent_balls
                                    : ((Array.isArray(data.recentBalls) && data.recentBalls.length > 0) ? data.recentBalls : null);
                                if (recentBallsList) {
                                    this.recentBalls = recentBallsList;
                                    updated.recentBalls = recentBallsList;
                                    updated.recent_balls = recentBallsList;
                                }

                                    // Commentary reactive state
                                    if (Array.isArray(data.commentary) && data.commentary.length) {
                                        updated.commentary = data.commentary;
                                    }

                                    // Timestamp
                                    if (serverTs) {
                                        updated.serverUpdatedAt = serverTs;
                                        updated.server_updated_at = serverTs;
                                    }
                                    if (data.updated_at) {
                                        updated.updated_at = data.updated_at;
                                        this.updatedAt = data.updated_at;
                                    }

                                    // Market Rates & Session
                                    if (data.fav_team) updated.fav_team = data.fav_team;
                                    if (data.min_rate || data.rate_min) updated.min_rate = data.min_rate || data.rate_min;
                                    if (data.max_rate || data.rate_max) updated.max_rate = data.max_rate || data.rate_max;
                                    if (data.session_over || data.s_ovr) updated.s_ovr = data.session_over || data.s_ovr;
                                    if (data.session_min || data.s_min || data.session_no) updated.s_min = data.session_min || data.s_min || data.session_no;
                                    if (data.session_max || data.s_max || data.session_yes) updated.s_max = data.session_max || data.s_max || data.session_yes;

                                    // Last ball indicator
                                    if (data.latest_ball) {
                                        const lb = data.latest_ball;
                                        let ballLabel = '•';
                                        if (lb.wicket && lb.wicket != '0') {
                                            ballLabel = 'W';
                                        } else if (lb.runs !== undefined && lb.runs !== null) {
                                            ballLabel = String(lb.runs);
                                        } else if (lb.type) {
                                            ballLabel = lb.type;
                                        }
                                        updated.ball_event = ballLabel;
                                        updated.last_ball_event = ballLabel;
                                    }

                                    this.live = updated;
                                    if (typeof window !== 'undefined' && this.matchId) {
                                        window.__cricwireLiveStore = window.__cricwireLiveStore || {};
                                        window.__cricwireLiveStore[this.matchId] = updated;
                                    }

                                    this.handleBallEvent(data);
                                } catch (err) {
                                    // Ignore transient network errors
                                } finally {
                                    this.isFetching = false;
                                }
                            },

                            fetchMatchData() {
                                return this.fetchLiveMatch();
                            },

                            togglePin() {
                                let pinned = JSON.parse(localStorage.getItem('pinned_matches') || '[]');
                                const id = String(this.matchId);
                                if (this.isPinned) {
                                    pinned = pinned.filter(i => String(i) !== id);
                                    this.isPinned = false;
                                } else {
                                    if (!pinned.some(i => String(i) === id)) {
                                        pinned.push(id);
                                    }
                                    this.isPinned = true;
                                }
                                localStorage.setItem('pinned_matches', JSON.stringify(pinned));
                                window.dispatchEvent(new CustomEvent('match-pin-updated'));
                                window.dispatchEvent(new CustomEvent('pinned-matches-updated'));
                            },

                            skipMatch() {
                                let skipped = JSON.parse(localStorage.getItem('skipped_matches') || '[]');
                                const id = String(this.matchId);
                                if (!skipped.some(i => String(i) === id)) {
                                    skipped.push(id);
                                    localStorage.setItem('skipped_matches', JSON.stringify(skipped));
                                }
                                this.isSkipped = true;
                                window.dispatchEvent(new CustomEvent('match-skip-updated'));
                                window.location.href = '/';
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
                                const payload = e.payload || e;
                                const latestBall = payload.latest_ball || {};
                                const curOver = latestBall.over !== undefined ? String(latestBall.over) : (payload.team_a_over || payload.over_a || '');
                                const curBall = latestBall.ball !== undefined ? String(latestBall.ball) : (payload.last_ball || payload.ball_event || '');
                                const scA = payload.score_a || payload.scoreA || payload.team_a_scores || '';
                                const scB = payload.score_b || payload.scoreB || payload.team_b_scores || '';

                                // Signature of latest ball and runs state
                                const signature = `${curOver}_${curBall}_${scA}_${scB}`;

                                if (signature && signature !== this.lastBallSignature) {
                                    const isInitial = this.lastBallSignature === '';
                                    this.lastBallSignature = signature;
                                    this.lastEvent = (latestBall.over !== undefined && latestBall.ball !== undefined)
                                        ? String(latestBall.over) + '.' + String(latestBall.ball)
                                        : (payload.last_ball_event || payload.event || payload.raw_status || '');

                                    if (!isInitial) {
                                        // Determine ball type and pulse color
                                        const ballType = String(latestBall.type || latestBall.runs || payload.last_ball_event || payload.event || '').toLowerCase();
                                        const runs = String(latestBall.runs !== undefined ? latestBall.runs : '');

                                        if (runs === '4' || ballType.includes('four') || ballType === '4') {
                                            this.flashColor = 'blue';
                                        } else if (runs === '6' || ballType.includes('six') || ballType === '6') {
                                            this.flashColor = 'emerald';
                                        } else if (ballType.includes('wicket') || ballType.includes('out') || ballType === 'w') {
                                            this.flashColor = 'red';
                                        } else {
                                            this.flashColor = 'amber';
                                        }

                                        // Set isFlashing for 1200ms
                                        this.isFlashing = true;
                                        setTimeout(() => { this.isFlashing = false; }, 1200);

                                        // Voice announcement
                                        let eventType = ballType;
                                        if (runs === '4' || ballType.includes('four') || ballType === '4') {
                                            eventType = '4';
                                        } else if (runs === '6' || ballType.includes('six') || ballType === '6') {
                                            eventType = '6';
                                        } else if (ballType.includes('wicket') || ballType.includes('out') || ballType === 'w') {
                                            eventType = 'w';
                                        }
                                        this.speakEvent(eventType);
                                    }
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
                        };
                    }

                    // Global window fallback
                    window.matchDetailEngine = createMatchDetailEngine;

                    // Alpine.data registration
                    document.addEventListener('alpine:init', () => {
                        if (window.Alpine) {
                            window.Alpine.data('matchDetailEngine', createMatchDetailEngine);
                        }
                    });
