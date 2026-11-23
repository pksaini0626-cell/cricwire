@props(['activeTab' => 'live', 'liveCount' => null, 'upcomingCount' => null, 'finishedCount' => null])

<div class="flex gap-1 p-0.5 bg-[#0D1422] rounded-lg border border-[#253248]">
    <!-- Live Tab -->
    <button 
        type="button"
        wire:click="setTab('live')" 
        class="relative flex items-center justify-center gap-1.5 py-1.5 px-3 rounded-md text-xs font-semibold transition-all duration-100 {{ $activeTab === 'live' ? 'bg-[#EF233C] text-white font-bold' : 'text-[#94A3B8] hover:text-[#F8FAFC] hover:bg-[#172337]' }}"
    >
        <span class="relative flex h-1.5 w-1.5">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full {{ $activeTab === 'live' ? 'bg-white' : 'bg-[#EF233C]' }} opacity-75"></span>
            <span class="relative inline-flex rounded-full h-1.5 w-1.5 {{ $activeTab === 'live' ? 'bg-white' : 'bg-[#EF233C]' }}"></span>
        </span>
        <span>Live</span>
        @if(!is_null($liveCount) && $liveCount > 0)
            <span class="text-[9px] px-1 py-px font-bold rounded {{ $activeTab === 'live' ? 'bg-white/25 text-white' : 'bg-[#172337] text-[#94A3B8]' }}">
                {{ $liveCount }}
            </span>
        @endif
    </button>

    <!-- Upcoming Tab -->
    <button 
        type="button"
        wire:click="setTab('upcoming')" 
        class="relative flex items-center justify-center gap-1.5 py-1.5 px-3 rounded-md text-xs font-semibold transition-all duration-100 {{ $activeTab === 'upcoming' ? 'bg-amber-500 text-slate-950 font-bold' : 'text-[#94A3B8] hover:text-[#F8FAFC] hover:bg-[#172337]' }}"
    >
        <span>Upcoming</span>
    </button>

    <!-- Finished Tab -->
    <button 
        type="button"
        wire:click="setTab('finished')" 
        class="relative flex items-center justify-center gap-1.5 py-1.5 px-3 rounded-md text-xs font-semibold transition-all duration-100 {{ $activeTab === 'finished' ? 'bg-[#172337] text-[#F8FAFC] font-bold' : 'text-[#94A3B8] hover:text-[#F8FAFC] hover:bg-[#172337]' }}"
    >
        <span>Finished</span>
    </button>
</div>
