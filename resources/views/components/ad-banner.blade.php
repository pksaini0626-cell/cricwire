@props(['placement' => 'upcoming_match'])

@php
    $banner = \App\Models\AdBanner::activeSlot($placement)->first();
@endphp

@if($banner)
    <div class="w-full my-3 flex flex-col items-center justify-center transition-all duration-300">
        <div class="text-[9px] uppercase tracking-wider text-slate-500 font-semibold mb-1 flex items-center gap-1">
            <span>SPONSORED</span>
            <span class="w-1 h-1 rounded-full bg-slate-600"></span>
            <span>CRICKET PARTNER</span>
        </div>

        @if($banner->type === 'image_link' || $banner->type === 'telegram_cta')
            @php
                $imgUrl = $banner->resolved_image_url;
                $redirectUrl = route('ad.redirect', ['banner' => $banner->id]);
            @endphp

            @if($imgUrl)
                <a 
                    href="{{ $redirectUrl }}" 
                    target="_blank" 
                    rel="noopener noreferrer sponsored"
                    class="block overflow-hidden rounded-xl border border-white/10 hover:border-amber-500/40 transition-all duration-200 shadow-md hover:shadow-amber-500/10 group max-w-full"
                >
                    <img 
                        src="{{ $imgUrl }}" 
                        alt="{{ $banner->name }}" 
                        loading="lazy" 
                        class="w-auto max-h-[160px] sm:max-h-[250px] object-contain rounded-xl group-hover:scale-[1.01] transition-transform duration-200"
                    />
                </a>
            @endif
        @elseif($banner->type === 'html_script' && !empty($banner->script_code))
            <div class="w-full max-w-full overflow-hidden flex justify-center">
                {!! $banner->script_code !!}
            </div>
        @endif
    </div>
@endif

