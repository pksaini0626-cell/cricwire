<!-- Mobile-Only Instant Welcome Splash Screen -->
<div id="cricwire-mobile-splash"
     class="fixed inset-0 z-[100000] md:hidden flex flex-col items-center justify-between bg-slate-950 text-slate-100 px-6 py-12 select-none overflow-hidden transition-opacity duration-500 ease-out"
     style="display: none; height: 100dvh;"
     onclick="window.dismissCricwireSplash && window.dismissCricwireSplash()">

    <!-- Ambient Neon Stadium Glow Background -->
    <div class="pointer-events-none absolute inset-0 overflow-hidden">
        <div class="absolute -top-24 left-1/2 -translate-x-1/2 w-80 h-80 rounded-full bg-amber-500/15 blur-3xl"></div>
        <div class="absolute bottom-10 left-1/2 -translate-x-1/2 w-72 h-72 rounded-full bg-emerald-500/10 blur-3xl"></div>
    </div>

    <!-- Top Live Pill -->
    <div class="relative z-10 flex items-center space-x-2 px-3.5 py-1.5 rounded-full bg-slate-900/90 border border-slate-800 shadow-md">
        <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
        <span class="text-[11px] font-bold uppercase tracking-widest text-slate-300">Fastest Live Cricket Line</span>
    </div>

    <!-- Center Brand Logo & Welcome Message -->
    <div class="relative z-10 flex flex-col items-center text-center my-auto animate-splash-pop">
        <!-- Glowing Emblem -->
        <div class="relative mb-5">
            <div class="absolute -inset-2 rounded-3xl bg-gradient-to-tr from-amber-500 to-emerald-400 opacity-30 blur-lg animate-pulse"></div>
            <div class="relative w-24 h-24 rounded-3xl bg-gradient-to-tr from-amber-500 via-amber-400 to-yellow-300 p-4 flex items-center justify-center shadow-2xl shadow-amber-500/30 border border-amber-200/40">
                <svg viewBox="0 0 24 24" fill="none" class="w-full h-full text-slate-950" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
                    <path d="M18.5 3.5a2.121 2.121 0 0 1 3 3L13 15l-4-1 1-4 8.5-6.5z" fill="currentColor"/>
                    <path d="M9 14l-5 5a1.414 1.414 0 0 0 2 2l5-5"/>
                    <circle cx="6" cy="6" r="3" fill="#0f172a" stroke="currentColor" stroke-width="1.5"/>
                    <path d="M4.8 4.8c.8.8 1.6 1.6 2.4 2.4" stroke="#f59e0b" stroke-width="1.3"/>
                </svg>
            </div>
        </div>

        <!-- Brand Wordmark -->
        <h1 class="text-3xl font-black tracking-wider uppercase text-white">
            CRIC<span class="text-amber-400">WIRE</span>
        </h1>

        <!-- Welcome Message -->
        <p class="mt-2.5 text-base font-bold text-slate-100">
            Welcome to Cricwire 🏏
        </p>
        <p class="mt-1 text-xs text-slate-400 max-w-[260px] leading-relaxed">
            Ball-by-ball live scores, real-time match stats & instant cricket updates.
        </p>
    </div>

    <!-- Bottom Progress Bar & Tap Hint -->
    <div class="relative z-10 w-full max-w-[220px] flex flex-col items-center space-y-3">
        <div class="w-full h-1.5 bg-slate-900 rounded-full overflow-hidden border border-slate-800/80">
            <div class="h-full bg-gradient-to-r from-amber-500 via-yellow-400 to-emerald-400 rounded-full animate-splash-progress"></div>
        </div>
        <span class="text-[10px] font-semibold uppercase tracking-widest text-slate-500">
            Tap anywhere to continue
        </span>
    </div>

    <style>
        @keyframes splashPop {
            0% { opacity: 0; transform: scale(0.92) translateY(8px); }
            100% { opacity: 1; transform: scale(1) translateY(0); }
        }
        @keyframes splashProgress {
            0% { width: 0%; }
            100% { width: 100%; }
        }
        .animate-splash-pop {
            animation: splashPop 0.35s cubic-bezier(0.16, 1, 0.3, 1) forwards;
        }
        .animate-splash-progress {
            animation: splashProgress 1.75s linear forwards;
        }
    </style>

    <!-- Synchronous Immediate Execution Script (Runs Before Frame Paint) -->
    <script>
        (function () {
            var splash = document.getElementById('cricwire-mobile-splash');
            if (!splash) return;

            var isMobile = window.innerWidth < 768;
            var alreadyWelcomed = false;
            try {
                alreadyWelcomed = sessionStorage.getItem('cricwire_welcomed') === '1';
            } catch (e) {}

            if (isMobile && !alreadyWelcomed) {
                splash.style.display = 'flex';
                document.documentElement.style.overflow = 'hidden';

                window.dismissCricwireSplash = function () {
                    if (!splash || splash.dataset.dismissed === '1') return;
                    splash.dataset.dismissed = '1';
                    try {
                        sessionStorage.setItem('cricwire_welcomed', '1');
                    } catch (e) {}
                    splash.style.opacity = '0';
                    splash.style.pointerEvents = 'none';
                    document.documentElement.style.overflow = '';
                    setTimeout(function () {
                        if (splash) splash.remove();
                    }, 500);
                };

                setTimeout(window.dismissCricwireSplash, 1800);
            } else {
                splash.remove();
            }
        })();
    </script>
</div>
