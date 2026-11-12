@props([
    'user' => auth()->user(),
    'size' => 'md', // 'sm', 'md', 'lg'
])

@php
    $sizeClasses = match($size) {
        'sm' => 'w-8 h-8 text-xs',
        'lg' => 'w-16 h-16 text-2xl',
        default => 'w-10 h-10 text-base',
    };

    $name = trim($user->name ?? '');
    $avatarUrl = $user->avatar ?? $user->profile_photo_url ?? null;

    // Extract First + Last Initials (e.g. "Virat Kohli" -> "VK", "Rohit" -> "RO")
    $initials = '';
    if (!empty($name)) {
        $parts = preg_split('/\s+/', $name);
        if (count($parts) >= 2) {
            $initials = mb_strtoupper(mb_substr($parts[0], 0, 1) . mb_substr(end($parts), 0, 1));
        } else {
            $initials = mb_strtoupper(mb_substr($parts[0], 0, 2));
        }
    }
@endphp

<div {{ $attributes->merge(['class' => "{$sizeClasses} rounded-full shrink-0 relative overflow-hidden flex items-center justify-center font-black tracking-wider select-none border border-amber-400/50 bg-gradient-to-tr from-amber-500/20 via-slate-900 to-slate-800 text-amber-400 shadow-lg shadow-amber-500/10"]) }}>
    @if(!empty($avatarUrl))
        <img src="{{ \Illuminate\Support\Str::startsWith($avatarUrl, 'http') ? $avatarUrl : asset('storage/' . $avatarUrl) }}"
             alt="{{ $name ?: 'User' }}"
             class="w-full h-full object-cover"
             onerror="this.style.display='none'; this.nextElementSibling.style.display='flex';" />
        <span style="display: none;" class="w-full h-full items-center justify-center uppercase">
            {{ $initials ?: '🏏' }}
        </span>
    @elseif(!empty($initials))
        <span class="uppercase">{{ $initials }}</span>
    @else
        <!-- Guest / Default Cricket Icon -->
        <span class="leading-none">🏏</span>
    @endif
</div>

