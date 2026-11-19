@props(['activeTab' => 'live', 'liveCount' => null, 'upcomingCount' => null, 'finishedCount' => null])

<div class="px-3 pt-3 pb-1 sticky top-[57px] z-30 bg-slate-950/95 backdrop-blur-md">
    <div class="grid grid-cols-3 gap-1.5 p-1 bg-slate-900/90 rounded-2xl border border-white/5 shadow-inner">
        <!-- Live Tab -->
        <button 
            type="button"
            wire:click="setTab('live')" 
            class="relative flex items-center justify-center gap-1.5 py-2 px-3 rounded-xl text-xs font-bold transition-all duration-200 {{ $activeTab === 'live' ? 'bg-gradient-to-r from-red-600 to-rose-600 text-white shadow-lg glow-red' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/40' }}"
        >
            <span class="relative flex h-2 w-2">
                <span class="animate-ping absolute inline-flex h-full w-full rounded-full {{ $activeTab === 'live' ? 'bg-white' : 'bg-red-500' }} opacity-75"></span>
                <span class="relative inline-flex rounded-full h-2 w-2 {{ $activeTab === 'live' ? 'bg-white' : 'bg-red-500' }}"></span>
            </span>
            <span>Live</span>
            @if(!is_null($liveCount))
                <span class="text-[10px] px-1.5 py-0.2 rounded-full {{ $activeTab === 'live' ? 'bg-white/20 text-white' : 'bg-slate-800 text-slate-400' }}">
                    {{ $liveCount }}
                </span>
            @endif
        </button>

        <!-- Upcoming Tab -->
        <button 
            type="button"
            wire:click="setTab('upcoming')" 
            class="relative flex items-center justify-center gap-1.5 py-2 px-3 rounded-xl text-xs font-bold transition-all duration-200 {{ $activeTab === 'upcoming' ? 'bg-gradient-to-r from-amber-500 to-amber-600 text-slate-950 shadow-lg glow-gold' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/40' }}"
        >
            <span>Upcoming</span>
            @if(!is_null($upcomingCount))
                <span class="text-[10px] px-1.5 py-0.2 rounded-full {{ $activeTab === 'upcoming' ? 'bg-black/20 text-slate-950 font-black' : 'bg-slate-800 text-slate-400' }}">
                    {{ $upcomingCount }}
                </span>
            @endif
        </button>

        <!-- Finished Tab -->
        <button 
            type="button"
            wire:click="setTab('finished')" 
            class="relative flex items-center justify-center gap-1.5 py-2 px-3 rounded-xl text-xs font-bold transition-all duration-200 {{ $activeTab === 'finished' ? 'bg-slate-800 text-white border border-white/10 shadow-md' : 'text-slate-400 hover:text-slate-200 hover:bg-slate-800/40' }}"
        >
            <span>Finished</span>
            @if(!is_null($finishedCount))
                <span class="text-[10px] px-1.5 py-0.2 rounded-full {{ $activeTab === 'finished' ? 'bg-white/20 text-white' : 'bg-slate-800 text-slate-400' }}">
                    {{ $finishedCount }}
                </span>
            @endif
        </button>
    </div>
</div>
