<div class="min-h-screen bg-slate-950 text-slate-100 pb-24 md:pb-8 flex flex-col justify-between">
    <div>
        <x-header />

        <main class="max-w-7xl mx-auto w-full px-3 sm:px-6 lg:px-8 py-5 sm:py-8 space-y-6">
            <!-- Hero Header & Captain Identity -->
            <section class="relative rounded-2xl bg-gradient-to-br from-slate-900 via-[#0F172A] to-amber-950/25 border border-white/10 p-4 sm:p-6 shadow-2xl overflow-hidden">
                <div class="absolute -top-16 -right-16 w-48 h-48 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>
                <div class="relative flex flex-col lg:flex-row lg:items-center lg:justify-between gap-4">
                    <div>
                        <div class="inline-flex items-center gap-2 px-2.5 py-1 rounded-full bg-amber-500/15 border border-amber-500/30 text-amber-300 text-[10px] font-black uppercase tracking-wider mb-2">
                            <span>🎮</span> Cricwire Arena • Game #1
                        </div>
                        <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight">
                            ⚔️ Captain vs Captain — Squad Runs Battle
                        </h1>
                        <p class="text-xs sm:text-sm text-slate-300 mt-1 max-w-2xl">
                            Challenge a friend on any upcoming <strong class="text-amber-300">T20 or ODI</strong> match. Draft <strong class="text-emerald-300">5v5</strong> or <strong class="text-emerald-300">3v3</strong> live turn-by-turn, swap benched players before lock, and win <strong class="text-amber-300">1 Batting Run = 1 CricGem</strong>!
                        </p>
                    </div>

                    @if($user)
                        <div class="bg-slate-950/80 border border-slate-800 rounded-2xl p-3.5 sm:p-4 min-w-[260px] space-y-2.5">
                            <div class="flex items-center justify-between gap-3">
                                <div class="min-w-0">
                                    <div class="text-[10px] uppercase font-bold tracking-wider text-slate-400">Your Captain Tag</div>
                                    <div class="text-sm font-black text-amber-300 truncate">
                                        {{ $user->captainTitle() }}
                                    </div>
                                </div>
                                <span class="px-2.5 py-1 rounded-xl text-xs font-mono font-black shrink-0 {{ (int) $user->cric_gems >= 100 ? 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/30' : 'bg-rose-500/15 text-rose-300 border border-rose-500/30' }}">
                                    💎 {{ number_format((int) $user->cric_gems) }}
                                </span>
                            </div>

                            @if(!$editingUsername)
                                <button
                                    type="button"
                                    wire:click="toggleUsernameEditor"
                                    class="w-full py-1.5 px-3 rounded-xl bg-slate-800 hover:bg-slate-700 border border-slate-700 text-[11px] font-bold text-slate-200 transition flex items-center justify-center gap-1.5"
                                >
                                    <span>✏️</span>
                                    <span>{{ $user->captain_username ? 'Update Captain Username' : 'Set Custom Captain Username' }}</span>
                                </button>
                            @else
                                <form wire:submit="saveCaptainUsername" class="space-y-2">
                                    <input
                                        type="text"
                                        wire:model="captainUsernameInput"
                                        placeholder="e.g. KingKohli_99"
                                        maxlength="24"
                                        class="w-full bg-slate-900 border border-amber-500/40 rounded-xl px-3 py-1.5 text-xs text-white placeholder-slate-500 focus:outline-none focus:ring-1 focus:ring-amber-400"
                                    >
                                    <div class="flex items-center gap-2">
                                        <button type="submit" class="flex-1 py-1.5 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 text-xs font-black transition">
                                            Save
                                        </button>
                                        <button type="button" wire:click="toggleUsernameEditor" class="px-3 py-1.5 rounded-xl bg-slate-800 text-slate-300 text-xs font-bold">
                                            Cancel
                                        </button>
                                    </div>
                                </form>
                            @endif

                            @if($usernameMessage)
                                <div class="text-[11px] text-emerald-300 font-semibold">{{ $usernameMessage }}</div>
                            @endif
                            @if($usernameError)
                                <div class="text-[11px] text-rose-300 font-semibold">{{ $usernameError }}</div>
                            @endif

                            @if((int) $user->cric_gems < 100)
                                <div class="p-2 rounded-xl bg-rose-500/10 border border-rose-500/30 text-[11px] text-rose-200">
                                    ⚠️ Minimum <strong>100 CricGems</strong> required to create or accept a Captain Battle. Earn gems via daily streaks &amp; referrals on <a href="{{ route('more') }}" wire:navigate class="underline text-amber-300 font-bold">/more</a>.
                                </div>
                            @endif
                        </div>
                    @else
                        <a
                            href="{{ route('google.redirect', ['intended' => '/games']) }}"
                            class="inline-flex items-center justify-center gap-2 px-5 py-3 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 text-xs font-black uppercase tracking-wider shadow-lg shadow-amber-500/20 transition shrink-0"
                        >
                            <span>Sign In with Google &amp; Get +500 💎</span>
                            <span>→</span>
                        </a>
                    @endif
                </div>
            </section>

            <!-- Action Alerts -->
            @if($challengeError)
                <div class="p-3.5 rounded-2xl bg-rose-500/15 border border-rose-500/40 text-rose-200 text-xs font-bold">
                    ⚠️ {{ $challengeError }}
                </div>
            @endif
            @if($challengeSuccess)
                <div class="p-3.5 rounded-2xl bg-emerald-500/15 border border-emerald-500/40 text-emerald-200 text-xs font-bold">
                    ✅ {{ $challengeSuccess }}
                </div>
            @endif

            <!-- Incoming Battle Challenges -->
            @if($user && $incomingChallenges->isNotEmpty())
                <section class="rounded-2xl bg-gradient-to-r from-amber-950/40 via-slate-900 to-emerald-950/30 border border-amber-500/40 p-4 sm:p-5 shadow-xl space-y-3">
                    <div class="flex items-center justify-between">
                        <h2 class="text-sm font-black text-amber-300 uppercase tracking-wider flex items-center gap-2">
                            <span>⚔️</span> Incoming Captain Challenges ({{ $incomingChallenges->count() }})
                        </h2>
                        <span class="text-[11px] text-slate-400">Accept to trigger Coin Toss &amp; Draft</span>
                    </div>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-3">
                        @foreach($incomingChallenges as $inc)
                            <div class="rounded-xl bg-slate-950/90 border border-amber-500/30 p-3.5 flex flex-col justify-between gap-3">
                                <div>
                                    <div class="flex items-center justify-between gap-2">
                                        <span class="text-xs font-black text-amber-300">{{ $inc->challenger?->captainTitle() ?? 'Captain' }}</span>
                                        <span class="px-2 py-0.5 rounded-md text-[10px] font-black bg-emerald-500/15 text-emerald-300 border border-emerald-500/30">
                                            {{ $inc->modeLabel() }} • {{ $inc->match_format }}
                                        </span>
                                    </div>
                                    <div class="text-sm font-black text-white mt-1">{{ $inc->match_title }}</div>
                                    <div class="text-[11px] text-slate-400 mt-0.5">
                                        ⏰ Locks at {{ $inc->locks_at?->copy()->timezone('Asia/Kolkata')->format('d M, h:i A') }} IST
                                    </div>
                                </div>
                                <div class="flex items-center gap-2">
                                    <button
                                        type="button"
                                        wire:click="acceptChallenge({{ $inc->id }})"
                                        class="flex-1 py-2 px-3 rounded-xl bg-gradient-to-r from-emerald-500 to-teal-400 hover:from-emerald-400 hover:to-teal-300 text-slate-950 text-xs font-black uppercase tracking-wider transition"
                                    >
                                        ⚔️ Accept &amp; Join Draft
                                    </button>
                                    <button
                                        type="button"
                                        wire:click="declineChallenge({{ $inc->id }})"
                                        class="py-2 px-3 rounded-xl bg-slate-800 hover:bg-rose-600/30 text-slate-300 hover:text-rose-200 text-xs font-bold transition"
                                    >
                                        Decline
                                    </button>
                                </div>
                            </div>
                        @endforeach
                    </div>
                </section>
            @endif

            <!-- 3-Step Guided Challenge Creation Form -->
            @if($user)
                @php
                    $selectedFriendObj = $selectedOpponentId ? $friends->firstWhere('id', $selectedOpponentId) : null;
                    $selectedFriendLabel = $selectedFriendObj
                        ? ($selectedFriendObj->captainTitle() . ' (' . $selectedFriendObj->player_code . ')')
                        : '';
                    $selectedMatchObj = $selectedMatchId ? collect($eligibleMatches)->firstWhere('match_id', (string) $selectedMatchId) : null;
                    $canSubmitChallenge = $selectedFriendObj && $selectedMatchObj && (int) $user->cric_gems >= 100 && (int) ($selectedFriendObj->cric_gems ?? 0) >= 100;
                @endphp
                <section
                    class="rounded-2xl bg-slate-900/90 border border-slate-800 p-4 sm:p-6 shadow-xl space-y-6"
                    x-data="{
                        friendPickerOpen: false,
                        friendQuery: '',
                        matchDropdownOpen: false,
                    }"
                    @keydown.escape.window="friendPickerOpen = false; matchDropdownOpen = false"
                >
                    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-2 pb-4 border-b border-slate-800">
                        <div>
                            <h2 class="text-base sm:text-lg font-black text-white flex items-center gap-2">
                                <span>⚔️</span> Start a New Captain Battle
                            </h2>
                            <p class="text-xs text-slate-400 mt-0.5">
                                Both Captains must hold 100+ CricGems. Only T20 &amp; ODI upcoming matches are eligible.
                            </p>
                        </div>
                        <span class="self-start sm:self-auto px-3 py-1 rounded-full bg-slate-800 border border-slate-700 text-[11px] font-bold text-amber-300">
                            1 Run = 1 CricGem
                        </span>
                    </div>

                    <!-- FIELD 1: Challenge Your Friend (Input + Select Button + Searchable Modal) -->
                    <div class="space-y-2.5">
                        <div class="flex items-center justify-between gap-2">
                            <label class="text-xs font-black uppercase tracking-wider text-amber-400">
                                1. Challenge Your Friend
                            </label>
                            <a href="{{ route('more') }}" wire:navigate class="text-[11px] font-bold text-cyan-400 hover:text-cyan-300">
                                + Add Friends on /more →
                            </a>
                        </div>

                        @if($friends->isEmpty())
                            <div class="p-4 rounded-xl bg-slate-950/80 border border-slate-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <div>
                                    <p class="text-xs font-bold text-slate-200">You haven't connected with any friends yet.</p>
                                    <p class="text-[11px] text-slate-400 mt-0.5">Add a friend by CW Player ID or QR code first to challenge them.</p>
                                </div>
                                <a href="{{ route('more') }}" wire:navigate class="inline-flex items-center justify-center gap-1.5 px-4 py-2.5 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 text-xs font-black shrink-0 transition">
                                    ➕ Add Friends on /more
                                </a>
                            </div>
                        @else
                            <div class="flex items-center gap-2">
                                <div class="relative flex-1 min-w-0">
                                    <input
                                        type="text"
                                        readonly
                                        @click="friendPickerOpen = true"
                                        value="{{ $selectedFriendLabel }}"
                                        placeholder="Tap Select to choose a friend..."
                                        class="w-full cursor-pointer bg-slate-950 border {{ $selectedFriendObj ? 'border-emerald-500/60 text-white font-bold' : 'border-slate-700 text-slate-300' }} rounded-xl px-3.5 py-3 pr-24 text-xs sm:text-sm placeholder-slate-500 focus:outline-none focus:border-amber-400 transition"
                                    >
                                    @if($selectedFriendObj)
                                        <span class="absolute right-3 top-1/2 -translate-y-1/2 px-2 py-0.5 rounded-md bg-emerald-500/20 border border-emerald-500/40 text-emerald-300 text-[10px] font-black pointer-events-none">
                                            ✓ Selected
                                        </span>
                                    @endif
                                </div>
                                <button
                                    type="button"
                                    @click="friendPickerOpen = true"
                                    class="px-5 py-3 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 font-black text-xs uppercase tracking-wider shrink-0 shadow-md shadow-amber-500/15 transition active:scale-95"
                                >
                                    Select
                                </button>
                            </div>

                            @if($selectedFriendObj && (int) $selectedFriendObj->cric_gems < 100)
                                <div class="text-[11px] font-semibold text-rose-300">
                                    ⚠️ {{ $selectedFriendObj->captainTitle() }} currently has {{ number_format((int) $selectedFriendObj->cric_gems) }} 💎 (100+ 💎 required to accept).
                                </div>
                            @endif

                            <!-- Searchable Friend Picker Modal -->
                            <div
                                x-show="friendPickerOpen"
                                x-cloak
                                x-transition.opacity
                                class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-950/80 backdrop-blur-sm"
                                @click.self="friendPickerOpen = false"
                            >
                                <div class="w-full max-w-md rounded-2xl bg-slate-900 border border-slate-700 shadow-2xl overflow-hidden">
                                    <div class="px-4 py-3 border-b border-slate-800 flex items-center justify-between bg-slate-950/60">
                                        <h3 class="text-sm font-black text-white flex items-center gap-2">
                                            <span>🤝</span> Select a Friend to Challenge
                                        </h3>
                                        <button type="button" @click="friendPickerOpen = false" class="px-2 py-1 rounded-lg text-slate-400 hover:text-white text-xs font-bold">✕</button>
                                    </div>

                                    <div class="p-3 border-b border-slate-800">
                                        <input
                                            type="text"
                                            x-model="friendQuery"
                                            placeholder="Search friend by Captain name or CW code..."
                                            class="w-full bg-slate-950 border border-slate-700 focus:border-amber-400 rounded-xl px-3.5 py-2 text-xs text-white placeholder-slate-500 focus:outline-none"
                                        >
                                    </div>

                                    <div class="max-h-72 overflow-y-auto p-3 space-y-2">
                                        @foreach($friends as $friend)
                                            @php
                                                $isSelected = (int) $selectedOpponentId === (int) $friend->id;
                                                $friendEligible = (int) $friend->cric_gems >= 100;
                                                $searchText = strtolower($friend->captainTitle() . ' ' . $friend->name . ' ' . $friend->player_code);
                                            @endphp
                                            <button
                                                type="button"
                                                x-show="!friendQuery || @js($searchText).includes(friendQuery.toLowerCase())"
                                                wire:click="selectOpponent({{ $friend->id }})"
                                                @click="friendPickerOpen = false"
                                                class="w-full text-left p-3 rounded-xl border transition flex items-center justify-between gap-2.5 {{ $isSelected ? 'bg-emerald-500/15 border-emerald-400' : 'bg-slate-950 border-slate-800 hover:border-amber-400/60' }}"
                                            >
                                                <div class="flex items-center gap-2.5 min-w-0">
                                                    <x-user-avatar :user="$friend" size="sm" />
                                                    <div class="min-w-0">
                                                        <div class="text-xs font-black text-white truncate">{{ $friend->captainTitle() }}</div>
                                                        <div class="text-[10px] font-mono text-slate-400">{{ $friend->player_code }} • 🏆 {{ (int) $friend->battles_won }} Wins</div>
                                                    </div>
                                                </div>
                                                <div class="flex items-center gap-2 shrink-0">
                                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-mono font-bold {{ $friendEligible ? 'bg-emerald-500/15 text-emerald-300 border border-emerald-500/30' : 'bg-rose-500/15 text-rose-300 border border-rose-500/30' }}">
                                                        {{ number_format((int) $friend->cric_gems) }} 💎
                                                    </span>
                                                    <span class="px-2.5 py-1 rounded-lg text-[10px] font-black {{ $isSelected ? 'bg-emerald-400 text-slate-950' : 'bg-amber-400/20 text-amber-300' }}">
                                                        {{ $isSelected ? '✓ Selected' : 'Select' }}
                                                    </span>
                                                </div>
                                            </button>
                                        @endforeach
                                    </div>
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- FIELD 2: Select Upcoming Match (T20 & ODI Only Dropdown) -->
                    <div class="space-y-2.5 pt-3 border-t border-slate-800/80">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <label class="text-xs font-black uppercase tracking-wider text-amber-400">
                                2. Select Upcoming Match <span class="text-slate-400 font-semibold normal-case">(T20 / ODI Only)</span>
                            </label>
                            <div class="inline-flex rounded-xl bg-slate-950 p-1 border border-slate-800">
                                <button
                                    type="button"
                                    wire:click="setMatchFilter('today')"
                                    class="px-3 py-1 rounded-lg text-[11px] font-bold transition {{ $matchFilter === 'today' ? 'bg-amber-400 text-slate-950' : 'text-slate-400 hover:text-white' }}"
                                >
                                    Today's Matches
                                </button>
                                <button
                                    type="button"
                                    wire:click="setMatchFilter('top10')"
                                    class="px-3 py-1 rounded-lg text-[11px] font-bold transition {{ $matchFilter === 'top10' ? 'bg-amber-400 text-slate-950' : 'text-slate-400 hover:text-white' }}"
                                >
                                    Top 10 Upcoming
                                </button>
                            </div>
                        </div>

                        @if(empty($eligibleMatches))
                            <div class="p-4 rounded-xl bg-slate-950/80 border border-slate-800 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                                <p class="text-xs font-bold text-slate-300">
                                    No eligible T20/ODI matches found for {{ $matchFilter === 'today' ? 'Today (IST)' : 'Top 10 Upcoming' }}.
                                </p>
                                @if($matchFilter === 'today')
                                    <button
                                        type="button"
                                        wire:click="setMatchFilter('top10')"
                                        class="px-4 py-2 rounded-xl bg-amber-400/15 hover:bg-amber-400/25 border border-amber-400/40 text-amber-300 text-xs font-bold transition shrink-0"
                                    >
                                        Switch to Top 10 Upcoming →
                                    </button>
                                @endif
                            </div>
                        @else
                            <div class="relative" @click.outside="matchDropdownOpen = false">
                                <button
                                    type="button"
                                    @click="matchDropdownOpen = !matchDropdownOpen"
                                    class="w-full text-left bg-slate-950 border {{ $selectedMatchObj ? 'border-emerald-500/60' : 'border-slate-700 hover:border-amber-400/60' }} rounded-xl px-4 py-3 flex items-center justify-between gap-3 transition"
                                >
                                    @if($selectedMatchObj)
                                        <div class="min-w-0 flex-1">
                                            <div class="flex items-center gap-2 flex-wrap">
                                                <span class="text-xs sm:text-sm font-black text-white truncate">{{ $selectedMatchObj['match_title'] }}</span>
                                                <span class="px-2 py-0.5 rounded text-[10px] font-black {{ $selectedMatchObj['format'] === 'ODI' ? 'bg-cyan-500/20 text-cyan-300' : 'bg-amber-500/20 text-amber-300' }}">
                                                    {{ $selectedMatchObj['format'] }}
                                                </span>
                                                <span class="px-2 py-0.5 rounded bg-emerald-500/20 border border-emerald-500/40 text-emerald-300 text-[10px] font-black">
                                                    ✓ Selected
                                                </span>
                                            </div>
                                            <div class="text-[11px] text-slate-400 truncate mt-0.5">
                                                {{ $selectedMatchObj['series_name'] }} • {{ $selectedMatchObj['ist_time_label'] }} (Locks at {{ $selectedMatchObj['locks_at_label'] }})
                                            </div>
                                        </div>
                                    @else
                                        <span class="text-xs sm:text-sm text-slate-400 font-medium">
                                            🏏 Choose an upcoming T20 or ODI match...
                                        </span>
                                    @endif
                                    <span class="text-slate-400 text-xs shrink-0 transition-transform" :class="{ 'rotate-180': matchDropdownOpen }">▼</span>
                                </button>

                                <!-- Dropdown Listbox -->
                                <div
                                    x-show="matchDropdownOpen"
                                    x-cloak
                                    x-transition
                                    class="absolute left-0 right-0 mt-2 z-40 rounded-2xl bg-slate-900 border border-slate-700 shadow-2xl max-h-80 overflow-y-auto divide-y divide-slate-800/80"
                                >
                                    @foreach($eligibleMatches as $m)
                                        @php
                                            $mSelected = (string) $selectedMatchId === (string) $m['match_id'];
                                        @endphp
                                        <button
                                            type="button"
                                            wire:click="selectMatch('{{ $m['match_id'] }}')"
                                            @click="matchDropdownOpen = false"
                                            class="w-full text-left px-4 py-3 hover:bg-slate-800/80 transition flex items-center justify-between gap-3 {{ $mSelected ? 'bg-emerald-500/10' : '' }}"
                                        >
                                            <div class="min-w-0">
                                                <div class="flex items-center gap-2 flex-wrap">
                                                    <span class="text-xs sm:text-sm font-black text-white">{{ $m['match_title'] }}</span>
                                                    <span class="px-2 py-0.5 rounded text-[10px] font-black {{ $m['format'] === 'ODI' ? 'bg-cyan-500/20 text-cyan-300 border border-cyan-500/30' : 'bg-amber-500/20 text-amber-300 border border-amber-500/30' }}">
                                                        {{ $m['format'] }}
                                                    </span>
                                                </div>
                                                <div class="text-[11px] text-slate-400 mt-0.5">
                                                    {{ $m['series_name'] }} • {{ $m['ist_time_label'] }} (Locks at {{ $m['locks_at_label'] }})
                                                </div>
                                            </div>
                                            @if($mSelected)
                                                <span class="text-emerald-400 text-xs font-black shrink-0">✓</span>
                                            @endif
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif
                    </div>

                    <!-- FIELD 3: Battle Mode (5v5 or 3v3) & Submit CTA -->
                    <div class="space-y-3 pt-3 border-t border-slate-800/80">
                        <label class="block text-xs font-black uppercase tracking-wider text-amber-400">
                            3. Battle Mode (5v5 or 3v3)
                        </label>
                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
                            <button
                                type="button"
                                wire:click="selectTeamSize(5)"
                                class="p-3.5 rounded-xl border text-left transition flex items-center justify-between {{ $selectedTeamSize === 5 ? 'bg-amber-500/15 border-amber-400 ring-1 ring-amber-400/50' : 'bg-slate-950 border-slate-800 hover:border-slate-600' }}"
                            >
                                <div>
                                    <div class="text-sm font-black text-white">5v5 Squad Battle</div>
                                    <div class="text-[11px] text-slate-400">Pick 5 players each • 1 Run = 1 CricGem</div>
                                </div>
                                <span class="px-2.5 py-1 rounded-lg bg-amber-400/20 text-amber-300 font-mono text-xs font-black">5v5</span>
                            </button>
                            <button
                                type="button"
                                wire:click="selectTeamSize(3)"
                                class="p-3.5 rounded-xl border text-left transition flex items-center justify-between {{ $selectedTeamSize === 3 ? 'bg-amber-500/15 border-amber-400 ring-1 ring-amber-400/50' : 'bg-slate-950 border-slate-800 hover:border-slate-600' }}"
                            >
                                <div>
                                    <div class="text-sm font-black text-white">3v3 Quick Battle</div>
                                    <div class="text-[11px] text-slate-400">Pick 3 players each • 1 Run = 1 CricGem</div>
                                </div>
                                <span class="px-2.5 py-1 rounded-lg bg-cyan-400/20 text-cyan-300 font-mono text-xs font-black">3v3</span>
                            </button>
                        </div>

                        <div class="pt-2 space-y-2">
                            <button
                                type="button"
                                wire:click="sendChallenge"
                                wire:loading.attr="disabled"
                                @disabled(!$canSubmitChallenge)
                                title="{{ !$selectedFriendObj ? 'Select a friend first' : (!$selectedMatchObj ? 'Select an upcoming T20/ODI match first' : ((int) $user->cric_gems < 100 ? '100+ CricGems required' : 'Send Battle Challenge')) }}"
                                class="w-full py-3.5 px-6 rounded-xl bg-gradient-to-r from-amber-400 to-yellow-300 hover:from-amber-300 hover:to-yellow-200 disabled:opacity-45 disabled:cursor-not-allowed text-slate-950 text-xs sm:text-sm font-black uppercase tracking-wider shadow-lg shadow-amber-500/20 transition active:scale-[0.99]"
                            >
                                <span wire:loading.remove wire:target="sendChallenge">⚔️ Send Battle Challenge</span>
                                <span wire:loading wire:target="sendChallenge">Creating Live Draft Room...</span>
                            </button>

                            @if(!$canSubmitChallenge)
                                <p class="text-[11px] text-slate-400 text-center">
                                    @if((int) $user->cric_gems < 100)
                                        You need at least 100 CricGems to send a challenge.
                                    @elseif(!$selectedFriendObj)
                                        Select a friend in Step 1 to enable challenge creation.
                                    @elseif((int) $selectedFriendObj->cric_gems < 100)
                                        {{ $selectedFriendObj->captainTitle() }} needs at least 100 CricGems to play.
                                    @elseif(!$selectedMatchObj)
                                        Choose an upcoming T20 or ODI match in Step 2 to continue.
                                    @endif
                                </p>
                            @endif
                        </div>
                    </div>
                </section>
            @endif

            <!-- My Active & Past Battles -->
            @if($user && ($myActiveBattles->isNotEmpty() || $myPastBattles->isNotEmpty()))
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <!-- Active / Pending Outgoing / Live -->
                    <section class="rounded-2xl bg-slate-900/90 border border-slate-800 p-4 sm:p-5 space-y-3">
                        <h2 class="text-sm font-black text-emerald-300 uppercase tracking-wider flex items-center gap-2">
                            <span>⚡</span> My Active &amp; Live Battles ({{ $myActiveBattles->count() }})
                        </h2>
                        @forelse($myActiveBattles as $ab)
                            <a href="{{ route('games.battle.show', $ab->slug) }}" wire:navigate class="block p-3.5 rounded-xl bg-slate-950 border border-slate-800 hover:border-amber-400/50 transition space-y-1.5">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-xs font-black text-white">
                                        {{ $ab->challenger?->captainTitle() }} vs {{ $ab->opponent?->captainTitle() }}
                                    </span>
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase {{ $ab->status === 'drafting' ? 'bg-amber-500/20 text-amber-300 border border-amber-500/40' : ($ab->status === 'locked_live' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/40' : 'bg-slate-800 text-slate-300') }}">
                                        {{ str_replace('_', ' ', $ab->status) }}
                                    </span>
                                </div>
                                <div class="text-xs text-slate-300 font-semibold">{{ $ab->match_title }} ({{ $ab->modeLabel() }} • {{ $ab->match_format }})</div>
                                <div class="flex items-center justify-between text-[11px] text-slate-400">
                                    <span>Runs: {{ $ab->challenger_runs }} vs {{ $ab->opponent_runs }}</span>
                                    <span class="text-amber-400 font-bold">Enter Room →</span>
                                </div>
                            </a>
                        @empty
                            <p class="text-xs text-slate-500 py-4 text-center">No active drafts or live battles right now.</p>
                        @endforelse
                    </section>

                    <!-- Settled / History -->
                    <section class="rounded-2xl bg-slate-900/90 border border-slate-800 p-4 sm:p-5 space-y-3">
                        <h2 class="text-sm font-black text-amber-300 uppercase tracking-wider flex items-center gap-2">
                            <span>🏆</span> My Battle History ({{ $myPastBattles->count() }})
                        </h2>
                        @forelse($myPastBattles as $pb)
                            <a href="{{ route('games.battle.show', $pb->slug) }}" wire:navigate class="block p-3.5 rounded-xl bg-slate-950 border border-slate-800 hover:border-slate-600 transition space-y-1.5">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="text-xs font-black text-white">
                                        {{ $pb->challenger?->captainTitle() }} vs {{ $pb->opponent?->captainTitle() }}
                                    </span>
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-black uppercase {{ $pb->status === 'settled' ? 'bg-emerald-500/15 text-emerald-300' : 'bg-slate-800 text-slate-400' }}">
                                        {{ str_replace('_', ' ', $pb->status) }}
                                    </span>
                                </div>
                                <div class="text-xs text-slate-300">{{ $pb->match_title }} ({{ $pb->modeLabel() }})</div>
                                <div class="flex items-center justify-between text-[11px]">
                                    @if($pb->status === 'settled')
                                        <span class="text-emerald-400 font-bold">
                                            {{ $pb->winner ? "🏆 {$pb->winner->captainTitle()} won by {$pb->run_margin} runs" : '🤝 Tied (0 Gems)' }}
                                        </span>
                                    @else
                                        <span class="text-slate-500">0 CricGems deducted</span>
                                    @endif
                                    <span class="text-amber-400 font-bold">View →</span>
                                </div>
                            </a>
                        @empty
                            <p class="text-xs text-slate-500 py-4 text-center">No completed battles yet.</p>
                        @endforelse
                    </section>
                </div>
            @endif

            <!-- Public Live & Recent Captain Battles Directory -->
            <section class="rounded-2xl bg-slate-900/90 border border-slate-800 p-4 sm:p-6 space-y-4">
                <div class="flex items-center justify-between">
                    <h2 class="text-sm sm:text-base font-black text-white uppercase tracking-wider flex items-center gap-2">
                        <span>🏟️</span> Public Live &amp; Recent Captain Battles
                    </h2>
                    <span class="text-[11px] text-slate-400">Click any battle to inspect live/final scorecard</span>
                </div>

                @if($publicBattles->isEmpty())
                    <div class="p-6 rounded-xl bg-slate-950 border border-slate-800 text-center text-xs text-slate-400">
                        No public battles locked or settled yet. Be the first Captain to launch a battle above!
                    </div>
                @else
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-3">
                        @foreach($publicBattles as $pub)
                            <a href="{{ route('games.battle.show', $pub->slug) }}" wire:navigate class="p-4 rounded-xl bg-slate-950 border border-slate-800 hover:border-amber-400/50 transition space-y-2.5 group">
                                <div class="flex items-center justify-between gap-2">
                                    <span class="px-2 py-0.5 rounded-md text-[10px] font-black {{ $pub->status === 'locked_live' ? 'bg-emerald-500/20 text-emerald-300 border border-emerald-500/30' : 'bg-amber-500/15 text-amber-300 border border-amber-500/30' }}">
                                        {{ $pub->status === 'locked_live' ? '⚡ LIVE BATTLE' : '🏆 SETTLED' }}
                                    </span>
                                    <span class="text-[10px] font-mono font-bold text-slate-400">{{ $pub->modeLabel() }} • {{ $pub->match_format }}</span>
                                </div>
                                <div class="text-xs font-black text-white group-hover:text-amber-300 transition">
                                    {{ $pub->challenger?->captainTitle() }} <span class="text-slate-500">vs</span> {{ $pub->opponent?->captainTitle() }}
                                </div>
                                <div class="text-[11px] text-slate-400 truncate">{{ $pub->match_title }}</div>
                                <div class="flex items-center justify-between pt-2 border-t border-slate-800/80 text-xs font-mono font-black">
                                    <span class="text-amber-300">{{ $pub->challenger_runs }} Runs</span>
                                    <span class="text-slate-500">vs</span>
                                    <span class="text-cyan-300">{{ $pub->opponent_runs }} Runs</span>
                                </div>
                            </a>
                        @endforeach
                    </div>
                @endif
            </section>
        </main>
    </div>

    <x-footer />
    <x-bottom-nav />
</div>
