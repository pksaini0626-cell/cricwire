@props(['size' => 'normal'])

@php
    $heightClass = match($size) {
        'small' => 'h-7',
        'large' => 'h-11 sm:h-12',
        default => 'h-8 sm:h-9',
    };
@endphp

<picture {{ $attributes->merge(['class' => 'inline-flex items-center select-none group']) }}>
    <source srcset="{{ asset('images/cricwire-logo.webp') }}" type="image/webp">
    <img
        src="{{ asset('images/cricwire-logo.png') }}"
        alt="Cricwire"
        width="275"
        height="96"
        class="{{ $heightClass }} w-auto object-contain group-hover:scale-[1.02] transition-transform duration-200"
        loading="eager"
        decoding="async"
    >
</picture>
