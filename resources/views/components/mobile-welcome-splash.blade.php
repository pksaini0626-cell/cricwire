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
        <!-- Brand Logo -->
        <div class="mb-4">
            <x-cricwire-logo size="large" />
        </div>

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
