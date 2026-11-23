@php
    $banner = \App\Models\AdBanner::activeSlot('home_top')->first();
    $bannerImg = ($banner && $banner->resolved_image_url) ? $banner->resolved_image_url : null;
    $targetUrl = $banner?->target_url ?: null;
    $isExternal = $targetUrl && !in_array($targetUrl, ['#', url('/'), 'https://cricwire.in', 'https://cricwire.in/', 'http://cricwire.in', 'http://cricwire.in/']);
@endphp

@if($banner && $bannerImg)
    <div class="relative overflow-hidden rounded-xl border border-amber-500/20 bg-[#0D1422] shadow-lg mb-5 group transition-all duration-200">

        @if($isExternal)
            <a href="{{ route('ad.redirect', ['banner' => $banner->id]) }}" 
               target="_blank" 
               rel="noopener noreferrer sponsored" 
               class="relative w-full flex items-center justify-center overflow-hidden block">
                <img 
                    src="{{ $bannerImg }}" 
                    alt="{{ $banner->name ?? 'CricWire Live Cricket' }}" 
                    class="w-full h-auto max-h-[160px] sm:max-h-[190px] md:max-h-[220px] object-cover sm:object-contain rounded-2xl group-hover:scale-[1.01] transition-transform duration-300"
                    loading="eager"
                />
            </a>
        @else
            <div class="relative w-full flex items-center justify-center overflow-hidden">
                <img 
                    src="{{ $bannerImg }}" 
                    alt="{{ $banner->name ?? 'CricWire Live Cricket' }}" 
                    class="w-full h-auto max-h-[160px] sm:max-h-[190px] md:max-h-[220px] object-cover sm:object-contain rounded-2xl group-hover:scale-[1.01] transition-transform duration-300"
                    loading="eager"
                />
            </div>
        @endif
    </div>
@endif
