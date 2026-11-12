<header class="sticky top-0 z-40 bg-slate-900/90 backdrop-blur-xl border-b border-white/10 shadow-lg">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-3 flex items-center justify-between">
        <!-- Brand / Logo (Mobile & Desktop) -->
        <div class="flex items-center space-x-3 shrink-0">
            <a href="{{ url('/') }}" wire:navigate class="flex items-center space-x-2.5 group">
                <x-cricwire-logo size="normal" />
            </a>
        </div>

        <!-- Center Desktop Nav Links (Hidden on Mobile, Visible on md & lg) -->
        @php
            $currentRoute = request()->route()?->getName();
            $currentPath = request()->path();

            $navItems = [
                ['label' => 'Live Scores', 'url' => url('/'), 'active' => request()->is('/') || $currentRoute === 'home'],
                ['label' => 'Schedule', 'url' => route('schedule'), 'active' => str_starts_with($currentPath, 'cricket-schedule') || $currentRoute === 'schedule'],
                ['label' => 'Series', 'url' => url('/cricket-schedule/upcoming/international'), 'active' => str_starts_with($currentPath, 'cricket-series')],
                ['label' => 'News', 'url' => route('news.index'), 'active' => str_starts_with($currentPath, 'cricket-news') || $currentRoute === 'news.index'],
                ['label' => 'Polls', 'url' => route('polls'), 'active' => str_starts_with($currentPath, 'polls') || $currentRoute === 'polls'],
                ['label' => 'Rankings', 'url' => route('rankings'), 'active' => str_starts_with($currentPath, 'cricket-rankings') || $currentRoute === 'rankings'],
            ];
        @endphp

        <nav class="hidden md:flex items-center space-x-1 lg:space-x-2">
            @foreach($navItems as $item)
                <a 
                    href="{{ $item['url'] }}" 
                    wire:navigate 
                    class="px-3 py-1.5 rounded-xl text-xs lg:text-sm font-semibold transition-all relative group {{ $item['active'] ? 'text-amber-400 bg-amber-500/10 font-bold' : 'text-slate-300 hover:text-white hover:bg-slate-800/60' }}"
                >
                    {{ $item['label'] }}
                    @if($item['active'])
                        <span class="absolute bottom-0 left-3 right-3 h-0.5 bg-gradient-to-r from-amber-400 to-yellow-300 rounded-full shadow-[0_0_8px_rgba(245,158,11,0.8)]"></span>
                    @endif
                </a>
            @endforeach
        </nav>

        <!-- Right Side: Dark Mode, Search Bar, Live Alert & Google Auth -->
        <div class="flex items-center space-x-2.5 sm:space-x-3 shrink-0">
            <!-- Search Bar (Desktop) -->
            <div class="hidden lg:flex items-center relative">
                <input 
                    type="text" 
                    placeholder="Search teams, series, matches..." 
                    class="w-48 xl:w-60 bg-slate-800/80 border border-slate-700/70 focus:border-amber-400/80 rounded-xl px-3 py-1.5 pl-8 text-xs text-slate-100 placeholder-slate-400 focus:outline-none focus:ring-1 focus:ring-amber-400/50 transition-all"
                >
                <svg class="w-3.5 h-3.5 text-slate-400 absolute left-2.5 pointer-events-none" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35m1.35-5.65a7 7 0 11-14 0 7 7 0 0114 0z"/>
                </svg>
            </div>

            <!-- Dark Mode Indicator Pill -->
            <div class="hidden sm:flex items-center justify-center p-1.5 rounded-xl bg-slate-800/70 border border-slate-700/50 text-amber-400 text-xs" title="Dark Theme Active">
                <span>🌙</span>
            </div>

            <!-- Live Pulse Badge -->
            <div class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-red-500/10 border border-red-500/30 glow-red">
                <span class="relative flex h-2 w-2">
                    <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                    <span class="relative inline-flex rounded-full h-2 w-2 bg-red-500"></span>
                </span>
                <span class="text-[10px] font-black tracking-wider uppercase text-red-400">LIVE FEED</span>
            </div>

            <!-- Google Profile / Auth Button -->
            @auth
                <a href="{{ route('more') }}" wire:navigate class="relative rounded-full glow-gold hover:border-amber-400 transition" title="User Profile & Settings">
                    <x-user-avatar size="sm" />
                </a>
            @else
                <a href="{{ route('google.redirect') }}" class="flex items-center gap-1.5 px-3 py-1.5 rounded-xl bg-slate-800/90 hover:bg-slate-800 border border-white/10 hover:border-amber-500/40 text-xs font-semibold text-slate-200 hover:text-white transition shadow-sm group">
                    <svg class="w-3.5 h-3.5 text-amber-400 group-hover:scale-110 transition-transform" viewBox="0 0 24 24" fill="currentColor">
                        <path d="M12.545,10.239v3.821h5.445c-0.712,2.315-2.647,3.972-5.445,3.972c-3.332,0-6.033-2.701-6.033-6.032s2.701-6.032,6.033-6.032c1.498,0,2.866,0.549,3.921,1.453l2.814-2.814C17.503,2.988,15.139,2,12.545,2C7.021,2,2.543,6.477,2.543,12s4.478,10,10.002,10c8.396,0,10.249-7.85,9.426-11.761H12.545z"/>
                    </svg>
                    <span class="text-xs font-bold">Sign In</span>
                </a>
            @endauth
        </div>
    </div>
</header>

