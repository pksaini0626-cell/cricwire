/**
 * Match Detail Real-Time & Static Polling Engine
 */
window.matchDetailEngine = function(config) {
    return {
        soundEnabled: false,
        isFlashing: false,
        lastEvent: '',
        matchId: String(config.matchId || ''),
        pollMs: Math.max(3000, Number(config.pollSeconds || 10) * 1000),
        pollTimer: null,
        isPinned: false,
        isSkipped: false,
        userVotes: {},
        live: {},
        sendHeartbeat() {
            if (document.visibilityState === 'visible' && this.matchId) {
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
        init() {
            // Heartbeat: ping once on mount and every 20s while tab is visible
            this.sendHeartbeat();
            setInterval(() => this.sendHeartbeat(), 20000);
            document.addEventListener('visibilitychange', () => {
                if (document.visibilityState === 'visible') {
                    this.sendHeartbeat();
                    this.fetchMatchData();
                }
            });

            // Lightweight Alpine.js Static JSON Poller hitting /live-data/match-{matchId}.json (Zero PHP/DB load)
            this.fetchMatchData();
            this.startPoller();

            // Sync user local preferences
            const pinned = JSON.parse(localStorage.getItem('pinned_matches') || '[]');
            const skipped = JSON.parse(localStorage.getItem('skipped_matches') || '[]');
            this.isPinned = pinned.some(id => String(id) === String(this.matchId));
            this.isSkipped = skipped.some(id => String(id) === String(this.matchId));
            this.userVotes = JSON.parse(localStorage.getItem('user_poll_votes') || '{}');
        },
        destroy() {
            if (this.pollTimer) clearInterval(this.pollTimer);
        },
        startPoller() {
            if (this.pollTimer) clearInterval(this.pollTimer);
            const interval = Math.max(3000, this.pollMs || 10000);
            this.pollTimer = setInterval(() => {
                if (document.visibilityState === 'visible') {
                    this.fetchMatchData();
                }
            }, interval);
        },
        async fetchMatchData() {
            try {
                let data = null;
                try {
                    const res = await fetch('/live-data/match-' + this.matchId + '.json?t=' + Date.now());
                    if (res.ok) {
                        data = await res.json();
                    }
                } catch (e) {}

                // Fallback to searching in /live-data/home.json if match file isn't available or empty
                if (!data || (!data.score_a && !data.score_b && !data.team_a_scores && !data.team_b_scores && !data.score)) {
                    try {
                        const homeRes = await fetch('/live-data/home.json?t=' + Date.now());
                        if (homeRes.ok) {
                            const homeData = await homeRes.json();
                            const allMatches = [
                                ...(homeData.live || []),
                                ...(homeData.upcoming || []),
                                ...(homeData.recent || []),
                                ...(homeData.finished || [])
                            ];
                            const found = allMatches.find(m => String(m.match_id || m.id) === String(this.matchId));
                            if (found) {
                                data = data ? { ...found, ...data } : found;
                            }
                        }
                    } catch (e) {}
                }

                if (!data) return;

                // Merge into live without blanking out existing valid values
                const updated = { ...this.live };
                for (const [key, val] of Object.entries(data)) {
                    if (val !== null && val !== undefined && val !== '') {
                        updated[key] = val;
                    } else if (updated[key] === undefined) {
                        updated[key] = val;
                    }
                }

                // Explicit unified score normalization
                const scoreA = data.score_a || data.team_a_scores || data.team_a_score || updated.team_a_scores || updated.score_a || '';
                const scoreB = data.score_b || data.team_b_scores || data.team_b_score || updated.team_b_scores || updated.score_b || '';
                const combined = data.score || updated.score || scoreA || '';

                if (scoreA) {
                    updated.team_a_scores = scoreA;
                    updated.score_a = scoreA;
                    updated.team_a_score = scoreA;
                }
                if (scoreB) {
                    updated.team_b_scores = scoreB;
                    updated.score_b = scoreB;
                    updated.team_b_score = scoreB;
                }
                if (combined) {
                    updated.score = combined;
                }

                if (data.status || data.state || data.result) {
                    updated.statusText = data.result || data.status_note || data.status || data.state || updated.statusText;
                }

                if (Array.isArray(data.live_batters) && data.live_batters.length) {
                    updated.batsman = data.live_batters;
                } else if (Array.isArray(data.batsman) && data.batsman.length) {
                    updated.batsman = data.batsman;
                }

                if (data.live_bowler && data.live_bowler.name && data.live_bowler.name !== '-') {
                    updated.bolwer = [data.live_bowler];
                } else if (Array.isArray(data.bolwer) && data.bolwer.length) {
                    updated.bolwer = data.bolwer;
                }

                if (Array.isArray(data.recent_balls) && data.recent_balls.length) {
                    updated.recent_balls = data.recent_balls;
                }

                this.live = updated;

                if (data.poll_ms && data.poll_ms !== this.pollMs) {
                    this.pollMs = data.poll_ms;
                    this.startPoller();
                }
                this.handleBallEvent(data);
            } catch (err) {
                // Ignore transient network errors
            }
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
};
