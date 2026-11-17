@extends('components.layouts.app')

@section('content')
<div class="min-h-screen bg-slate-950 text-slate-100 font-sans antialiased pb-24 selection:bg-amber-500 selection:text-black flex flex-col justify-between">
    <div>
        <x-header />

        <main class="max-w-4xl mx-auto px-4 sm:px-6 py-8 sm:py-10">
            <!-- Breadcrumbs / Top Indicator -->
            <div class="border-b border-slate-800 pb-5 mb-8">
                <div class="flex items-center gap-2 text-xs font-black uppercase tracking-widest text-amber-400">
                    <a href="{{ route('home') }}" wire:navigate class="hover:underline text-slate-400">Home</a>
                    <span>/</span>
                    <span>About Us</span>
                </div>
                <h1 class="text-2xl sm:text-4xl font-black text-white mt-2 tracking-tight">About Cricwire</h1>
                <p class="text-xs text-slate-400 mt-2">Ultra-Fast Live Cricket Line &amp; Modern Cricket Media Platform</p>
            </div>

            <div class="space-y-8 text-slate-300 text-sm leading-relaxed">
                
                <!-- BRAND HERO / STORY -->
                <div class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-slate-900/90 to-amber-950/20 border border-slate-800 p-6 sm:p-8">
                    <div class="relative z-10 max-w-2xl space-y-4">
                        <span class="inline-flex items-center gap-2 px-3 py-1 rounded-full text-xs font-bold bg-amber-500/10 border border-amber-500/30 text-amber-400">
                            🏏 The Next-Gen Cricket Companion
                        </span>
                        <h2 class="text-xl sm:text-3xl font-extrabold text-white leading-tight">
                            Bringing the stadium pulse to your fingertips with zero delay.
                        </h2>
                        <p class="text-slate-300 text-sm sm:text-base leading-relaxed">
                            <strong>Cricwire (cricwire.in)</strong> was built by passionate cricket enthusiasts and engineers frustrated by bloated, sluggish sports apps. We created Cricwire with a singular focus: delivering instantaneous ball-by-ball match updates, accurate commentary, and comprehensive statistics wrapped in an ultra-clean, battery-efficient dark interface.
                        </p>
                    </div>
                </div>

                <!-- KEY PILLARS -->
                <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                    <!-- Pillar 1 -->
                    <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-5 hover:border-amber-500/40 transition">
                        <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-xl mb-3">
                            ⚡
                        </div>
                        <h3 class="text-base font-bold text-white mb-2">Fast Live Line</h3>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            Real-time score updates engineered with optimized sports telemetry feeds, ensuring you stay ahead with ball-by-ball scores, session updates, and match status as action happens on the pitch.
                        </p>
                    </div>

                    <!-- Pillar 2 -->
                    <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-5 hover:border-amber-500/40 transition">
                        <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-xl mb-3">
                            📊
                        </div>
                        <h3 class="text-base font-bold text-white mb-2">Deep Ball Analytics</h3>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            Comprehensive scorecards, partnership breakdowns, run-rate trajectories, bowling figures, and historical head-to-head match stats across International, T20 Leagues, and domestic tournaments.
                        </p>
                    </div>

                    <!-- Pillar 3 -->
                    <div class="bg-slate-900/60 border border-slate-800/80 rounded-2xl p-5 hover:border-amber-500/40 transition">
                        <div class="w-10 h-10 rounded-xl bg-amber-500/10 border border-amber-500/20 flex items-center justify-center text-xl mb-3">
                            📰
                        </div>
                        <h3 class="text-base font-bold text-white mb-2">News &amp; Fan Polls</h3>
                        <p class="text-xs text-slate-400 leading-relaxed">
                            Curated cricket journalism, tournament insights, match previews, and dynamic fan community polls to voice your opinion on match outcomes, star player performances, and controversies.
                        </p>
                    </div>
                </div>

                <!-- OUR MISSION -->
                <section class="space-y-3 bg-slate-900/40 border border-slate-800/80 rounded-2xl p-5 sm:p-6">
                    <h2 class="text-lg font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <span class="text-amber-400">🎯</span> Our Mission
                    </h2>
                    <p class="text-slate-300">
                        Our mission is to empower global cricket fans with honest, fast, and accessible match data without spam or deceptive gambling ads. Whether you follow the ICC World Cup, Indian Premier League (IPL), Big Bash League (BBL), Test Championships, or Women's cricket series, Cricwire brings you complete ball-by-ball coverage in an intuitive, responsive mobile &amp; web experience.
                    </p>
                </section>

                <!-- EDITORIAL INDEPENDENCE & ETHICS -->
                <section class="space-y-3 bg-slate-900/40 border border-slate-800/80 rounded-2xl p-5 sm:p-6">
                    <h2 class="text-lg font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <span class="text-amber-400">🛡️</span> Editorial Independence &amp; Fair Play
                    </h2>
                    <p class="text-slate-300">
                        Cricwire is strictly a sports information, journalism, and statistics portal. We are committed to ethical sports reporting and do <strong>not</strong> operate any betting or real-money gaming operations. All third-party team marks, competition trademarks, and player names belong to their respective copyright owners and are utilized in accordance with Fair Use guidelines for sports commentary and news reporting.
                    </p>
                </section>

                <!-- CONTACT / CONNECT -->
                <section class="space-y-3 pt-6 border-t border-slate-800">
                    <h2 class="text-lg font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <span class="text-amber-400">📬</span> Connect With Our Team
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-400">
                        Have editorial tips, feature requests, partnership inquiries, or general feedback? We'd love to hear from you:
                    </p>
                    <div class="flex flex-wrap gap-3 pt-1">
                        <a href="mailto:team@cricwire.in" class="inline-flex items-center gap-2 bg-slate-900 border border-slate-800 hover:border-amber-500/50 rounded-xl px-4 py-3 text-xs sm:text-sm font-mono text-amber-400 transition">
                            <span>✉️</span>
                            <span>team@cricwire.in</span>
                        </a>
                        <a href="{{ route('home') }}" wire:navigate class="inline-flex items-center gap-2 bg-slate-900 border border-slate-800 hover:border-amber-500/50 rounded-xl px-4 py-3 text-xs sm:text-sm font-semibold text-slate-300 hover:text-white transition">
                            <span>🏏</span>
                            <span>Explore Live Matches</span>
                        </a>
                    </div>
                </section>

            </div>
        </main>
    </div>

    <x-footer />
    <x-bottom-nav activeTab="more" />
</div>
@endsection

