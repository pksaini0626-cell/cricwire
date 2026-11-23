@php
    $shareUrl = urlencode(url()->current());
    $shareTitle = urlencode($article->title);
@endphp

<div x-data="{ copied: false }" class="p-4 rounded-2xl bg-slate-900/70 border border-slate-800 flex flex-wrap items-center justify-between gap-3 shadow-sm">
    <div class="flex items-center space-x-2">
        <span class="text-xs font-bold uppercase tracking-wider text-slate-400">Share Story:</span>
    </div>

    <div class="flex flex-wrap items-center gap-2">
        <!-- Copy Link with Instant Toast State -->
        <button type="button"
            @click="navigator.clipboard.writeText('{{ url()->current() }}'); copied = true; setTimeout(() => copied = false, 2500)"
            class="inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-slate-200 border border-slate-700 text-xs font-semibold transition cursor-pointer">
            <span>🔗</span>
            <span x-text="copied ? 'Copied!' : 'Copy Link'">Copy Link</span>
        </button>

        <!-- Direct WhatsApp Share -->
        <a href="https://api.whatsapp.com/send?text={{ $shareTitle }}%20-%20{{ $shareUrl }}"
           target="_blank" rel="noopener noreferrer"
           class="inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-xl bg-emerald-500/15 hover:bg-emerald-500/25 text-emerald-400 border border-emerald-500/30 text-xs font-semibold transition">
            <span>💬</span>
            <span>WhatsApp</span>
        </a>

        <!-- Direct Telegram Share -->
        <a href="https://t.me/share/url?url={{ $shareUrl }}&text={{ $shareTitle }}"
           target="_blank" rel="noopener noreferrer"
           class="inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-xl bg-sky-500/15 hover:bg-sky-500/25 text-sky-400 border border-sky-500/30 text-xs font-semibold transition">
            <span>✈️</span>
            <span>Telegram</span>
        </a>

        <!-- Direct X (Twitter) Share -->
        <a href="https://twitter.com/intent/tweet?text={{ $shareTitle }}&url={{ $shareUrl }}"
           target="_blank" rel="noopener noreferrer"
           class="inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-xl bg-slate-800 hover:bg-slate-700 text-white border border-slate-700 text-xs font-semibold transition">
            <span class="font-bold">𝕏</span>
            <span>Post</span>
        </a>

        <!-- Direct Facebook Share -->
        <a href="https://www.facebook.com/sharer/sharer.php?u={{ $shareUrl }}"
           target="_blank" rel="noopener noreferrer"
           class="inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-xl bg-blue-600/15 hover:bg-blue-600/25 text-blue-400 border border-blue-500/30 text-xs font-semibold transition">
            <span>📘</span>
            <span>Facebook</span>
        </a>
    </div>
</div>
