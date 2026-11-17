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
                    <span>Legal Compliance</span>
                </div>
                <h1 class="text-2xl sm:text-4xl font-black text-white mt-2 tracking-tight">Terms and Conditions</h1>
                <p class="text-xs text-slate-400 mt-2">Effective Date: November 2026 | Cricwire Platform (cricwire.in)</p>
            </div>

            <div class="space-y-8 text-slate-300 text-sm leading-relaxed">
                
                <!-- ACCEPTANCE -->
                <section class="space-y-3 bg-slate-900/40 border border-slate-800/80 rounded-2xl p-5">
                    <h2 class="text-lg font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <span class="text-amber-400">1.</span> Acceptance of Terms
                    </h2>
                    <p>
                        By visiting, accessing, downloading, or using the Cricwire platform (including the website at <a href="https://cricwire.in" class="text-amber-400 underline">cricwire.in</a>, mobile applications, progressive web applications, and related digital APIs), you acknowledge that you have read, understood, and agreed to be bound by these Terms and Conditions and our Privacy Policy. If you do not agree with any part of these terms, please discontinue using Cricwire immediately.
                    </p>
                </section>

                <!-- STRICT NON-GAMBLING POLICY -->
                <section class="space-y-3 bg-amber-500/10 border-2 border-amber-500/40 rounded-2xl p-5 sm:p-6 text-amber-200 shadow-xl">
                    <h2 class="text-lg font-black text-amber-400 uppercase tracking-wide flex items-center gap-2">
                        <span>⚠️</span> 2. Non-Gambling & Strict Informational Nature
                    </h2>
                    <p class="text-xs sm:text-sm leading-relaxed">
                        Cricwire is an independent digital sports media portal providing sports news, match schedules, live score updates, ball-by-ball editorial feeds, player profiles, and statistical telemetry.
                    </p>
                    <ul class="list-disc pl-5 space-y-1.5 text-xs sm:text-sm text-amber-300/90 mt-2">
                        <li>Cricwire does <strong>NOT</strong> operate, sponsor, facilitate, or promote sports betting, bookmaking, real-money wagering, lotteries, or fantasy cash competitions.</li>
                        <li>No monetary deposits, bets, wagers, or financial exchanges are ever conducted or accepted through Cricwire.</li>
                        <li>Any statistics, win probabilities, or run rate indicators are computed or sourced for purely editorial and mathematical fan analysis. They must never be construed as betting tips or solicitations to gamble.</li>
                    </ul>
                </section>

                <!-- ACCEPTABLE USE -->
                <section class="space-y-3 bg-slate-900/40 border border-slate-800/80 rounded-2xl p-5">
                    <h2 class="text-lg font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <span class="text-amber-400">3.</span> Acceptable Use & Conduct
                    </h2>
                    <p>When interacting with Cricwire features, fan polls, comments, and community channels, you agree not to:</p>
                    <ul class="list-disc pl-5 space-y-2 text-xs sm:text-sm text-slate-300">
                        <li>Engage in abusive, defamatory, threatening, vulgar, or discriminatory conduct towards cricket teams, players, or other community members.</li>
                        <li>Deploy scraping bots, automated scrapers, spiders, or automated requests designed to overwhelm server capacity, bypass rate-limits, or hijack our API feeds without written permission.</li>
                        <li>Post unsolicited promotional spam, betting tips, Telegram channel links, or unauthorized affiliate invitations.</li>
                        <li>Attempt to reverse-engineer, decompile, or compromise the security architecture of the Cricwire web or mobile application.</li>
                    </ul>
                </section>

                <!-- INTELLECTUAL PROPERTY -->
                <section class="space-y-3 bg-slate-900/40 border border-slate-800/80 rounded-2xl p-5">
                    <h2 class="text-lg font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <span class="text-amber-400">4.</span> Intellectual Property & Fair Use
                    </h2>
                    <p>
                        All original editorial content, codebases, UI designs, graphics, branding, and proprietary algorithms on Cricwire are the intellectual property of Cricwire.
                    </p>
                    <p class="text-xs sm:text-sm text-slate-400">
                        Third-party cricket team names, club badges, competition logos (e.g., ICC, BCCI, IPL), and player names displayed on this platform are registered trademarks of their respective governing boards and entities. Their display on Cricwire is purely for non-commercial identification, journalistic reporting, and sports editorial reference under the doctrine of Fair Use. Cricwire claims no affiliation with or endorsement by these sports bodies.
                    </p>
                </section>

                <!-- THIRD-PARTY FEED ACCURACY & DISCLAIMERS -->
                <section class="space-y-3 bg-slate-900/40 border border-slate-800/80 rounded-2xl p-5">
                    <h2 class="text-lg font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <span class="text-amber-400">5.</span> Third-Party Sports Feeds & Accuracy Disclaimers
                    </h2>
                    <p>
                        Live scores, ball trajectories, commentaries, and tournament standings are aggregated using third-party automated sports feeds and rapid telemetry. While we engineer our systems for maximum uptime and precision, live events are subject to technical interruptions, network lag, or third-party feed latency. Cricwire does not warrant that live score coverage will always be error-free or uninterrupted.
                    </p>
                </section>

                <!-- LIMITATION OF LIABILITY -->
                <section class="space-y-3 bg-slate-900/40 border border-slate-800/80 rounded-2xl p-5">
                    <h2 class="text-lg font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <span class="text-amber-400">6.</span> Limitation of Liability
                    </h2>
                    <p>
                        In no event shall Cricwire, its developers, operators, or affiliates be liable for any direct, indirect, incidental, consequential, or punitive damages arising out of your access to or inability to use the service, reliance on any sports scores or editorial analysis, or any interactions with third-party websites linked through advertisements. The service is provided on an "AS IS" and "AS AVAILABLE" basis.
                    </p>
                </section>

                <!-- TERMINATION & MODIFICATIONS -->
                <section class="space-y-3 bg-slate-900/40 border border-slate-800/80 rounded-2xl p-5">
                    <h2 class="text-lg font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <span class="text-amber-400">7.</span> Modifications & Governing Law
                    </h2>
                    <p>
                        We reserve the right to amend these Terms and Conditions at any time. Any changes will be posted on this page with an updated effective date. Continued usage of Cricwire following modifications signifies your acceptance of the revised terms.
                    </p>
                </section>

                <!-- CONTACT -->
                <section class="space-y-3 pt-6 border-t border-slate-800">
                    <h2 class="text-lg font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <span class="text-amber-400">8.</span> Contact Us
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-400">
                        If you have questions or concerns regarding our Terms and Conditions, reach out to us at:
                    </p>
                    <div class="inline-flex items-center gap-2 bg-slate-900 border border-slate-800 rounded-xl px-4 py-3 text-xs sm:text-sm font-mono text-amber-400">
                        <span>✉️</span>
                        <span>team@cricwire.in</span>
                    </div>
                </section>

            </div>
        </main>
    </div>

    <x-footer />
    <x-bottom-nav activeTab="more" />
</div>
@endsection
