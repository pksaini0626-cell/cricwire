@php
    $banner = \App\Models\AdBanner::activeSlot('home_top')->first();
    $bannerImg = ($banner && $banner->resolved_image_url) ? $banner->resolved_image_url : asset('images/cricwire-live-banner.webp');
    $targetUrl = $banner?->target_url ?: null;
    $isExternal = $targetUrl && !in_array($targetUrl, ['#', url('/'), 'https://cricwire.in', 'https://cricwire.in/', 'http://cricwire.in', 'http://cricwire.in/']);
@endphp

<div class="relative overflow-hidden rounded-2xl border border-amber-500/25 bg-[#070b1e] shadow-xl shadow-amber-500/5 group transition-all duration-300">
    <!-- Ambient Neon Stadium Glow Behind Banner -->
    <div class="absolute -top-12 left-1/4 w-48 h-48 bg-amber-500/10 rounded-full blur-3xl pointer-events-none"></div>
    <div class="absolute -bottom-12 right-1/4 w-48 h-48 bg-emerald-500/10 rounded-full blur-3xl pointer-events-none"></div>

    @if($isExternal && $banner)
        <a href="{{ route('ad.redirect', ['banner' => $banner->id]) }}" 
           target="_blank" 
           rel="noopener noreferrer sponsored" 
           class="relative w-full flex items-center justify-center overflow-hidden block">
            <img 
                src="{{ $bannerImg }}" 
                alt="CricWire Ultra Fast Cricket Live - Fast Updates <100ms Ping" 
                class="w-full h-auto max-h-[160px] sm:max-h-[190px] md:max-h-[220px] object-cover sm:object-contain rounded-2xl group-hover:scale-[1.01] transition-transform duration-300"
                loading="eager"
            />
        </a>
    @else
        <div class="relative w-full flex items-center justify-center overflow-hidden">
            <img 
                src="{{ $bannerImg }}" 
                alt="CricWire Ultra Fast Cricket Live - Fast Updates <100ms Ping" 
                class="w-full h-auto max-h-[160px] sm:max-h-[190px] md:max-h-[220px] object-cover sm:object-contain rounded-2xl group-hover:scale-[1.01] transition-transform duration-300"
                loading="eager"
            />
        </div>
    @endif
</div>
