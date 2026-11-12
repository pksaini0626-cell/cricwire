<div x-data="{ 
        isLoading: false,
        text: 'Fetching Live Line...' 
     }"
     x-init="
        document.addEventListener('livewire:navigating', () => { 
            isLoading = true; 
        });
        document.addEventListener('livewire:navigated', () => { 
            setTimeout(() => { isLoading = false; }, 150); 
        });
        if (window.Livewire && Livewire.hook) {
            Livewire.hook('commit', ({ succeed }) => {
                succeed(() => { isLoading = false; });
            });
        } else {
            document.addEventListener('livewire:init', () => {
                Livewire.hook('commit', ({ succeed }) => {
                    succeed(() => { isLoading = false; });
                });
            });
        }
     "
     x-show="isLoading"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-200"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="fixed inset-0 z-[9999] flex flex-col items-center justify-center bg-slate-950/85 backdrop-blur-md select-none"
     style="display: none;">
     
    <!-- Cricket Ball Spin & Bounce Animation Container -->
    <div class="relative w-24 h-24 flex items-center justify-center">
        <!-- Glowing Ambient Ring -->
        <div class="absolute inset-0 rounded-full bg-amber-500/20 blur-xl animate-pulse"></div>
        
        <!-- Rotating Pitch / Seam Circle -->
        <div class="w-16 h-16 rounded-full bg-gradient-to-tr from-rose-600 to-amber-500 p-0.5 shadow-xl shadow-amber-500/30 animate-spin" style="animation-duration: 1.2s;">
            <!-- Ball Core with Cricket Seam Stitching SVG -->
            <div class="w-full h-full rounded-full bg-slate-950 flex items-center justify-center relative overflow-hidden">
                <svg viewBox="0 0 100 100" class="w-full h-full text-amber-400 stroke-current opacity-80" fill="none" stroke-width="6" stroke-linecap="round">
                    <!-- Cricket Ball Seam Curved Line -->
                    <path d="M 15 50 Q 50 15 85 50 Q 50 85 15 50" stroke-dasharray="6 4" />
                </svg>
            </div>
        </div>

        <!-- Center Cricket Bat/Live Icon -->
        <div class="absolute inset-0 flex items-center justify-center pointer-events-none">
            <span class="text-xl">🏏</span>
        </div>
    </div>

    <!-- Status Text with Neon Amber Glow -->
    <div class="mt-4 flex flex-col items-center space-y-1">
        <div class="flex items-center space-x-1.5">
            <span class="w-2 h-2 rounded-full bg-emerald-400 animate-ping"></span>
            <span class="text-sm font-black tracking-wider uppercase text-white">CRIC<span class="text-amber-400">WIRE</span></span>
        </div>
        <p class="text-xs font-semibold text-slate-400 tracking-wide animate-pulse">
            Connecting to Live Line Engine...
        </p>
    </div>
</div>

