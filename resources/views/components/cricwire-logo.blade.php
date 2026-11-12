@props(['size' => 'normal'])

@php
    $dimensions = match($size) {
        'small' => 'h-7',
        'large' => 'h-12',
        default => 'h-9',
    };
@endphp

<div {{ $attributes->merge(['class' => 'inline-flex items-center space-x-2 select-none group']) }}>
    <!-- Electric Bat & Ball Icon -->
    <div class="{{ $dimensions }} aspect-square rounded-xl bg-gradient-to-tr from-amber-500 to-yellow-400 p-1.5 flex items-center justify-center shadow-lg shadow-amber-500/20 group-hover:scale-105 transition-transform duration-300">
        <svg viewBox="0 0 24 24" fill="none" class="w-full h-full text-slate-950" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">
            <!-- Cricket Bat Angle -->
            <path d="M18.5 3.5a2.121 2.121 0 0 1 3 3L13 15l-4-1 1-4 8.5-6.5z" fill="currentColor"/>
            <path d="M9 14l-5 5a1.414 1.414 0 0 0 2 2l5-5"/>
            <!-- Electric Seam Ball -->
            <circle cx="6" cy="6" r="3" fill="#0f172a" stroke="currentColor" stroke-width="1.5"/>
            <path d="M4.8 4.8c.8.8 1.6 1.6 2.4 2.4" stroke="#f59e0b" stroke-width="1.2"/>
        </svg>
    </div>

    <!-- Wordmark -->
    <div class="flex flex-col leading-none">
        <div class="flex items-center text-lg sm:text-xl font-black tracking-tight text-white uppercase">
            <span>CRIC</span>
            <span class="text-amber-400 ml-0.5">WIRE</span>
            <span class="ml-1.5 w-1.5 h-1.5 rounded-full bg-emerald-400 animate-pulse"></span>
        </div>
        <span class="text-[9px] font-bold uppercase tracking-widest text-slate-400">Fast Live Line Pro</span>
    </div>
</div>

