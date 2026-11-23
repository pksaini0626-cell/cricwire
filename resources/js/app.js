import './bootstrap';

/**
 * Match Detail Real-Time & Static Polling Engine Definition
 */
function createMatchDetailEngine(config) {
    const initialConfig = config || {};
    const initLive = initialConfig.live || {};

    return {
        soundEnabled: false,
        isFlashing: false,
        lastEvent: '',
        lastUpdatedAgo: 'just now',
        lastUpdatedTime: Date.now(),
        lastUpdatedTimeStr: new Date().toLocaleTimeString(),
        matchId: String(initialConfig.matchId || ''),
        isLive: Boolean(initialConfig.isLive === true && !initialConfig.isFinished),
        isFinished: Boolean(initialConfig.isFinished === true),
        isLocal: Boolean(initialConfig.isLocal === true),
        hasAutoPollParam: typeof window !== 'undefined' && new URLSearchParams(window.location.search).get('autopoll') === '1',
        isRefreshing: false,
        allowPolling: initialConfig.allowPolling !== undefined ? Boolean(initialConfig.allowPolling) : true,
        lastInteraction: Date.now(),
        lastActivityTime: Date.now(),
        isInactivePaused: false,
        activeTab: String(initialConfig.tab || 'live-line'),
        updatedAt: String(initialConfig.updatedAt || ''),
        pollMs: initialConfig.pollMs || (Number(initialConfig.pollSeconds || 8) * 1000) || 10000,
        pollTimer: null,
        agoTimer: null,
        inactivityTimer: null,
        isPinned: false,
        isSkipped: false,
        userVotes: {},
        live: {
            team_a_scores: '',
            team_b_scores: '',
            team_a_over: '',
            team_b_over: '',
            score_a: '',
            score_b: '',
            score: '',
            statusText: '',
            current_ball: '•',
            ball_event: '',
            last_ball_event: '',
            need_run_ball: '',
            result: '',
            fav_team: '',
            min_rate: '',
            max_rate: '',
            s_ovr: '',
            s_min: '',
            s_max: '',
            batsman: [],
            batters: [],
            bowler: [],
            recent_balls: [],
            recentBalls: [],
            commentary: [],
            last_wicket: null,
            ...initLive
        },

        get activeBowlers() {
            const b = this.live?.bowler;
            if (!b) return [];
            if (Array.isArray(b)) {
                return b.filter(item => item && item.name && item.name !== '-');
            }
            return (b.name && b.name !== '-') ? [b] : [];
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
                }).catch(() => {});
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
            if (this.isLive && !this.isFinished) {
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

            if (this.allowPolling) {
                // Heartbeat: ping once on mount and every 20s while tab is visible & user active
                this.sendHeartbeat();
                setInterval(() => {
                    if (!document.hidden && this.isUserActive()) {
                        this.sendHeartbeat();
                    }
                }, 20000);

                // Zero-Refresh Fast Live Poller hitting /api/live/match/{matchId} dynamically whenever the match is live
                if (this.isLive && !document.hidden && !this.isFinished && this.isUserActive()) {
                    this.fetchLiveMatch();
                }
                this.startPoller();
            }

            // Relative time "Updated Xs ago" ticker
            this.agoTimer = setInterval(() => {
                const elapsedSec = Math.floor((Date.now() - this.lastUpdatedTime) / 1000);
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
                    if (this.allowPolling && this.isUserActive()) {
                        this.sendHeartbeat();
                        if (this.isLive) {
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
            if (this.agoTimer) clearInterval(this.agoTimer);
            if (this.inactivityTimer) clearInterval(this.inactivityTimer);
        },

        startPoller() {
            if (this.isFinished || !this.allowPolling) return;
            if (this.pollTimer) clearInterval(this.pollTimer);
            const interval = Math.max(5000, Number(this.pollMs) || 10000);
            this.pollTimer = setInterval(() => {
                if (!document.hidden && this.isLive && !this.isFinished && this.isUserActive()) {
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
            if (!this.isUserActive()) return;
            try {
                const res = await fetch(`/api/live/match/${this.matchId}?_=${Date.now()}`, {
                    headers: { 'Accept': 'application/json' },
                    cache: 'no-store'
                });

                if (!res.ok) return;

                const data = await res.json();
                if (!data || Object.keys(data).length === 0) return;

                // Adjust dynamic polling interval if returned from server
                if (data.poll_interval_ms && Number(data.poll_interval_ms) > 0) {
                    const newPollMs = Number(data.poll_interval_ms);
                    if (Math.abs(newPollMs - this.pollMs) >= 1000) {
                        this.pollMs = newPollMs;
                        this.startPoller();
                    }
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

                // Explicit reactive score & status fields
                const scoreA = data.scoreA || data.score_a || data.team_a_scores || data.team_a_score || '';
                const scoreB = data.scoreB || data.score_b || data.team_b_scores || data.team_b_score || '';
                const combinedScore = data.combinedScore || data.score || scoreA || '';

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
                    updated.bowler = bowlerData;
                } else if (bowlerData && typeof bowlerData === 'object' && bowlerData.name && bowlerData.name !== '-') {
                    updated.bowler = [bowlerData];
                }

                // Recent balls reactive state
                const recentBallsList = data.recentBalls || data.recent_balls || [];
                if (Array.isArray(recentBallsList) && recentBallsList.length) {
                    updated.recentBalls = recentBallsList;
                    updated.recent_balls = recentBallsList;
                }

                // Commentary reactive state
                if (Array.isArray(data.commentary) && data.commentary.length) {
                    updated.commentary = data.commentary;
                }

                // Timestamp
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

                this.handleBallEvent(data);
            } catch (err) {
                // Ignore transient network errors
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
            const ballKey = (latestBall.over !== undefined && latestBall.ball !== undefined)
                ? String(latestBall.over) + '.' + String(latestBall.ball)
                : (payload.last_ball_event || payload.event || payload.raw_status || '');

            if (ballKey && ballKey !== this.lastEvent) {
                this.isFlashing = true;
                setTimeout(() => { this.isFlashing = false; }, 800);

                this.lastEvent = ballKey;

                // Determine voice event to speak (4, 6, W, or ball type)
                const ballType = String(latestBall.type || latestBall.runs || payload.last_ball_event || payload.event || '').toLowerCase();
                const runs = String(latestBall.runs || '');
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
