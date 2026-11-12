@extends('components.layouts.app')

@section('content')
<div class="max-w-md mx-auto min-h-screen bg-slate-950 text-slate-100 pb-20 font-sans antialiased relative">
    <header class="sticky top-0 z-40 bg-slate-900/90 backdrop-blur-xl border-b border-white/10 px-4 py-3 flex items-center justify-between shadow-lg">
        <div class="flex items-center space-x-2.5">
            <a href="{{ url('/more') }}" wire:navigate class="p-1.5 -ml-1.5 rounded-lg bg-slate-800/80 hover:bg-slate-800 text-slate-300 hover:text-white transition active:scale-95" aria-label="Go back">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7" />
                </svg>
            </a>
            <h1 class="text-sm font-bold text-white tracking-tight">Terms &amp; Conditions</h1>
        </div>
    </header>

    <main class="p-4 space-y-4 text-xs text-slate-300 leading-relaxed">
        <div class="bg-slate-900/80 border border-slate-800 rounded-2xl p-4 shadow-md space-y-3">
            <h2 class="text-sm font-bold text-white">1. Acceptance of Terms</h2>
            <p>
                By accessing or using the Cricwire platform (cricwire.in / cricketfans), you agree to be bound by these Terms and Conditions. Our services provide live cricket scores, statistics, editorial news, and community polling for entertainment and informational purposes only.
            </p>

            <h2 class="text-sm font-bold text-white">2. No Real-Money Gaming or Betting</h2>
            <p>
                Cricwire is strictly an analytics and live score feed platform. We do not operate, host, promote, or encourage any form of gambling, sports betting, or real-money wagering. Any match odds or rate indicators displayed are simulated market metrics for informational review only.
            </p>

            <h2 class="text-sm font-bold text-white">3. Intellectual Property</h2>
            <p>
                All brand logos, trademarks, and team representations belong to their respective copyright holders. Cricwire uses these under fair use principles for sports reporting.
            </p>

            <h2 class="text-sm font-bold text-white">4. User Account &amp; Privacy</h2>
            <p>
                Users may sign in using Google OAuth. You can request or perform immediate account deletion at any time directly through the More &amp; Settings menu.
            </p>

            <h2 class="text-sm font-bold text-white">5. Contact Information</h2>
            <p>
                For legal inquiries or feedback, please email us directly at <a href="mailto:team@cricwire.in" class="text-amber-400 font-bold underline">team@cricwire.in</a>.
            </p>
        </div>
    </main>

    <x-bottom-nav activeTab="more" />
</div>
@endsection

