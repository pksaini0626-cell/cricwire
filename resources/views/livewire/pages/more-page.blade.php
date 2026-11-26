<div 
    class="min-h-screen bg-slate-950 text-slate-100 pb-24 md:pb-0 font-sans antialiased relative selection:bg-amber-500 selection:text-black flex flex-col justify-between"
    x-data="{
        pushAlerts: false,
        loadingPush: false,
        showUpgradeModal: false,
        showDeleteModal: false,
        selectedPlan: '3_months',
        init() {
            if ('Notification' in window && Notification.permission === 'granted' && localStorage.getItem('cricwire_fcm_token')) {
                this.pushAlerts = true;
            } else {
                this.pushAlerts = false;
            }

            window.addEventListener('cricwire:push-status-changed', (e) => {
                if (e.detail && typeof e.detail.isSubscribed !== 'undefined') {
                    this.pushAlerts = e.detail.isSubscribed;
                }
            });
        },
        async togglePush() {
            if (this.loadingPush) return;
            this.loadingPush = true;
            try {
                if (!this.pushAlerts) {
                    if (window.CricwirePush) {
                        const success = await window.CricwirePush.subscribe();
                        if (success) {
                            this.pushAlerts = true;
                        }
                    }
                } else {
                    if (window.CricwirePush) {
                        await window.CricwirePush.unsubscribe();
                        this.pushAlerts = false;
                    }
                }
            } catch (err) {
                console.error('Error toggling push notifications:', err);
                alert('Could not update notification preferences. Please check your browser notification permissions.');
            } finally {
                this.loadingPush = false;
            }
        },
        shareApp() {
            const shareData = {
                title: 'Cricwire Live Line - Fast Ball-by-Ball Cricket Scores',
                text: 'Check out Cricwire for the fastest cricket live line, sessions, and predictions!',
                url: window.location.origin
            };
            if (navigator.share) {
                navigator.share(shareData).catch(() => {});
            } else {
                navigator.clipboard.writeText(window.location.origin);
                alert('App link copied to clipboard!');
            }
        },
        rateApp() {
            alert('App Store & Play Store listing is coming soon!');
        }
    }"
