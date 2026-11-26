@props(['activeTab' => null])

@php
    $currentRoute = request()->route()?->getName();
    $currentPath = request()->path();
    
    // Auto detect active tab if not explicitly passed
    $active = $activeTab ?: match(true) {
        $currentRoute === 'more' || $currentPath === 'more' || $currentPath === 'profile' => 'more',
        $currentRoute === 'games.index' || $currentRoute === 'games.battle.show' || str_starts_with($currentPath, 'games') => 'games',
        $currentRoute === 'polls' || $currentPath === 'polls' => 'polls',
        $currentRoute === 'news.index' || str_starts_with($currentPath, 'cricket-news') => 'news',
        $currentRoute === 'series.index' || $currentRoute === 'series.detail' || str_starts_with($currentPath, 'cricket-series') => 'series',
        $currentRoute === 'schedule' || str_starts_with($currentPath, 'cricket-schedule') => 'schedule',
        default => 'live',
    };
@endphp

<nav class="md:hidden lg:hidden fixed bottom-3 left-4 right-4 z-50 max-w-md mx-auto rounded-2xl bg-slate-900/90 backdrop-blur-xl border border-white/10 shadow-2xl overflow-hidden">
    <div class="grid grid-cols-6 h-14 items-center">
        <!-- Live -->
        <a href="{{ url('/') }}" wire:navigate class="flex flex-col items-center justify-center transition-all group {{ $active === 'live' ? 'text-amber-400 font-bold' : 'text-slate-400 hover:text-slate-200' }}">
            <span class="text-base group-hover:scale-110 transition-transform {{ $active === 'live' ? 'drop-shadow-[0_0_8px_rgba(245,158,11,0.5)]' : '' }}">
                ⚡
            </span>
            <span class="text-[10px] {{ $active === 'live' ? 'font-black uppercase tracking-wider text-neon-gold' : 'font-medium tracking-tight' }} mt-0.5">Live</span>
        </a>

        <!-- Schedule -->
        <a href="{{ route('schedule') }}" wire:navigate class="flex flex-col items-center justify-center transition-all group {{ $active === 'schedule' ? 'text-amber-400 font-bold' : 'text-slate-400 hover:text-slate-200' }}">
            <span class="text-base group-hover:scale-110 transition-transform {{ $active === 'schedule' ? 'drop-shadow-[0_0_8px_rgba(245,158,11,0.5)]' : '' }}">
                📅
            </span>
            <span class="text-[10px] {{ $active === 'schedule' ? 'font-black uppercase tracking-wider text-neon-gold' : 'font-medium tracking-tight' }} mt-0.5">Schedule</span>
        </a>

        <!-- Games -->
        <a href="{{ route('games.index') }}" wire:navigate class="flex flex-col items-center justify-center transition-all group {{ $active === 'games' ? 'text-amber-400 font-bold' : 'text-slate-400 hover:text-slate-200' }}">
            <span class="text-base group-hover:scale-110 transition-transform {{ $active === 'games' ? 'drop-shadow-[0_0_8px_rgba(245,158,11,0.5)]' : '' }}">
                🎮
            </span>
            <span class="text-[10px] {{ $active === 'games' ? 'font-black uppercase tracking-wider text-neon-gold' : 'font-medium tracking-tight' }} mt-0.5">Games</span>
        </a>

        <!-- News -->
        <a href="{{ route('news.index') }}" wire:navigate class="flex flex-col items-center justify-center transition-all group {{ $active === 'news' ? 'text-amber-400 font-bold' : 'text-slate-400 hover:text-slate-200' }}">
            <span class="text-base group-hover:scale-110 transition-transform {{ $active === 'news' ? 'drop-shadow-[0_0_8px_rgba(245,158,11,0.5)]' : '' }}">
                📰
            </span>
            <span class="text-[10px] {{ $active === 'news' ? 'font-black uppercase tracking-wider text-neon-gold' : 'font-medium tracking-tight' }} mt-0.5">News</span>
        </a>

        <!-- Polls -->
        <a href="{{ route('polls') }}" wire:navigate class="flex flex-col items-center justify-center transition-all group {{ $active === 'polls' ? 'text-amber-400 font-bold' : 'text-slate-400 hover:text-slate-200' }}">
            <span class="text-base group-hover:scale-110 transition-transform {{ $active === 'polls' ? 'drop-shadow-[0_0_8px_rgba(245,158,11,0.5)]' : '' }}">
                🗳️
            </span>
            <span class="text-[10px] {{ $active === 'polls' ? 'font-black uppercase tracking-wider text-neon-gold' : 'font-medium tracking-tight' }} mt-0.5">Polls</span>
        </a>

        <!-- More -->
        <a href="{{ route('more') }}" wire:navigate class="flex flex-col items-center justify-center transition-all group {{ $active === 'more' ? 'text-amber-400 font-bold' : 'text-slate-400 hover:text-slate-200' }}">
            <span class="text-base group-hover:scale-110 transition-transform {{ $active === 'more' ? 'drop-shadow-[0_0_8px_rgba(245,158,11,0.5)]' : '' }}">
                ⚙️
            </span>
            <span class="text-[10px] {{ $active === 'more' ? 'font-black uppercase tracking-wider text-neon-gold' : 'font-medium tracking-tight' }} mt-0.5">More</span>
        </a>
    </div>
</nav>
