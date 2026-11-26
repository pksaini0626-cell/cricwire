@php
    $locksAtTimestamp = $battle->locks_at ? $battle->locks_at->timestamp : 0;
    $shouldPollRoom = in_array($battle->status, ['pending_accept', 'drafting'], true);
    $shouldPollLive = $battle->status === 'locked_live';
    $battleUrl = route('games.battle.show', $battle->slug);
@endphp

<div
    class="min-h-screen bg-slate-950 text-slate-100 pb-24 md:pb-8 flex flex-col justify-between"
    @if($shouldPollRoom) wire:poll.4s.visible="pollRoom" @elseif($shouldPollLive) wire:poll.15s.visible="pollLiveScores" @endif
    x-data="{
        locksAt: {{ $locksAtTimestamp }},
        nowTs: Math.floor(Date.now() / 1000),
        timer: null,
        init() {
            this.timer = setInterval(() => {
                this.nowTs = Math.floor(Date.now() / 1000);
            }, 1000);
        },
        destroy() {
            if (this.timer) clearInterval(this.timer);
        },
        countdownText() {
            if (!this.locksAt) return '--:--';
            const diff = this.locksAt - this.nowTs;
            if (diff <= 0) return 'LOCKED';
            const hrs = Math.floor(diff / 3600);
            const mins = Math.floor((diff % 3600) / 60);
            const secs = diff % 60;
            const pad = (n) => String(n).padStart(2, '0');
            return hrs > 0 ? `${hrs}h ${pad(mins)}m ${pad(secs)}s` : `${pad(mins)}m ${pad(secs)}s`;
        }
    }"
