@if(config('app.show_market_rates', true) && \App\Helpers\SystemSetting::isMarketRatesEnabled() && !empty($sessionSummary))
    @php
        $activeInning = $sessionSummary['active_inning'] ?? 1;
        $inng1 = $sessionSummary['innings_1'] ?? null;
        $inng2 = $sessionSummary['innings_2'] ?? null;
    @endphp

    <div class="space-y-3" x-data="{ openInning: {{ $activeInning === 2 ? 2 : 1 }} }">
        <div class="flex items-center justify-between pb-1">
            <h3 class="text-xs font-bold text-amber-400 uppercase tracking-wider flex items-center gap-1.5">
                <span>📊</span>
                <span>Session Summary</span>
            </h3>
            <span class="text-[10px] text-slate-400 font-mono">Rates &amp; Pass/Fail</span>
        </div>

        <!-- 1st Innings Accordion Card -->
        @if(!empty($inng1['rows']))
            <div class="bg-slate-900/95 border border-slate-800 rounded-2xl overflow-hidden shadow-md transition-all">
                <!-- Header / Toggle -->
                <button 
                    type="button"
                    @click="openInning = (openInning === 1 ? null : 1)"
                    class="w-full flex items-center justify-between p-3.5 bg-slate-900 hover:bg-slate-850 transition text-left"
                >
                    <div class="flex items-center space-x-2.5">
                        <div class="w-6 h-6 rounded-full overflow-hidden bg-slate-950 border border-slate-750 flex items-center justify-center shrink-0">
                            @if(!empty($inng1['team_logo']))
                                <img src="{{ $inng1['team_logo'] }}" alt="{{ $inng1['team_name'] }}" class="w-full h-full object-cover" onerror="this.style.display='none'">
                            @else
                                <span class="text-[10px] font-bold text-amber-400">1</span>
                            @endif
                        </div>
                        <span class="text-xs font-bold text-white tracking-wide">
                            1st Innings - <span class="text-amber-400">{{ $inng1['team_name'] }}</span>
                        </span>
                        @if($activeInning === 1 && !($inng1['is_completed'] ?? false))
                            <span class="px-1.5 py-0.5 rounded text-[9px] font-black uppercase bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">Active</span>
                        @endif
                    </div>
                    <div class="flex items-center space-x-2">
                        <svg class="w-4 h-4 text-slate-400 transition-transform duration-200" :class="{ 'rotate-180': openInning === 1 }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                </button>

                <!-- Accordion Body -->
                <div x-show="openInning === 1" x-collapse>
                    <div class="overflow-x-auto border-t border-slate-800/80">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="bg-slate-950/60 text-slate-400 text-[10px] uppercase font-bold border-b border-slate-800/80">
                                    <th class="py-2.5 px-3">Over</th>
                                    <th class="py-2.5 px-3 text-center">Session Rate</th>
                                    <th class="py-2.5 px-3 text-center">Score</th>
                                    <th class="py-2.5 px-3 text-right">Match Odds</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/50 font-mono">
                                @forelse($inng1['rows'] as $r)
                                    <tr class="hover:bg-slate-800/20 transition">
                                        <!-- Over Column -->
                                        <td class="py-2.5 px-3 font-bold text-white">
                                            <div class="flex items-center gap-1.5">
                                                <span class="text-amber-400">{{ $r['over'] }}</span>
                                                <span class="text-[10px] text-slate-500 font-sans font-normal">ov</span>
                                            </div>
                                        </td>

                                        <!-- Session Rate + Low/High Column -->
                                        <td class="py-2.5 px-3 text-center">
                                            <div class="inline-flex flex-col items-center">
                                                <span class="font-bold text-white text-xs">
                                                    {{ $r['rate_display'] ?? '-' }}
                                                </span>
                                                @if(!empty($r['lowest']) || !empty($r['highest']))
                                                    <span class="inline-block mt-0.5 px-1.5 py-0.5 rounded text-[9px] font-sans font-medium bg-slate-950 text-slate-400 border border-slate-800">
                                                        L: <strong class="text-rose-400">{{ $r['lowest'] ?? '-' }}</strong> | H: <strong class="text-emerald-400">{{ $r['highest'] ?? '-' }}</strong>
                                                    </span>
                                                @endif
                                            </div>
                                        </td>

                                        <!-- Score & Result Column (✔ / ✘ / -) -->
                                        <td class="py-2.5 px-3 text-center">
                                            <div class="inline-flex items-center gap-1.5">
                                                <span class="font-bold text-slate-200">
                                                    {{ $r['score'] ?? '-' }}
                                                </span>
                                                @if($r['passed'] === true)
                                                    <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-emerald-500/20 text-emerald-400 font-black text-xs" title="Session Passed (Yes)">
                                                        ✔
                                                    </span>
                                                @elseif($r['passed'] === false)
                                                    <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-rose-500/20 text-rose-400 font-black text-xs" title="Session Failed (No)">
                                                        ✘
                                                    </span>
                                                @else
                                                    <span class="text-slate-600 text-xs">-</span>
                                                @endif
                                            </div>
                                        </td>

                                        <!-- Match Odds Column -->
                                        <td class="py-2.5 px-3 text-right">
                                            <span class="text-slate-300 font-medium text-[11px]">
                                                {{ $r['match_odds'] ?? '-' }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="py-3 px-3 text-center text-slate-500 text-xs font-sans">
                                            No session data for 1st Innings yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif

        <!-- 2nd Innings Accordion Card (if active or 2nd innings has rows) -->
        @if(!empty($inng2['rows']) && ($activeInning >= 2 || !empty($match['team_b_scores']) || ($inng2['is_completed'] ?? false)))
            <div class="bg-slate-900/95 border border-slate-800 rounded-2xl overflow-hidden shadow-md transition-all">
                <!-- Header / Toggle -->
                <button 
                    type="button"
                    @click="openInning = (openInning === 2 ? null : 2)"
                    class="w-full flex items-center justify-between p-3.5 bg-slate-900 hover:bg-slate-850 transition text-left"
                >
                    <div class="flex items-center space-x-2.5">
                        <div class="w-6 h-6 rounded-full overflow-hidden bg-slate-950 border border-slate-750 flex items-center justify-center shrink-0">
                            @if(!empty($inng2['team_logo']))
                                <img src="{{ $inng2['team_logo'] }}" alt="{{ $inng2['team_name'] }}" class="w-full h-full object-cover" onerror="this.style.display='none'">
                            @else
                                <span class="text-[10px] font-bold text-amber-400">2</span>
                            @endif
                        </div>
                        <span class="text-xs font-bold text-white tracking-wide">
                            2nd Innings - <span class="text-amber-400">{{ $inng2['team_name'] }}</span>
                        </span>
                        @if($activeInning === 2 && !($inng2['is_completed'] ?? false))
                            <span class="px-1.5 py-0.5 rounded text-[9px] font-black uppercase bg-emerald-500/10 text-emerald-400 border border-emerald-500/30">Active</span>
                        @endif
                    </div>
                    <div class="flex items-center space-x-2">
                        <svg class="w-4 h-4 text-slate-400 transition-transform duration-200" :class="{ 'rotate-180': openInning === 2 }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7" />
                        </svg>
                    </div>
                </button>

                <!-- Accordion Body -->
                <div x-show="openInning === 2" x-collapse>
                    <div class="overflow-x-auto border-t border-slate-800/80">
                        <table class="w-full text-left text-xs">
                            <thead>
                                <tr class="bg-slate-950/60 text-slate-400 text-[10px] uppercase font-bold border-b border-slate-800/80">
                                    <th class="py-2.5 px-3">Over</th>
                                    <th class="py-2.5 px-3 text-center">Session Rate</th>
                                    <th class="py-2.5 px-3 text-center">Score</th>
                                    <th class="py-2.5 px-3 text-right">Match Odds</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-slate-800/50 font-mono">
                                @forelse($inng2['rows'] as $r)
                                    <tr class="hover:bg-slate-800/20 transition">
                                        <!-- Over Column -->
                                        <td class="py-2.5 px-3 font-bold text-white">
                                            <div class="flex items-center gap-1.5">
                                                <span class="text-amber-400">{{ $r['over'] }}</span>
                                                <span class="text-[10px] text-slate-500 font-sans font-normal">ov</span>
                                            </div>
                                        </td>

                                        <!-- Session Rate + Low/High Column -->
                                        <td class="py-2.5 px-3 text-center">
                                            <div class="inline-flex flex-col items-center">
                                                <span class="font-bold text-white text-xs">
                                                    {{ $r['rate_display'] ?? '-' }}
                                                </span>
                                                @if(!empty($r['lowest']) || !empty($r['highest']))
                                                    <span class="inline-block mt-0.5 px-1.5 py-0.5 rounded text-[9px] font-sans font-medium bg-slate-950 text-slate-400 border border-slate-800">
                                                        L: <strong class="text-rose-400">{{ $r['lowest'] ?? '-' }}</strong> | H: <strong class="text-emerald-400">{{ $r['highest'] ?? '-' }}</strong>
                                                    </span>
                                                @endif
                                            </div>
                                        </td>

                                        <!-- Score & Result Column (✔ / ✘ / -) -->
                                        <td class="py-2.5 px-3 text-center">
                                            <div class="inline-flex items-center gap-1.5">
                                                <span class="font-bold text-slate-200">
                                                    {{ $r['score'] ?? '-' }}
                                                </span>
                                                @if($r['passed'] === true)
                                                    <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-emerald-500/20 text-emerald-400 font-black text-xs" title="Session Passed (Yes)">
                                                        ✔
                                                    </span>
                                                @elseif($r['passed'] === false)
                                                    <span class="inline-flex items-center justify-center w-4 h-4 rounded-full bg-rose-500/20 text-rose-400 font-black text-xs" title="Session Failed (No)">
                                                        ✘
                                                    </span>
                                                @else
                                                    <span class="text-slate-600 text-xs">-</span>
                                                @endif
                                            </div>
                                        </td>

                                        <!-- Match Odds Column -->
                                        <td class="py-2.5 px-3 text-right">
                                            <span class="text-slate-300 font-medium text-[11px]">
                                                {{ $r['match_odds'] ?? '-' }}
                                            </span>
                                        </td>
                                    </tr>
                                @empty
                                    <tr>
                                        <td colspan="4" class="py-3 px-3 text-center text-slate-500 text-xs font-sans">
                                            No session data for 2nd Innings yet.
                                        </td>
                                    </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        @endif
    </div>
@endif

