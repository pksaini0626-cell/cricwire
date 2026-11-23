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
                ['label' => 'Series', 'url' => route('series.index'), 'active' => str_starts_with($currentPath, 'cricket-series') || $currentRoute === 'series.index' || $currentRoute === 'series.detail'],
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

            <!-- In-App Notification Bell & Dropdown Drawer -->
            <div 
                x-data="{
                    open: false,
                    loading: false,
                    notifications: [],
                    hasUnread: false,
                    lastSeenId: parseInt(localStorage.getItem('cricwire_last_seen_notif_id') || '0'),
                    init() {
                        this.fetchLatest();
                    },
                    async fetchLatest() {
                        try {
                            const res = await fetch('/api/notifications/latest');
                            const json = await res.json();
                            if (json.success && json.data) {
                                this.notifications = json.data;
                                if (this.notifications.length > 0) {
                                    const latestId = this.notifications[0].id;
                                    this.hasUnread = latestId > this.lastSeenId;
                                }
                            }
                        } catch (e) {
                            console.error('Error fetching notifications:', e);
                        }
                    },
                    toggle() {
                        this.open = !this.open;
                        if (this.open) {
                            this.markAsSeen();
                            this.fetchLatest();
                        }
                    },
                    markAsSeen() {
                        if (this.notifications.length > 0) {
                            const latestId = this.notifications[0].id;
                            localStorage.setItem('cricwire_last_seen_notif_id', latestId.toString());
                            this.lastSeenId = latestId;
                            this.hasUnread = false;
                        }
                    },
                    getIcon(type) {
                        switch(type) {
                            case 'match': return '🏏';
                            case 'poll': return '📊';
                            case 'news': return '🚨';
                            default: return '📢';
                        }
                    },
                    timeAgo(dateString) {
                        if (!dateString) return '';
                        const date = new Date(dateString);
                        const now = new Date();
                        const seconds = Math.floor((now - date) / 1000);
                        if (seconds < 60) return 'Just now';
                        const minutes = Math.floor(seconds / 60);
                        if (minutes < 60) return minutes + 'm ago';
                        const hours = Math.floor(minutes / 60);
                        if (hours < 24) return hours + 'h ago';
                        const days = Math.floor(hours / 24);
                        return days + 'd ago';
                    }
                }"
                class="relative"
                @click.outside="open = false"
            >
                <button 
                    @click="toggle()" 
                    type="button"
                    class="relative p-2 rounded-xl bg-slate-800/80 hover:bg-slate-700/80 border border-white/10 hover:border-amber-400/50 text-slate-300 hover:text-white transition shadow-sm focus:outline-none"
                    aria-label="Notification Center"
                >
                    <svg class="w-4 h-4 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 17h5l-1.405-1.405A2.032 2.032 0 0118 14.158V11a6.002 6.002 0 00-4-5.659V5a2 2 0 10-4 0v.341C7.67 6.165 6 8.388 6 11v3.159c0 .538-.214 1.055-.595 1.436L4 17h5m6 0v1a3 3 0 11-6 0v-1m6 0H9" />
                    </svg>

                    <!-- Pulsing Unread Badge Dot -->
                    <template x-if="hasUnread">
                        <span class="absolute top-1.5 right-1.5 flex h-2.5 w-2.5">
                            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-red-400 opacity-75"></span>
                            <span class="relative inline-flex rounded-full h-2.5 w-2.5 bg-red-500 shadow-[0_0_8px_rgba(239,68,68,0.9)]"></span>
                        </span>
                    </template>
                </button>

                <!-- Dropdown Notification Drawer -->
                <div 
                    x-show="open" 
                    x-transition:enter="transition ease-out duration-200"
                    x-transition:enter-start="opacity-0 scale-95 translate-y-1"
                    x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                    x-transition:leave="transition ease-in duration-150"
                    x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                    x-transition:leave-end="opacity-0 scale-95 translate-y-1"
                    style="display: none;"
                    class="absolute right-0 sm:right-0 -mr-12 sm:mr-0 mt-2 w-80 sm:w-96 rounded-2xl bg-slate-900/95 backdrop-blur-2xl border border-white/10 shadow-2xl z-50 overflow-hidden"
                >
                    <!-- Header -->
                    <div class="px-4 py-3 border-b border-white/10 flex items-center justify-between bg-slate-800/40">
                        <div class="flex items-center space-x-2">
                            <span class="text-base">🔔</span>
                            <h3 class="text-xs sm:text-sm font-bold text-white tracking-wide">Match & News Alerts</h3>
                        </div>
                        <button 
                            @click="markAsSeen()" 
                            class="text-[11px] font-semibold text-amber-400 hover:text-amber-300 transition"
                        >
                            Mark all read
                        </button>
                    </div>

                    <!-- Notification List -->
                    <div class="max-h-96 overflow-y-auto divide-y divide-white/5 scrollbar-thin scrollbar-thumb-slate-700">
                        <template x-if="notifications.length === 0">
                            <div class="p-8 text-center text-slate-400">
                                <span class="text-3xl block mb-2">🏏</span>
                                <p class="text-xs font-medium">No alerts right now</p>
                                <p class="text-[10px] text-slate-500 mt-0.5">Live toss, wickets, and breaking news will appear here.</p>
                            </div>
                        </template>

                        <template x-for="item in notifications" :key="item.id">
                            <a 
                                :href="item.target_url || '#'" 
                                class="block p-3.5 hover:bg-white/[0.04] transition group"
                                :class="{'bg-amber-500/[0.03]': item.id > lastSeenId}"
                            >
                                <div class="flex items-start space-x-3">
                                    <div class="w-8 h-8 rounded-xl bg-slate-800 border border-white/10 flex items-center justify-center shrink-0 shadow-inner group-hover:border-amber-400/40 transition">
                                        <span class="text-sm" x-text="getIcon(item.type)"></span>
                                    </div>
                                    <div class="flex-1 min-w-0">
                                        <div class="flex items-center justify-between gap-1 mb-0.5">
                                            <h4 class="text-xs font-bold text-slate-100 truncate group-hover:text-amber-400 transition" x-text="item.title"></h4>
                                            <span class="text-[10px] text-slate-500 shrink-0" x-text="timeAgo(item.sent_at || item.created_at)"></span>
                                        </div>
                                        <p class="text-[11px] text-slate-400 line-clamp-2 leading-relaxed" x-text="item.body"></p>
                                        <template x-if="item.image_url">
                                            <img :src="item.image_url" alt="Banner" class="mt-2 w-full h-24 object-cover rounded-lg border border-white/5" />
                                        </template>
                                    </div>
                                </div>
                            </a>
                        </template>
                    </div>

                    <!-- Footer Link -->
                    <div class="p-2.5 bg-slate-950/60 border-t border-white/5 text-center">
                        <a href="{{ route('more') }}" wire:navigate class="text-[11px] font-semibold text-slate-400 hover:text-amber-400 transition">
                            Notification Preferences & Settings →
                        </a>
                    </div>
                </div>
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