>
    <div>
        <!-- Desktop Header (Visible on md and up) -->
        <div class="hidden md:block">
            <x-header />
        </div>

        <!-- Mobile Top Sticky Header (Hidden on md and up) -->
        <header class="md:hidden sticky top-0 z-40 bg-slate-900/90 backdrop-blur-xl border-b border-white/10 px-4 py-3 flex items-center justify-between shadow-lg">
            <div class="flex items-center space-x-2.5">
                <a href="{{ url('/') }}" wire:navigate class="p-1.5 -ml-1.5 rounded-lg bg-slate-800/80 hover:bg-slate-800 text-slate-300 hover:text-white transition active:scale-95" aria-label="Go back">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                    </svg>
                </a>
                <h1 class="text-base font-black tracking-tight text-white flex items-center gap-2">
                    <span>⚡</span> More &amp; Settings
                </h1>
            </div>

            <span class="px-2 py-0.5 rounded-md text-[10px] font-mono font-bold bg-slate-800 text-amber-400 border border-amber-500/20">
                PRO
            </span>
        </header>

        <!-- Main Responsive Content Container -->
        <main class="max-w-7xl mx-auto w-full px-3 sm:px-6 lg:px-8 py-4 sm:py-8">
            
            <!-- Desktop Breadcrumbs / Title Strip -->
            <div class="hidden md:flex items-center justify-between pb-6 mb-6 border-b border-slate-800/80">
                <div>
                    <div class="flex items-center space-x-2 text-xs text-slate-400 mb-1">
                        <a href="/" wire:navigate class="hover:text-amber-400 transition">Home</a>
                        <span>/</span>
                        <span class="text-amber-400 font-semibold">Account &amp; Settings</span>
                    </div>
                    <h1 class="text-2xl lg:text-3xl font-black text-white tracking-tight flex items-center gap-2.5">
                        <span class="text-amber-400">⚡</span> Account, Settings &amp; Preferences
                    </h1>
                </div>

                <div class="flex items-center space-x-3">
                    <span class="px-3 py-1 rounded-full text-xs font-mono font-bold bg-amber-500/10 text-amber-400 border border-amber-500/20">
                        CRICWIRE PRO • v{{ $appVersion }}
                    </span>
                </div>
            </div>

            <!-- Responsive Layout: 12-Column Grid on Desktop / Single Column Stack on Mobile -->
            <div class="grid grid-cols-1 md:grid-cols-12 gap-6 items-start max-w-md mx-auto md:max-w-none">
                
                <!-- LEFT COLUMN: Profile Card, Premium Subscription Banner & Socials (md:col-span-5 lg:col-span-4 space-y-4) -->
                <div class="md:col-span-5 lg:col-span-4 space-y-4">
                    
                    <!-- 1. User Profile Card -->
                    <div class="bg-gradient-to-r from-slate-900 via-slate-900/90 to-slate-950 border border-white/10 rounded-2xl p-4 sm:p-5 shadow-xl flex items-center justify-between">
                        <div class="flex items-center space-x-3.5 overflow-hidden">
                            @if($user)
                                <x-user-avatar :user="$user" class="w-12 h-12 sm:w-14 sm:h-14 text-lg sm:text-xl shadow-md glow-gold" />
                                <div class="truncate">
                                    <div class="flex items-center gap-1.5">
                                        <h2 class="text-sm sm:text-base font-extrabold text-white truncate">{{ $user->name }}</h2>
                                        <span class="w-2 h-2 rounded-full bg-emerald-400 shrink-0" title="Online"></span>
                                    </div>
                                    <p class="text-xs text-slate-400 truncate">{{ $user->email }}</p>
                                    <span class="inline-block mt-1 text-[10px] text-amber-400 font-bold uppercase tracking-wider">Cricwire Fan Member</span>
                                </div>
                            @else
                                <x-user-avatar :user="null" class="w-12 h-12 sm:w-14 sm:h-14 text-xl sm:text-2xl shadow-md" />
                                <div class="truncate">
                                    <h2 class="text-sm sm:text-base font-extrabold text-white">Guest Cricket Fan</h2>
                                    <p class="text-xs text-slate-400">Sign in to sync pinned matches</p>
                                    <a href="{{ route('google.redirect') }}" class="inline-flex items-center gap-1.5 mt-2 px-3 py-1.5 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 text-xs font-black transition shadow-sm">
                                        <span>Continue with Google</span>
                                        <span>→</span>
                                    </a>
                                </div>
                            @endif
                        </div>

                        @if($user)
                            <span class="px-2.5 py-1 rounded-full text-[10px] font-black uppercase tracking-wider bg-emerald-500/20 text-emerald-400 border border-emerald-500/30 shrink-0">
                                Active
                            </span>
                        @endif
                    </div>

                    <!-- 2. Cricwire Premium Plans Card (Hero Neon Gold Box) -->
                    <div class="relative rounded-2xl bg-gradient-to-b from-amber-950/30 via-slate-900 to-slate-950 p-4 sm:p-5 border border-amber-500/40 shadow-2xl glow-gold overflow-hidden">
                        <div class="absolute -top-12 -right-12 w-32 h-32 bg-amber-500/10 rounded-full blur-2xl pointer-events-none"></div>

                        <div class="flex items-center justify-between mb-2">
                            <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider bg-amber-500/20 text-amber-300 border border-amber-500/30">
                                <span>👑</span> Cricwire Premium
                            </span>
                            <span class="text-[10px] text-slate-400 font-semibold">Ad-Free &amp; Fastest Line</span>
                        </div>

                        <p class="text-xs text-slate-300 mb-3 sm:mb-4 leading-relaxed">
                            Enjoy 0-second live score latency, live ball voice audio, and zero advertisements across the platform.
                        </p>

                        <!-- Pricing Grid (3 Plan Cards) -->
                        <div class="grid grid-cols-3 gap-2 mb-4 text-center">
                            <!-- Monthly -->
                            <button 
                                type="button" 
                                @click="selectedPlan = 'monthly'" 
                                class="p-2.5 rounded-xl border transition-all text-xs flex flex-col justify-between"
                                :class="selectedPlan === 'monthly' ? 'bg-amber-500/20 border-amber-400 ring-1 ring-amber-400 text-white' : 'bg-slate-950/80 border-slate-800 text-slate-400 hover:border-slate-700'"
                            >
                                <span class="text-[10px] uppercase font-bold text-slate-400">Monthly</span>
                                <span class="font-mono font-black text-amber-300 text-sm sm:text-base mt-1">₹149</span>
                                <span class="text-[9px] text-slate-500 mt-0.5">30 Days</span>
                            </button>

                            <!-- 3 Months (Popular) -->
                            <button 
                                type="button" 
                                @click="selectedPlan = '3_months'" 
                                class="p-2.5 rounded-xl border relative transition-all text-xs flex flex-col justify-between"
                                :class="selectedPlan === '3_months' ? 'bg-amber-500/25 border-amber-400 ring-1 ring-amber-400 text-white' : 'bg-slate-950/80 border-slate-800 text-slate-400 hover:border-slate-700'"
                            >
                                <span class="absolute -top-2 left-1/2 -translate-x-1/2 px-1.5 py-0.2 rounded-full text-[8px] font-black uppercase tracking-wider bg-gradient-to-r from-amber-500 to-yellow-400 text-slate-950 shadow-sm">
                                    Popular
                                </span>
                                <span class="text-[10px] uppercase font-bold text-amber-300 mt-1">3 Months</span>
                                <span class="font-mono font-black text-white text-sm sm:text-base mt-0.5">₹399</span>
                                <span class="text-[9px] text-emerald-400 mt-0.5">Save 15%</span>
                            </button>

                            <!-- Annual (Best Value) -->
                            <button 
                                type="button" 
                                @click="selectedPlan = 'annual'" 
                                class="p-2.5 rounded-xl border relative transition-all text-xs flex flex-col justify-between"
                                :class="selectedPlan === 'annual' ? 'bg-amber-500/20 border-amber-400 ring-1 ring-amber-400 text-white' : 'bg-slate-950/80 border-slate-800 text-slate-400 hover:border-slate-700'"
                            >
                                <span class="absolute -top-2 left-1/2 -translate-x-1/2 px-1.5 py-0.2 rounded-full text-[8px] font-black uppercase tracking-wider bg-emerald-500 text-slate-950 shadow-sm">
                                    Best Value
                                </span>
                                <span class="text-[10px] uppercase font-bold text-slate-300 mt-1">Annual</span>
                                <span class="font-mono font-black text-amber-300 text-sm sm:text-base mt-0.5">₹999</span>
                                <span class="text-[9px] text-emerald-400 mt-0.5">Save 45%</span>
                            </button>
                        </div>

                        <!-- Upgrade Action Button -->
                        <button 
                            type="button" 
                            @click="showUpgradeModal = true" 
                            class="w-full py-2.5 sm:py-3 rounded-xl bg-gradient-to-r from-amber-400 via-yellow-400 to-amber-500 hover:from-amber-300 hover:to-amber-400 text-slate-950 font-black text-xs uppercase tracking-wider shadow-lg shadow-amber-500/25 active:scale-[0.99] transition"
                        >
                            ⚡ Upgrade to Cricwire Pro
                        </button>
                    </div>

                    <!-- Follow Us & Social Media Card -->
                    @if(!empty($socialLinks))
                        <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-4 shadow-md space-y-3">
                            <div class="text-[11px] font-bold text-amber-400 uppercase tracking-wider pb-1 border-b border-slate-800/80 flex items-center justify-between">
                                <span>Official Community Channels</span>
                                <span class="text-xs">📢</span>
                            </div>

                            <div class="grid grid-cols-2 gap-2">
                                @foreach($socialLinks as $key => $social)
                                    <a 
                                        href="{{ $social['url'] }}" 
                                        target="_blank" 
                                        rel="noopener noreferrer" 
                                        class="flex items-center space-x-2.5 p-2.5 rounded-xl bg-slate-950 border border-slate-800 hover:border-amber-500/40 transition group"
                                    >
                                        <span class="text-base">
                                            @if($key === 'telegram') ✈️
                                            @elseif($key === 'instagram') 📸
                                            @elseif($key === 'twitter') 𝕏
                                            @elseif($key === 'youtube') 📺
                                            @elseif($key === 'facebook') 📘
                                            @else 🔗
                                            @endif
                                        </span>
                                        <div class="overflow-hidden">
                                            <span class="text-xs font-bold text-slate-200 group-hover:text-amber-300 transition block truncate">
                                                {{ $social['name'] }}
                                            </span>
                                        </div>
                                    </a>
                                @endforeach
                            </div>

                            <div class="grid grid-cols-2 gap-2 pt-1">
                                <button 
                                    type="button" 
                                    @click="rateApp()" 
                                    class="p-2.5 rounded-xl bg-slate-950 border border-slate-800 hover:border-slate-700 flex items-center justify-center gap-2 text-xs font-bold text-slate-300 hover:text-white transition active:scale-95"
                                >
                                    <span>⭐</span>
                                    <span>Rate App</span>
                                </button>

                                <button 
                                    type="button" 
                                    @click="shareApp()" 
                                    class="p-2.5 rounded-xl bg-slate-950 border border-slate-800 hover:border-amber-500/40 flex items-center justify-center gap-2 text-xs font-bold text-amber-400 hover:text-amber-300 transition active:scale-95"
                                >
                                    <span>🚀</span>
                                    <span>Share Cricwire</span>
                                </button>
                            </div>
                        </div>
                    @endif

                </div>

                <!-- RIGHT COLUMN: Preferences, Quick Navigation, Policies, and Account Deletion (md:col-span-7 lg:col-span-8 space-y-4) -->
                <div class="md:col-span-7 lg:col-span-8 space-y-4">
                    
                    <!-- App Preferences & Navigation Settings -->
                    <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-4 sm:p-5 shadow-md space-y-3">
                        <div class="text-[11px] font-bold text-amber-400 uppercase tracking-wider pb-2 border-b border-slate-800/80 flex items-center justify-between">
                            <span>App Features &amp; Push Alerts</span>
                            <span class="text-xs">⚙️</span>
                        </div>

                        <div class="divide-y divide-slate-800/80">
                            <!-- Push Notifications Toggle -->
                            <div class="flex items-center justify-between py-3">
                                <div class="flex items-center space-x-3">
                                    <span class="text-xl">🔔</span>
                                    <div>
                                        <div class="text-xs sm:text-sm font-bold text-slate-200">Match Push Alerts</div>
                                        <div class="text-[11px] text-slate-400">Receive instant toss, innings break &amp; wicket alerts</div>
                                    </div>
                                </div>

                                <button 
                                    type="button" 
                                    @click="togglePush()" 
                                    class="w-12 h-6 flex items-center rounded-full p-1 transition-colors duration-300 focus:outline-none"
                                    :class="pushAlerts ? 'bg-amber-500' : 'bg-slate-800'"
                                    aria-label="Toggle Push Alerts"
                                >
                                    <div 
                                        class="bg-white w-4 h-4 rounded-full shadow-md transform transition-transform duration-300"
                                        :class="pushAlerts ? 'translate-x-6' : 'translate-x-0'"
                                    ></div>
                                </button>
                            </div>

                            <!-- Browse Fixtures -->
                            <a href="{{ route('schedule') }}" wire:navigate class="flex items-center justify-between py-3.5 hover:bg-slate-800/40 px-2 rounded-xl transition group">
                                <div class="flex items-center space-x-3">
                                    <span class="text-xl">📅</span>
                                    <div>
                                        <span class="text-xs sm:text-sm font-bold text-slate-200 group-hover:text-amber-300 transition">Tournament Fixtures &amp; Schedules</span>
                                        <p class="text-[11px] text-slate-400">View upcoming, live, and recently finished series matches</p>
                                    </div>
                                </div>
                                <span class="text-slate-500 text-sm font-bold group-hover:translate-x-1 transition-transform">→</span>
                            </a>

                            <!-- News & Editorials -->
                            <a href="{{ route('news.index') }}" wire:navigate class="flex items-center justify-between py-3.5 hover:bg-slate-800/40 px-2 rounded-xl transition group">
                                <div class="flex items-center space-x-3">
                                    <span class="text-xl">📰</span>
                                    <div>
                                        <span class="text-xs sm:text-sm font-bold text-slate-200 group-hover:text-amber-300 transition">Latest News &amp; Cricket Editorials</span>
                                        <p class="text-[11px] text-slate-400">Match previews, pitch reports, player analysis &amp; viral cricket stories</p>
                                    </div>
                                </div>
                                <span class="text-slate-500 text-sm font-bold group-hover:translate-x-1 transition-transform">→</span>
                            </a>

                            <!-- Fan Polls -->
                            <a href="{{ route('polls') }}" wire:navigate class="flex items-center justify-between py-3.5 hover:bg-slate-800/40 px-2 rounded-xl transition group">
                                <div class="flex items-center space-x-3">
                                    <span class="text-xl">🗳️</span>
                                    <div>
                                        <span class="text-xs sm:text-sm font-bold text-slate-200 group-hover:text-amber-300 transition">Community Fan Polls</span>
                                        <p class="text-[11px] text-slate-400">Cast your prediction votes on ongoing matches &amp; tournaments</p>
                                    </div>
                                </div>
                                <span class="text-slate-500 text-sm font-bold group-hover:translate-x-1 transition-transform">→</span>
                            </a>
                        </div>
                    </div>

                    <!-- Support, Feedback & Legal Transparency -->
                    <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-4 sm:p-5 shadow-md space-y-3">
                        <div class="text-[11px] font-bold text-amber-400 uppercase tracking-wider pb-2 border-b border-slate-800/80 flex items-center justify-between">
                            <span>Compliance, Support &amp; Policies</span>
                            <span class="text-xs">📜</span>
                        </div>

                        <div class="divide-y divide-slate-800/80">
                            <!-- Feedback -->
                            <a href="mailto:team@cricwire.in?subject=Cricwire App Feedback" class="flex items-center justify-between py-3 hover:bg-slate-800/40 px-2 rounded-xl transition group">
                                <div class="flex items-center space-x-3">
                                    <span class="text-lg">💬</span>
                                    <div>
                                        <div class="text-xs sm:text-sm font-bold text-slate-200 group-hover:text-amber-300 transition">Feedback &amp; Grievance Desk</div>
                                        <div class="text-[11px] text-slate-400">Directly contact our development &amp; editorial team</div>
                                    </div>
                                </div>
                                <span class="text-slate-500 text-xs font-mono">team@cricwire.in</span>
                            </a>

                            <!-- Privacy Policy & Disclaimers -->
                            <a href="{{ url('/privacy-policy') }}" wire:navigate class="flex items-center justify-between py-3 hover:bg-slate-800/40 px-2 rounded-xl transition group">
                                <div class="flex items-center space-x-3">
                                    <span class="text-lg">🛡️</span>
                                    <div>
                                        <div class="text-xs sm:text-sm font-bold text-slate-200 group-hover:text-white transition">Privacy Policy &amp; Non-Gambling Disclaimers</div>
                                        <div class="text-[11px] text-slate-400">Our third-party API data, zero-gambling stance &amp; user privacy rights</div>
                                    </div>
                                </div>
                                <span class="text-slate-500 text-xs">→</span>
                            </a>

                            <!-- Terms & Conditions -->
                            <a href="{{ url('/terms-and-conditions') }}" wire:navigate class="flex items-center justify-between py-3 hover:bg-slate-800/40 px-2 rounded-xl transition group">
                                <div class="flex items-center space-x-3">
                                    <span class="text-lg">⚖️</span>
                                    <div>
                                        <div class="text-xs sm:text-sm font-bold text-slate-200 group-hover:text-white transition">Terms of Service</div>
                                        <div class="text-[11px] text-slate-400">User conduct guidelines and platform terms</div>
                                    </div>
                                </div>
                                <span class="text-slate-500 text-xs">→</span>
                            </a>
                        </div>
                    </div>

                    <!-- Account & Session (Safe Primary Action) -->
                    @auth
                        <div class="bg-slate-900/90 border border-slate-800 rounded-2xl p-4 sm:p-5 shadow-md space-y-3">
                            <div class="text-[11px] font-bold text-amber-400 uppercase tracking-wider pb-2 border-b border-slate-800/80 flex items-center justify-between">
                                <span>Account &amp; Security</span>
                                <span class="text-xs">🔐</span>
                            </div>

                            <button 
                                type="button" 
                                wire:click="logout" 
                                wire:loading.attr="disabled"
                                class="w-full bg-slate-800/90 hover:bg-slate-700 hover:border-amber-500/40 text-slate-100 border border-slate-700 rounded-xl px-4 py-3 font-bold text-sm flex items-center justify-between transition active:scale-[0.99] disabled:opacity-60 disabled:cursor-not-allowed group"
                            >
                                <div class="flex items-center space-x-3 text-left">
                                    <svg class="w-5 h-5 text-amber-400 shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.75 9V5.25A2.25 2.25 0 0013.5 3h-6a2.25 2.25 0 00-2.25 2.25v13.5A2.25 2.25 0 007.5 21h6a2.25 2.25 0 002.25-2.25V15m3 0l3-3m0 0l-3-3m3 3H9" />
                                    </svg>
                                    <div>
                                        <div class="text-sm font-bold text-slate-100 group-hover:text-amber-300 transition">Logout</div>
                                        <div class="text-[11px] font-normal text-slate-400">Sign out of this device</div>
                                    </div>
                                </div>
                                <span wire:loading.remove wire:target="logout" class="text-slate-400 group-hover:text-amber-400 group-hover:translate-x-0.5 transition-transform text-sm">→</span>
                                <span wire:loading wire:target="logout" class="text-xs text-amber-400 font-semibold">Signing out...</span>
                            </button>
                        </div>

                        <!-- Danger Zone (Destructive Account Deletion Trigger) -->
                        <div class="bg-rose-950/20 border border-rose-500/30 rounded-2xl p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
                            <div>
                                <h3 class="text-xs sm:text-sm font-bold text-rose-400 flex items-center gap-1.5">
                                    <span>⚠️</span> Danger Zone • Account Deletion
                                </h3>
                                <p class="text-[11px] text-slate-400 mt-0.5">Permanently erase your preferences, saved matches, and Google account linkage.</p>
                            </div>
                            <button 
                                type="button" 
                                @click="showDeleteModal = true" 
                                class="px-4 py-2 rounded-xl bg-rose-600/20 hover:bg-rose-600 border border-rose-500/40 text-rose-300 hover:text-white text-xs font-bold transition shrink-0"
                            >
                                Delete My Account
                            </button>
                        </div>
                    @endauth

                    <!-- Version & Status Note (Mobile-only bottom version; Desktop uses header & footer) -->
                    <div class="text-center pt-2 pb-4 space-y-1 md:hidden">
                        <p class="text-[11px] font-mono text-slate-500 font-semibold">
                            Cricwire App Version {{ $appVersion }} (Build 2026.1)
                        </p>
                        <p class="text-[10px] text-slate-600 font-medium">
                            Crafted for Cricket Fans 🏏
                        </p>
                    </div>

                </div>

            </div>
        </main>
    </div>

    <!-- Upgrade Premium Modal -->
    <div 
        x-show="showUpgradeModal" 
        x-cloak 
        class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
        @keydown.escape.window="showUpgradeModal = false"
    >
        <div class="bg-slate-900 border border-amber-500/40 rounded-2xl max-w-sm w-full p-5 text-center shadow-2xl relative" @click.away="showUpgradeModal = false">
            <div class="text-3xl mb-2">👑</div>
            <h3 class="text-base font-extrabold text-white mb-1">Upgrade to Cricwire Pro</h3>
            <p class="text-xs text-slate-400 mb-4">
                Thank you for your interest! Payment gateway integration is currently undergoing testing and will be live shortly.
            </p>
            <button 
                type="button" 
                @click="showUpgradeModal = false" 
                class="w-full py-2.5 rounded-xl bg-amber-400 hover:bg-amber-300 text-slate-950 font-black text-xs uppercase tracking-wider transition"
            >
                Got It
            </button>
        </div>
    </div>

    <!-- Delete Account Confirmation Modal -->
    @auth
        <div 
            x-show="showDeleteModal" 
            x-cloak 
            class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm"
            @keydown.escape.window="showDeleteModal = false"
        >
            <div class="bg-slate-900 border border-rose-500/40 rounded-2xl max-w-sm w-full p-5 text-center shadow-2xl relative" @click.away="showDeleteModal = false">
                <div class="text-3xl mb-2">⚠️</div>
                <h3 class="text-base font-extrabold text-white mb-1">Permanently Delete Account?</h3>
                <p class="text-xs text-slate-400 mb-5 leading-relaxed">
                    Are you sure? This will permanently delete your account and data.
                </p>

                <div class="grid grid-cols-2 gap-2">
                    <button 
                        type="button" 
                        @click="showDeleteModal = false" 
                        class="py-2.5 rounded-xl bg-slate-800 text-slate-300 hover:text-white font-bold text-xs transition"
                    >
                        Cancel
                    </button>
                    <button 
                        type="button" 
                        wire:click="deleteAccount" 
                        class="py-2.5 rounded-xl bg-rose-600 hover:bg-rose-500 text-white font-black text-xs uppercase tracking-wider transition shadow-lg shadow-rose-600/30"
                    >
                        Yes, Delete
                    </button>
                </div>
            </div>
        </div>
    @endauth

    <!-- Desktop Global Footer (Hidden on mobile) -->
    <x-footer />

    <!-- Mobile Bottom Navigation (Highlighting More tab) -->
    <x-bottom-nav activeTab="more" />
</div>
