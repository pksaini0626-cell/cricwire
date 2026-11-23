<div 
    x-data="{
        showBanner: false,
        loading: false,
        init() {
            // Check Notification support and current permission state
            if (!('Notification' in window)) return;

            const dismissedUntil = localStorage.getItem('cricwire_push_prompt_dismissed_until');
            const now = new Date().getTime();

            if (Notification.permission === 'default' && (!dismissedUntil || now > parseInt(dismissedUntil))) {
                // Delay banner display by 2.5s for seamless page load
                setTimeout(() => {
                    this.showBanner = true;
                }, 2500);
            }
        },
        async enablePush() {
            this.loading = true;
            try {
                const permission = await Notification.requestPermission();
                if (permission === 'granted') {
                    if (window.CricwirePush) {
                        await window.CricwirePush.subscribe();
                    }
                    this.showBanner = false;
                } else {
                    this.dismiss(30); // don't ask for 30 days if denied
                }
            } catch (err) {
                console.error('Push permission request error:', err);
            } finally {
                this.loading = false;
            }
        },
        dismiss(days = 7) {
            const expireAt = new Date().getTime() + (days * 24 * 60 * 60 * 1000);
            localStorage.setItem('cricwire_push_prompt_dismissed_until', expireAt.toString());
            this.showBanner = false;
        }
    }"
    x-show="showBanner"
    x-transition:enter="transition ease-out duration-300"
    x-transition:enter-start="opacity-0 translate-y-6"
    x-transition:enter-end="opacity-100 translate-y-0"
    x-transition:leave="transition ease-in duration-200"
    x-transition:leave-start="opacity-100 translate-y-0"
    x-transition:leave-end="opacity-0 translate-y-6"
    style="display: none;"
    class="fixed bottom-20 md:bottom-6 left-4 right-4 md:left-auto md:right-6 md:w-96 z-50 p-4 rounded-2xl bg-slate-900/95 backdrop-blur-xl border border-amber-500/30 shadow-[0_10px_30px_rgba(0,0,0,0.8)] text-white"
>
    <div class="flex items-start space-x-3.5">
        <div class="w-10 h-10 rounded-xl bg-gradient-to-br from-amber-500/20 to-yellow-500/10 border border-amber-500/30 flex items-center justify-center shrink-0 shadow-inner">
            <span class="text-xl">🏏</span>
        </div>
        <div class="flex-1">
            <h4 class="text-xs sm:text-sm font-bold text-white tracking-wide">Never Miss a Moment!</h4>
            <p class="text-[11px] sm:text-xs text-slate-300 mt-1 leading-snug">
                Get instant toss updates, wicket alerts, and match-winning thrillers straight to your device.
            </p>
            <div class="flex items-center gap-2 mt-3">
                <button 
                    @click="enablePush()" 
                    :disabled="loading"
                    type="button"
                    class="px-3.5 py-1.5 rounded-xl bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-400 hover:to-amber-500 text-slate-950 font-bold text-xs shadow-md shadow-amber-500/20 transition-all flex items-center gap-1.5 disabled:opacity-50"
                >
                    <template x-if="loading">
                        <svg class="animate-spin h-3.5 w-3.5 text-slate-950" viewBox="0 0 24 24" fill="none">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path>
                        </svg>
                    </template>
                    <span>Enable Alerts</span>
                </button>
                <button 
                    @click="dismiss(7)" 
                    type="button"
                    class="px-3 py-1.5 rounded-xl bg-white/5 hover:bg-white/10 text-slate-400 hover:text-slate-200 text-xs font-semibold transition"
                >
                    Later
                </button>
            </div>
        </div>
        <button 
            @click="dismiss(7)" 
            type="button" 
            class="text-slate-400 hover:text-white transition p-1"
            aria-label="Close"
        >
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
        </button>
    </div>
</div>