>
    <div>
        <x-header />

        <main class="max-w-7xl mx-auto w-full px-3 sm:px-6 lg:px-8 py-5 sm:py-8 space-y-5">
            <!-- Top Breadcrumb + Match Battle Header -->
            <div class="flex flex-wrap items-center justify-between gap-2 text-xs text-slate-400">
                <div class="flex items-center gap-2">
                    <a href="{{ route('games.index') }}" wire:navigate class="hover:text-amber-400 font-semibold transition">🎮 Games Hub</a>
                    <span>/</span>
                    <span class="text-amber-400 font-bold truncate max-w-[220px] sm:max-w-none">{{ $battle->match_title }}</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="px-2.5 py-1 rounded-lg bg-slate-900 border border-slate-800 text-[11px] font-mono font-bold text-amber-300">
                        {{ $battle->modeLabel() }} • {{ $battle->match_format }}
                    </span>
                    @if($isBeforeLock)
                        <span class="px-2.5 py-1 rounded-lg bg-amber-500/15 border border-amber-500/30 text-[11px] font-mono font-black text-amber-300">
                            🔒 Locks in <span x-text="countdownText()"></span>
                        </span>
                    @endif
                </div>
            </div>

            <!-- Battle Matchup Banner -->
            <section class="relative rounded-2xl bg-gradient-to-br from-slate-900 via-[#0F172A] to-emerald-950/25 border border-white/10 p-4 sm:p-6 shadow-2xl overflow-hidden">
                <div class="flex flex-col md:flex-row md:items-center md:justify-between gap-4">
                    <div class="space-y-1">
                        <div class="flex items-center gap-2 flex-wrap">
                            <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider
                                {{ match($battle->status) {
                                    'pending_accept' => 'bg-amber-500/20 text-amber-300 border border-amber-500/40',
                                    'drafting' => 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/40',
                                    'locked_live' => 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40',
                                    'settled' => 'bg-amber-400 text-slate-950',
                                    default => 'bg-rose-500/20 text-rose-300 border border-rose-500/40',
                                } }}">
                                {{ strtoupper(str_replace('_', ' ', $battle->status)) }}
                            </span>
                            <span class="text-xs text-slate-400">
                                ⏰ Cutoff: {{ $battle->locks_at?->copy()->timezone('Asia/Kolkata')->format('d M Y, h:i A') }} IST (5 mins before start)
                            </span>
                        </div>
                        <h1 class="text-xl sm:text-2xl font-black text-white tracking-tight">
                            {{ $challengerTitle }} <span class="text-amber-400">vs</span> {{ $opponentTitle }}
                        </h1>
                        <p class="text-xs sm:text-sm text-slate-300">
                            🏏 {{ $battle->match_title }} • 💎 <strong class="text-emerald-300">1 Batting Run = 1 CricGem</strong>
                        </p>
                    </div>

                    <div class="flex items-center gap-3 shrink-0">
                        <div class="px-4 py-2.5 rounded-xl bg-slate-950/90 border border-amber-500/30 text-center">
                            <div class="text-[10px] text-slate-400 uppercase font-bold truncate max-w-[110px]">Team 1</div>
                            <div class="text-xl font-black font-mono text-amber-300">{{ $battle->challenger_runs }}</div>
                        </div>
                        <span class="text-xs font-black text-slate-500">VS</span>
                        <div class="px-4 py-2.5 rounded-xl bg-slate-950/90 border border-cyan-500/30 text-center">
                            <div class="text-[10px] text-slate-400 uppercase font-bold truncate max-w-[110px]">Team 2</div>
                            <div class="text-xl font-black font-mono text-cyan-300">{{ $battle->opponent_runs }}</div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Action Feedback -->
            @if($actionError)
                <div class="p-3.5 rounded-2xl bg-rose-500/15 border border-rose-500/40 text-rose-200 text-xs font-bold">
                    ⚠️ {{ $actionError }}
                </div>
            @endif
            @if($actionSuccess)
                <div class="p-3.5 rounded-2xl bg-emerald-500/15 border border-emerald-500/40 text-emerald-200 text-xs font-bold">
                    {{ $actionSuccess }}
                </div>
            @endif

            {{-- ===================================================================== --}}
            {{-- STATE 1: PENDING ACCEPTANCE                                           --}}
            {{-- ===================================================================== --}}
            @if($battle->status === 'pending_accept')
                <section class="rounded-2xl bg-slate-900/90 border border-amber-500/30 p-6 text-center space-y-4 shadow-xl">
                    <div class="text-4xl">⚔️</div>
                    <h2 class="text-lg sm:text-xl font-black text-white">
                        {{ $challengerTitle }} Challenged {{ $opponentTitle }}!
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-300 max-w-xl mx-auto">
                        Once {{ $opponentTitle }} accepts, Cricwire will perform an instant animated <strong>System Coin Toss</strong> to decide who picks 1st in the <strong>{{ $battle->modeLabel() }}</strong> Live Squad Draft.
                    </p>

                    @if($currentUser && (int) $currentUser->id === (int) $battle->opponent_id)
                        <div class="flex flex-wrap items-center justify-center gap-3 pt-2">
                            <button
                                type="button"
                                wire:click="acceptChallenge"
                                class="px-6 py-3 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-400 hover:from-emerald-400 hover:to-teal-300 text-slate-950 text-xs sm:text-sm font-black uppercase tracking-wider shadow-lg transition active:scale-95"
                            >
                                🪙 Accept &amp; Flip System Coin Toss
                            </button>
                            <button
                                type="button"
                                wire:click="declineChallenge"
                                class="px-5 py-3 rounded-xl bg-slate-800 hover:bg-rose-600/30 text-slate-300 hover:text-rose-200 text-xs font-bold transition"
                            >
                                Decline Challenge
                            </button>
                        </div>
                    @elseif($currentUser && (int) $currentUser->id === (int) $battle->challenger_id)
                        <div class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-950 border border-slate-800 text-xs text-amber-300 font-bold">
                            <span class="w-2 h-2 rounded-full bg-amber-400 animate-ping"></span>
                            <span>Waiting for {{ $opponentTitle }} to accept &amp; join the Draft Room (auto-refreshing every 3s)...</span>
                        </div>
                    @elseif(!$currentUser)
                        <a href="{{ route('google.redirect', ['intended' => route('games.battle.show', $battle->slug)]) }}" class="inline-flex items-center gap-2 px-5 py-3 rounded-xl bg-amber-400 text-slate-950 text-xs font-black uppercase tracking-wider">
                            Sign In to Accept / View Battle →
                        </a>
                    @endif
                </section>
            @endif

            {{-- ===================================================================== --}}
            {{-- STATE 2: LIVE TURN-BY-TURN DRAFT ROOM                                 --}}
            {{-- ===================================================================== --}}
            @if($battle->status === 'drafting')
                @php
                    $myPicksCount = $currentUser ? $battle->picks->where('user_id', $currentUser->id)->count() : 0;
                @endphp
                <!-- Top Turn Status Strip + Toss Info -->
                <section class="rounded-2xl border p-4 sm:p-5 space-y-3 shadow-xl {{ $isMyTurn ? 'bg-gradient-to-r from-emerald-950/70 via-slate-900 to-emerald-950/50 border-emerald-400/70' : 'bg-slate-900/90 border-slate-700' }}">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="flex items-center gap-3">
                            <div class="w-10 h-10 rounded-full {{ $isMyTurn ? 'bg-emerald-500/20 border-emerald-400 animate-pulse' : 'bg-amber-500/20 border-amber-400/50' }} border flex items-center justify-center text-xl shrink-0">
                                {{ $isMyTurn ? '🟢' : '🪙' }}
                            </div>
                            <div>
                                <div class="text-[11px] font-bold uppercase tracking-wider text-amber-300">
                                    System Coin Toss Winner: {{ $battle->tossWinner?->captainTitle() ?? 'Captain' }}
                                </div>
                                <div class="text-sm sm:text-base font-black text-white mt-0.5">
                                    @if($isMyTurn)
                                        <span class="text-emerald-300">🟢 YOUR TURN — Tap "+ Pick" on any available player below (Pick {{ $myPicksCount + 1 }} of {{ $battle->team_size }})</span>
                                    @elseif($isDraftPaused)
                                        <span class="text-amber-300">⏸️ Draft Paused — Waiting for {{ $otherCaptainName }}</span>
                                    @else
                                        <span class="text-amber-200">⏳ Waiting for {{ $battle->currentTurnUser?->captainTitle() ?? ($otherCaptainName ?: 'opponent') }} to pick their player...</span>
                                    @endif
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-2 text-xs font-mono shrink-0">
                            <span class="px-3 py-1.5 rounded-xl bg-slate-950 border border-slate-800 text-slate-300">
                                Total Picks: <strong class="text-amber-300">{{ $battle->picks->count() }}</strong> / {{ $battle->team_size * 2 }}
                            </span>
                        </div>
                    </div>

                    <!-- Live Presence Pause Alert (NO Auto-Pick) -->
                    @if($isDraftPaused)
                        <div class="p-3.5 rounded-xl bg-amber-500/15 border border-amber-400/50 text-amber-200 text-xs sm:text-sm font-bold flex items-center gap-2.5">
                            <span class="text-lg">⏸️</span>
                            <span>{{ $otherCaptainName }} left the screen — Draft paused until they return... (Auto-pick is disabled)</span>
                        </div>
                    @endif
                </section>

                <!-- Swapping Active Banner -->
                @if($swappingPick)
                    <div class="p-4 rounded-2xl bg-amber-500/15 border border-amber-400 text-amber-100 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                        <div class="text-xs sm:text-sm font-bold">
                            🔄 Replacing benched player <strong class="text-white">{{ $swappingPick->player_name }}</strong> — click <strong class="text-emerald-300">Swap In</strong> on any unpicked <span class="text-emerald-300">● Playing</span> player below!
                        </div>
                        <button type="button" wire:click="cancelSwap" class="px-3 py-1.5 rounded-xl bg-slate-900 text-slate-200 text-xs font-bold shrink-0">
                            Cancel Swap
                        </button>
                    </div>
                @endif

                <!-- Selected Squads Tracker (Team 1 vs Team 2) -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    @foreach([
                        ['title' => "Team 1 — {$challengerTitle}", 'picks' => $challengerPicks, 'userId' => $battle->challenger_id, 'accent' => 'amber'],
                        ['title' => "Team 2 — {$opponentTitle}", 'picks' => $opponentPicks, 'userId' => $battle->opponent_id, 'accent' => 'cyan'],
                    ] as $side)
                        <div class="rounded-2xl bg-slate-900/90 border border-slate-800 p-4 space-y-3">
                            <div class="flex items-center justify-between pb-2 border-b border-slate-800">
                                <h3 class="text-xs font-black uppercase tracking-wider {{ $side['accent'] === 'amber' ? 'text-amber-400' : 'text-cyan-400' }}">{{ $side['title'] }}</h3>
                                <span class="text-xs font-mono font-bold text-slate-300">{{ $side['picks']->count() }} / {{ $battle->team_size }} Picked</span>
                            </div>
                            <div class="space-y-2">
                                @forelse($side['picks'] as $p)
                                    @php
                                        $isOwner = $currentUser && (int) $currentUser->id === (int) $side['userId'];
                                        $isBenchedAfterXi = $playingXiAnnounced && !$p->is_playing_xi;
                                    @endphp
                                    <div class="p-2.5 rounded-xl bg-slate-950 border {{ $isBenchedAfterXi ? 'border-rose-500/50' : 'border-slate-800' }} flex items-center justify-between gap-2">
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <span class="text-xs font-bold text-white truncate">{{ $p->player_name }}</span>
                                                @if($playingXiAnnounced && $p->is_playing_xi)
                                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-black bg-emerald-500/20 text-emerald-300 border border-emerald-500/30">● Playing</span>
                                                @elseif($isBenchedAfterXi)
                                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-black bg-rose-500/20 text-rose-300 border border-rose-500/30">BENCHED</span>
                                                @endif
                                            </div>
                                            <div class="text-[10px] text-slate-400">{{ $p->player_team }} • {{ $p->player_role ?: 'PLAYER' }}</div>
                                        </div>
                                        @if($isOwner && $isBenchedAfterXi && $isBeforeLock)
                                            <button
                                                type="button"
                                                wire:click="startSwap({{ $p->id }})"
                                                class="px-2.5 py-1 rounded-lg bg-amber-400 hover:bg-amber-300 text-slate-950 text-[10px] font-black shrink-0 transition"
                                            >
                                                🔄 Replace Now
                                            </button>
                                        @else
                                            <span class="text-[10px] font-mono text-slate-500 shrink-0">Pick #{{ $p->pick_order }}</span>
                                        @endif
                                    </div>
                                @empty
                                    <p class="text-xs text-slate-500 py-3 text-center">No players picked yet.</p>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>

                <!-- Two-Column Full Squad Picker (Team A & Team B) -->
                <section class="rounded-2xl bg-slate-900/90 border border-slate-800 p-4 sm:p-6 space-y-4">
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2">
                        <div>
                            <h2 class="text-sm sm:text-base font-black text-white">🏏 Full Match Squads</h2>
                            <p class="text-[11px] text-slate-400">
                                @if($playingXiAnnounced)
                                    <span class="text-emerald-400 font-bold">● Playing XI Announced!</span> Pick confirmed starters or swap benched players before lock.
                                @else
                                    Showing complete squads. Once Playing XI is announced (~25m before match), <span class="text-emerald-400 font-bold">● Playing</span> badges will appear automatically.
                                @endif
                            </p>
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                        @foreach(['team_a' => 'amber', 'team_b' => 'cyan'] as $teamKey => $accent)
                            @php
                                $teamBlock = $squadsData[$teamKey] ?? ['name' => 'Team', 'players' => []];
                            @endphp
                            <div class="space-y-2.5">
                                <div class="pb-2 border-b border-slate-800 flex items-center justify-between">
                                    <h3 class="text-xs sm:text-sm font-black uppercase tracking-wider {{ $accent === 'amber' ? 'text-amber-400' : 'text-cyan-400' }}">{{ $teamBlock['name'] }}</h3>
                                    <span class="text-[11px] text-slate-500">{{ count($teamBlock['players']) }} Players</span>
                                </div>

                                @forelse($teamBlock['players'] as $idx => $player)
                                    @php
                                        $pickKey = $player['norm_name'] . '|' . mb_strtolower(trim($player['team']));
                                        $pickedInfo = $pickedMap[$pickKey] ?? null;
                                        $canSwapThis = $swappingPick && $player['is_playing_xi'] && $isBeforeLock && !$pickedInfo;
                                    @endphp

                                    @if($pickedInfo)
                                        <!-- Already Picked (Locked) Card -->
                                        <div class="p-3 rounded-xl bg-slate-950 border border-slate-800 opacity-60 cursor-not-allowed flex items-center justify-between gap-2.5">
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    <span class="text-xs font-bold text-slate-300 truncate">{{ $player['name'] }}</span>
                                                    <span class="px-1.5 py-0.5 rounded bg-slate-800 text-[9px] font-mono font-bold text-slate-400">{{ $player['role'] }}</span>
                                                    @if($player['is_playing_xi'])
                                                        <span class="px-1.5 py-0.5 rounded bg-emerald-500/15 border border-emerald-500/30 text-[9px] font-black text-emerald-300">● Playing</span>
                                                    @endif
                                                </div>
                                            </div>
                                            <span class="px-2.5 py-1 rounded-lg bg-slate-900 border border-slate-700 text-[10px] font-bold text-slate-400 shrink-0">
                                                🔒 Picked by {{ $pickedInfo['captain_title'] }}
                                            </span>
                                        </div>
                                    @elseif($canSwapThis)
                                        <!-- Available for Bench Swap -->
                                        <div class="p-3 rounded-xl bg-slate-900 border border-emerald-500/60 hover:border-emerald-400 flex items-center justify-between gap-2.5 transition">
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    <span class="text-xs font-bold text-white truncate">{{ $player['name'] }}</span>
                                                    <span class="px-1.5 py-0.5 rounded bg-slate-800 text-[9px] font-mono font-bold text-slate-300">{{ $player['role'] }}</span>
                                                    <span class="px-1.5 py-0.5 rounded bg-emerald-500/20 border border-emerald-500/40 text-[9px] font-black text-emerald-300">● Playing</span>
                                                </div>
                                            </div>
                                            <button
                                                type="button"
                                                wire:click="confirmSwapPlayer('{{ $teamKey }}', {{ $idx }})"
                                                wire:loading.attr="disabled"
                                                class="px-3 py-1.5 rounded-lg bg-emerald-500 hover:bg-emerald-400 disabled:opacity-50 text-slate-950 text-[11px] font-black shrink-0 transition"
                                            >
                                                🔄 Swap In
                                            </button>
                                        </div>
                                    @elseif($isMyTurn)
                                        <!-- Available Player (Your Turn) -->
                                        <div class="p-3 rounded-xl bg-slate-900 border border-slate-700 hover:border-emerald-400 cursor-pointer flex items-center justify-between gap-2.5 transition shadow-sm">
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    <span class="text-xs sm:text-sm font-bold text-white truncate">{{ $player['name'] }}</span>
                                                    <span class="px-1.5 py-0.5 rounded bg-slate-800 text-[9px] font-mono font-bold text-slate-300">{{ $player['role'] }}</span>
                                                    @if($player['is_playing_xi'])
                                                        <span class="px-1.5 py-0.5 rounded bg-emerald-500/20 border border-emerald-500/40 text-[9px] font-black text-emerald-300">● Playing</span>
                                                    @endif
                                                </div>
                                            </div>
                                            <button
                                                type="button"
                                                wire:click="pickSquadPlayer('{{ $teamKey }}', {{ $idx }})"
                                                wire:loading.attr="disabled"
                                                class="px-3.5 py-1.5 rounded-lg bg-emerald-500 hover:bg-emerald-400 disabled:opacity-50 text-slate-950 text-[11px] font-black shrink-0 shadow-sm shadow-emerald-500/20 transition active:scale-95"
                                            >
                                                + Pick
                                            </button>
                                        </div>
                                    @else
                                        <!-- Available Player (Opponent's Turn / Paused) -->
                                        <div class="p-3 rounded-xl bg-slate-950/90 border border-slate-800 flex items-center justify-between gap-2.5">
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-1.5 flex-wrap">
                                                    <span class="text-xs font-bold text-slate-200 truncate">{{ $player['name'] }}</span>
                                                    <span class="px-1.5 py-0.5 rounded bg-slate-800 text-[9px] font-mono font-bold text-slate-300">{{ $player['role'] }}</span>
                                                    @if($player['is_playing_xi'])
                                                        <span class="px-1.5 py-0.5 rounded bg-emerald-500/20 border border-emerald-500/40 text-[9px] font-black text-emerald-300">● Playing</span>
                                                    @endif
                                                </div>
                                            </div>
                                            <span class="px-2.5 py-1 rounded-lg bg-slate-900 border border-slate-800 text-slate-400 text-[10px] font-semibold shrink-0">
                                                {{ $isDraftPaused ? 'Paused' : 'Waiting Turn' }}
                                            </span>
                                        </div>
                                    @endif
                                @empty
                                    <div class="p-4 rounded-xl bg-slate-950 border border-slate-800 text-xs text-slate-500 text-center">
                                        Squad data not cached yet for {{ $teamBlock['name'] }}.
                                    </div>
                                @endforelse
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            {{-- ===================================================================== --}}
            {{-- STATE 3 & 4: LOCKED LIVE & SETTLED PUBLIC SEO SCORECARD             --}}
            {{-- ===================================================================== --}}
            @if(in_array($battle->status, ['locked_live', 'settled'], true))
                {{-- Pre-Lock Bench Swap Panel (if draft finished early before Playing XI & lock) --}}
                @if($battle->status === 'locked_live' && $isBeforeLock && $playingXiAnnounced && $isParticipant)
                    @php
                        $myBenched = $battle->picks->where('user_id', $currentUser->id)->where('is_playing_xi', false);
                    @endphp
                    @if($myBenched->isNotEmpty())
                        <section class="rounded-2xl bg-amber-500/10 border border-amber-400/50 p-4 space-y-3">
                            <div class="text-xs sm:text-sm font-black text-amber-300">
                                ⚠️ Playing XI is Out! You have {{ $myBenched->count() }} BENCHED player(s) — Replace before lock ({{ $battle->locks_at?->copy()->timezone('Asia/Kolkata')->format('h:i A') }} IST)!
                            </div>
                            @if($swappingPick)
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-3 pt-2">
                                    @foreach(['team_a', 'team_b'] as $teamKey)
                                        @php $tb = $squadsData[$teamKey] ?? ['name' => 'Team', 'players' => []]; @endphp
                                        <div class="space-y-1.5">
                                            <div class="text-xs font-bold text-slate-300">{{ $tb['name'] }} (● Playing)</div>
                                            @foreach($tb['players'] as $idx => $pl)
                                                @php $pk = $pl['norm_name'] . '|' . mb_strtolower(trim($pl['team'])); @endphp
                                                @if($pl['is_playing_xi'] && !isset($pickedMap[$pk]))
                                                    <div class="p-2 rounded-xl bg-slate-950 border border-slate-800 flex items-center justify-between gap-2">
                                                        <span class="text-xs font-bold text-white">{{ $pl['name'] }} <span class="text-emerald-400 text-[10px]">● Playing</span></span>
                                                        <button type="button" wire:click="confirmSwapPlayer('{{ $teamKey }}', {{ $idx }})" class="px-2.5 py-1 rounded-lg bg-emerald-500 text-slate-950 text-[10px] font-black">
                                                            Swap In
                                                        </button>
                                                    </div>
                                                @endif
                                            @endforeach
                                        </div>
                                    @endforeach
                                </div>
                            @endif
                        </section>
                    @endif
                @endif

                {{-- Winner Celebration & Social Winning Card (Settled) --}}
                @if($battle->status === 'settled')
                    <section class="rounded-2xl bg-gradient-to-br from-amber-500/20 via-slate-900 to-emerald-500/20 border border-amber-400/50 p-5 sm:p-7 text-center space-y-4 shadow-2xl">
                        <div class="text-5xl animate-bounce">🏆</div>
                        <div>
                            <div class="text-xs font-black uppercase tracking-widest text-emerald-300 mb-1">
                                Congratulations Captain!
                            </div>
                            <h2 class="text-xl sm:text-3xl font-black text-white">
                                {{ $winnerBannerText }}
                            </h2>
                            @if((int) $battle->run_margin > 0 && $winnerCaptainName)
                                <p class="text-xs sm:text-sm text-amber-300 font-bold mt-1.5">
                                    💎 +{{ number_format((int) $battle->run_margin) }} CricGems credited to {{ $winnerCaptainName }} (1 Run = 1 CricGem)
                                </p>
                            @endif
                        </div>

                        <!-- Social Media Shareable Winning Card Preview + HTML5 Canvas Generator -->
                        <div
                            class="max-w-md mx-auto pt-2 space-y-4"
                            x-data="{
                                generating: false,
                                cardData: @js([
                                    'matchTitle' => $battle->match_title,
                                    'mode' => $battle->modeLabel() . ' ' . $battle->match_format,
                                    'winnerName' => $winnerCaptainName ?: 'Draw / Tied',
                                    'loserName' => $loserCaptainName ?: $opponentTitle,
                                    'winnerRuns' => $winnerRuns,
                                    'loserRuns' => $loserRuns,
                                    'margin' => (int) $battle->run_margin,
                                    'bannerText' => $winnerBannerText,
                                    'url' => $battleUrl,
                                ]),
                                buildCanvasDataUrl() {
                                    const c = document.createElement('canvas');
                                    c.width = 1080;
                                    c.height = 1080;
                                    const ctx = c.getContext('2d');

                                    const bg = ctx.createLinearGradient(0, 0, 1080, 1080);
                                    bg.addColorStop(0, '#020617');
                                    bg.addColorStop(0.55, '#0f172a');
                                    bg.addColorStop(1, '#064e3b');
                                    ctx.fillStyle = bg;
                                    ctx.fillRect(0, 0, 1080, 1080);

                                    ctx.strokeStyle = '#f59e0b';
                                    ctx.lineWidth = 10;
                                    ctx.strokeRect(36, 36, 1008, 1008);

                                    ctx.textAlign = 'center';
                                    ctx.fillStyle = '#fbbf24';
                                    ctx.font = 'bold 34px system-ui, -apple-system, sans-serif';
                                    ctx.fillText('⚡ CRICWIRE CAPTAIN BATTLE', 540, 120);

                                    ctx.font = '120px system-ui, -apple-system, sans-serif';
                                    ctx.fillText('🏆', 540, 270);

                                    ctx.fillStyle = '#34d399';
                                    ctx.font = 'bold 38px system-ui, -apple-system, sans-serif';
                                    ctx.fillText('CONGRATULATIONS CAPTAIN!', 540, 350);

                                    ctx.fillStyle = '#ffffff';
                                    ctx.font = '900 46px system-ui, -apple-system, sans-serif';
                                    ctx.fillText(this.cardData.winnerName, 540, 430);

                                    ctx.fillStyle = '#cbd5e1';
                                    ctx.font = 'bold 30px system-ui, -apple-system, sans-serif';
                                    ctx.fillText(this.cardData.matchTitle + ' (' + this.cardData.mode + ')', 540, 505);

                                    // Score Box
                                    ctx.fillStyle = 'rgba(2, 6, 23, 0.85)';
                                    ctx.fillRect(110, 560, 860, 220);
                                    ctx.strokeStyle = '#334155';
                                    ctx.lineWidth = 3;
                                    ctx.strokeRect(110, 560, 860, 220);

                                    ctx.fillStyle = '#fbbf24';
                                    ctx.font = '900 76px monospace';
                                    ctx.fillText(this.cardData.winnerRuns + '  vs  ' + this.cardData.loserRuns, 540, 665);

                                    ctx.fillStyle = '#34d399';
                                    ctx.font = 'bold 34px system-ui, -apple-system, sans-serif';
                                    const marginLabel = this.cardData.margin > 0
                                        ? ('WON BY ' + this.cardData.margin + ' RUNS • +' + this.cardData.margin + ' 💎')
                                        : 'TIED BATTLE';
                                    ctx.fillText(marginLabel, 540, 735);

                                    ctx.fillStyle = '#94a3b8';
                                    ctx.font = 'bold 28px system-ui, -apple-system, sans-serif';
                                    ctx.fillText('Play Squad Runs Battles free on', 540, 910);

                                    ctx.fillStyle = '#fbbf24';
                                    ctx.font = '900 42px system-ui, -apple-system, sans-serif';
                                    ctx.fillText('cricwire.in/games', 540, 965);

                                    return c.toDataURL('image/png');
                                },
                                downloadCard() {
                                    const url = this.buildCanvasDataUrl();
                                    const a = document.createElement('a');
                                    a.href = url;
                                    a.download = 'cricwire-captain-win.png';
                                    document.body.appendChild(a);
                                    a.click();
                                    a.remove();
                                },
                                async shareCard() {
                                    this.generating = true;
                                    try {
                                        const dataUrl = this.buildCanvasDataUrl();
                                        const res = await fetch(dataUrl);
                                        const blob = await res.blob();
                                        const file = new File([blob], 'cricwire-captain-win.png', { type: 'image/png' });
                                        if (navigator.canShare && navigator.canShare({ files: [file] })) {
                                            await navigator.share({
                                                title: this.cardData.bannerText,
                                                text: `🏆 ${this.cardData.bannerText} on Cricwire! Play at ${this.cardData.url}`,
                                                files: [file],
                                                url: this.cardData.url,
                                            });
                                        } else if (navigator.share) {
                                            await navigator.share({
                                                title: this.cardData.bannerText,
                                                text: `🏆 ${this.cardData.bannerText} on Cricwire!`,
                                                url: this.cardData.url,
                                            });
                                        } else {
                                            this.downloadCard();
                                        }
                                    } catch (e) {
                                        this.downloadCard();
                                    } finally {
                                        this.generating = false;
                                    }
                                }
                            }"
                        >
                            <!-- Visual Winning Card -->
                            <div id="winning-battle-card" class="rounded-2xl bg-gradient-to-br from-slate-950 via-slate-900 to-emerald-950 border-2 border-amber-400 p-5 text-center shadow-2xl space-y-2.5">
                                <div class="text-[10px] font-black uppercase tracking-widest text-amber-400">⚡ Cricwire Captain Battle</div>
                                <div class="text-3xl">🏆</div>
                                <div class="text-xs font-black uppercase tracking-wider text-emerald-300">Congratulations Captain!</div>
                                <div class="text-lg font-black text-white">{{ $winnerCaptainName ?: 'Match Tied' }}</div>
                                <div class="text-[11px] text-slate-300">{{ $battle->match_title }} • {{ $battle->modeLabel() }} {{ $battle->match_format }}</div>
                                <div class="py-2.5 px-4 rounded-xl bg-slate-950/90 border border-slate-800 flex items-center justify-around font-mono">
                                    <div>
                                        <div class="text-[10px] text-slate-400">{{ $challengerTitle }}</div>
                                        <div class="text-xl font-black text-amber-300">{{ $battle->challenger_runs }}</div>
                                    </div>
                                    <div class="text-xs font-black text-slate-500">VS</div>
                                    <div>
                                        <div class="text-[10px] text-slate-400">{{ $opponentTitle }}</div>
                                        <div class="text-xl font-black text-cyan-300">{{ $battle->opponent_runs }}</div>
                                    </div>
                                </div>
                                <div class="text-xs font-black text-emerald-300">
                                    {{ (int) $battle->run_margin > 0 ? "Won by {$battle->run_margin} Runs • +{$battle->run_margin} 💎" : 'Tied Match' }}
                                </div>
                                <div class="text-[11px] font-mono font-bold text-amber-400 pt-1">cricwire.in</div>
                            </div>

                            <!-- Share Buttons -->
                            <div class="flex flex-wrap items-center justify-center gap-2">
                                <button
                                    type="button"
                                    @click="shareCard()"
                                    :disabled="generating"
                                    class="px-4 py-2.5 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 text-xs font-black uppercase tracking-wider transition shadow-lg"
                                >
                                    📲 Share Winning Card
                                </button>
                                <button
                                    type="button"
                                    @click="downloadCard()"
                                    class="px-4 py-2.5 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-white text-xs font-bold transition"
                                >
                                    ⬇️ Download PNG
                                </button>
                                <a
                                    href="https://wa.me/?text={{ urlencode("🏆 {$winnerBannerText} in {$battle->match_title} ({$battle->modeLabel()}) on Cricwire! View scorecard: {$battleUrl}") }}"
                                    target="_blank"
                                    rel="noopener"
                                    class="px-3.5 py-2.5 rounded-xl bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold transition"
                                >
                                    💬 WhatsApp
                                </a>
                                <a
                                    href="https://twitter.com/intent/tweet?text={{ urlencode("🏆 {$winnerBannerText} in {$battle->match_title} on @cricwire!") }}&url={{ urlencode($battleUrl) }}"
                                    target="_blank"
                                    rel="noopener"
                                    class="px-3.5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 border border-slate-700 text-slate-200 text-xs font-bold transition"
                                >
                                    𝕏 Share
                                </a>
                            </div>
                        </div>
                    </section>
                @endif

                <!-- Side-by-Side Battle Runs Scorecard -->
                <div class="grid grid-cols-1 md:grid-cols-2 gap-5">
                    @foreach([
                        ['label' => 'Team 1', 'captain' => $challengerTitle, 'userId' => $battle->challenger_id, 'picks' => $challengerPicks, 'total' => $battle->challenger_runs, 'accent' => 'amber'],
                        ['label' => 'Team 2', 'captain' => $opponentTitle, 'userId' => $battle->opponent_id, 'picks' => $opponentPicks, 'total' => $battle->opponent_runs, 'accent' => 'cyan'],
                    ] as $card)
                        @php
                            $isCardWinner = $battle->status === 'settled' && (int) $battle->winner_id === (int) $card['userId'];
                        @endphp
                        <section class="rounded-2xl bg-slate-900/90 border {{ $isCardWinner ? 'border-emerald-400 ring-1 ring-emerald-400/40' : 'border-slate-800' }} p-4 sm:p-5 space-y-3 shadow-xl">
                            <div class="flex items-center justify-between pb-3 border-b border-slate-800">
                                <div>
                                    <div class="text-[10px] font-bold uppercase tracking-wider text-slate-400 flex items-center gap-1.5">
                                        <span>{{ $card['label'] }}</span>
                                        @if($isCardWinner)
                                            <span class="px-1.5 py-0.5 rounded bg-emerald-500/20 text-emerald-300 font-black">🏆 WINNER</span>
                                        @endif
                                    </div>
                                    <h3 class="text-sm sm:text-base font-black {{ $card['accent'] === 'amber' ? 'text-amber-300' : 'text-cyan-300' }}">{{ $card['captain'] }}</h3>
                                </div>
                                <div class="text-right">
                                    <div class="text-[10px] uppercase font-bold text-slate-400">Total Runs</div>
                                    <div class="text-2xl font-black font-mono text-white">{{ $card['total'] }}</div>
                                </div>
                            </div>

                            <div class="space-y-2">
                                @foreach($card['picks'] as $p)
                                    @php
                                        $isOwner = $currentUser && (int) $currentUser->id === (int) $card['userId'];
                                        $canBenchSwap = $battle->status === 'locked_live' && $isBeforeLock && $playingXiAnnounced && $isOwner && !$p->is_playing_xi;
                                    @endphp
                                    <div class="p-3 rounded-xl bg-slate-950 border {{ $canBenchSwap ? 'border-rose-500/50' : 'border-slate-800' }} flex items-center justify-between gap-3">
                                        <div class="min-w-0">
                                            <div class="flex items-center gap-1.5 flex-wrap">
                                                <span class="text-xs sm:text-sm font-bold text-white truncate">{{ $p->player_name }}</span>
                                                @if($playingXiAnnounced && $p->is_playing_xi)
                                                    <span class="px-1.5 py-0.5 rounded text-[9px] font-black bg-emerald-500/15 text-emerald-300 border border-emerald-500/30">● Playing</span>
                                                @endif
                                            </div>
                                            <div class="text-[11px] text-slate-400 mt-0.5">
                                                {{ $p->player_team }} • {{ $p->player_role ?: 'PLAYER' }}
                                            </div>
                                        </div>

                                        <div class="flex items-center gap-2 shrink-0">
                                            @if($canBenchSwap)
                                                <button
                                                    type="button"
                                                    wire:click="startSwap({{ $p->id }})"
                                                    class="px-2.5 py-1 rounded-lg bg-amber-400 text-slate-950 text-[10px] font-black"
                                                >
                                                    🔄 BENCHED — Replace
                                                </button>
                                            @endif

                                            @if($p->did_not_bat)
                                                <span class="px-2.5 py-1 rounded-lg bg-slate-800 border border-slate-700 text-[10px] font-mono font-bold text-slate-400">
                                                    DNB - Did Not Bat (0)
                                                </span>
                                            @else
                                                <span class="px-3 py-1 rounded-lg bg-emerald-500/15 border border-emerald-500/30 text-sm font-mono font-black text-emerald-300">
                                                    {{ $p->runs_scored }} Runs
                                                </span>
                                            @endif
                                        </div>
                                    </div>
                                @endforeach
                            </div>
                        </section>
                    @endforeach
                </div>
            @endif

            {{-- ===================================================================== --}}
            {{-- STATE 5: DECLINED / EXPIRED INCOMPLETE                                --}}
            {{-- ===================================================================== --}}
            @if(in_array($battle->status, ['declined', 'expired_incomplete'], true))
                <section class="rounded-2xl bg-slate-900/90 border border-slate-800 p-6 text-center space-y-3">
                    <div class="text-4xl">{{ $battle->status === 'declined' ? '🚫' : '⏱️' }}</div>
                    <h2 class="text-lg font-black text-white">
                        {{ $battle->status === 'declined' ? 'Challenge Declined' : 'Draft Expired Incomplete' }}
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-400 max-w-md mx-auto">
                        {{ $battle->status === 'declined'
                            ? 'This Captain vs Captain battle challenge was declined. 0 CricGems were deducted.'
                            : 'The Match Start - 5 Mins lock deadline arrived before both Captains completed all picks. 0 CricGems were deducted.' }}
                    </p>
                    <a href="{{ route('games.index') }}" wire:navigate class="inline-flex items-center gap-2 px-5 py-2.5 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 text-xs font-black uppercase tracking-wider transition">
                        Back to Games Hub →
                    </a>
                </section>
            @endif
        </main>
    </div>

    <x-footer />
    <x-bottom-nav />
</div>
