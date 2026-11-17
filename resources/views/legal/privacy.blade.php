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
                <h1 class="text-2xl sm:text-4xl font-black text-white mt-2 tracking-tight">Privacy Policy & Disclaimers</h1>
                <p class="text-xs text-slate-400 mt-2">Effective Date: November 2026 | Cricwire Platform (cricwire.in)</p>
            </div>

            <div class="space-y-8 text-slate-300 text-sm leading-relaxed">
                
                <!-- CRITICAL NON-GAMBLING DISCLAIMER BOX -->
                <div class="bg-amber-500/10 border-2 border-amber-500/40 rounded-2xl p-5 sm:p-6 text-amber-200 shadow-xl">
                    <h3 class="text-base sm:text-lg font-black text-amber-400 uppercase tracking-wide mb-3 flex items-center gap-2">
                        <span>⚠️</span> Non-Gambling & Informational Disclaimer (Play Store & Legal Notice)
                    </h3>
                    <p class="text-xs sm:text-sm leading-relaxed mb-3">
                        <strong class="text-white">CRICWIRE (cricwire.in) is strictly a live cricket score, sports statistics, ball-by-ball commentary, and informational sports analytics platform.</strong> We do <strong>NOT</strong> facilitate, operate, host, promote, or encourage real-money betting, gambling, wagering, bookmaking, or fantasy cash deposit games of any nature.
                    </p>
                    <p class="text-xs sm:text-sm leading-relaxed text-amber-300/90">
                        Cricwire does <strong>NOT</strong> collect deposits, accept wagers, or distribute cash payouts. Cricwire is not affiliated with, endorsed by, or partnered with any illegal gambling or wagering syndicates, betting exchanges, or underground bookmaking ("Khai-Lagai") operations. Any match rate indicators or analytical metrics displayed are simulated statistical numbers derived solely from publicly available historical data and third-party sports information feeds for journalistic review.
                    </p>
                </div>

                <!-- SECTION 1: NATURE OF SERVICE -->
                <section class="space-y-3 bg-slate-900/40 border border-slate-800/80 rounded-2xl p-5">
                    <h2 class="text-lg font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <span class="text-amber-400">1.</span> Nature of Service
                    </h2>
                    <p>
                        Cricwire provides real-time cricket scores, fast live line ball-by-ball editorial commentary, team squads, tournament fixtures, player analytics, curated sports news, and interactive fan engagement polls. The platform is created purely for cricket journalism, entertainment, and sports enthusiast insights.
                    </p>
                </section>

                <!-- SECTION 2: INFORMATION WE COLLECT -->
                <section class="space-y-3 bg-slate-900/40 border border-slate-800/80 rounded-2xl p-5">
                    <h2 class="text-lg font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <span class="text-amber-400">2.</span> Information We Collect
                    </h2>
                    <p>
                        We respect your personal privacy and practice data minimization:
                    </p>
                    <ul class="list-disc pl-5 space-y-2 text-xs sm:text-sm text-slate-300">
                        <li>
                            <strong class="text-white">Google Socialite OAuth Login:</strong> When you choose to authenticate via Google Sign-In, we receive only your public profile name, verified email address, and avatar URL to authenticate your session and personalize community polls. We do not access your Google contacts, drive, or private files.
                        </li>
                        <li>
                            <strong class="text-white">Local Device Storage:</strong> We use browser / webview LocalStorage on your device to store user UI preferences (such as pinned matches, hide/show filters, and sound notification settings). This data remains locally on your device and is not sold.
                        </li>
                        <li>
                            <strong class="text-white">Telemetry & Log Information:</strong> Standard server logs (such as IP addresses, browser user-agent, operating system, and request timestamps) are automatically logged for security analysis, DDoS mitigation, and server performance monitoring.
                        </li>
                        <li>
                            <strong class="text-white">Zero Financial Information:</strong> Because Cricwire does not conduct payments, betting, or digital commerce, we never request, process, or store credit card numbers, bank account details, UPI PINs, or crypto wallets.
                        </li>
                    </ul>
                </section>

                <!-- SECTION 3: THIRD-PARTY DATA FEEDS & APIS -->
                <section class="space-y-3 bg-slate-900/40 border border-slate-800/80 rounded-2xl p-5">
                    <h2 class="text-lg font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <span class="text-amber-400">3.</span> Third-Party Sports APIs & Feeds
                    </h2>
                    <p>
                        Match fixtures, ball-by-ball updates, commentary, and tournament points tables are provided through syndicated third-party sports data providers and APIs. While we make every effort to deliver instantaneous updates, Cricwire makes no express or implied warranty regarding 100% uninterrupted delivery, zero-millisecond latency, or error-free feed synchronization.
                    </p>
                </section>

                <!-- SECTION 4: AD NETWORKS & EXTERNAL LINKS -->
                <section class="space-y-3 bg-slate-900/40 border border-slate-800/80 rounded-2xl p-5">
                    <h2 class="text-lg font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <span class="text-amber-400">4.</span> Third-Party Ad Networks & Cookies
                    </h2>
                    <p>
                        Cricwire may display banner advertisements, sponsored content, or affiliate links served by certified third-party ad networks (such as Google AdSense or certified ad partners).
                    </p>
                    <ul class="list-disc pl-5 space-y-1.5 text-xs sm:text-sm text-slate-300">
                        <li>Third-party ad vendors, including Google, use cookies to serve ads based on prior visits to our site or other websites.</li>
                        <li>Users can opt out of personalized advertising by visiting Google's Ads Settings (<a href="https://www.google.com/settings/ads" target="_blank" rel="noopener noreferrer" class="text-amber-400 underline">google.com/settings/ads</a>).</li>
                        <li>Cricwire has no control over and assumes no responsibility for the content, privacy practices, or products found on linked third-party websites.</li>
                    </ul>
                </section>

                <!-- SECTION 5: DATA RETENTION & USER ACCOUNT DELETION RIGHTS -->
                <section class="space-y-3 bg-slate-900/40 border border-slate-800/80 rounded-2xl p-5">
                    <h2 class="text-lg font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <span class="text-amber-400">5.</span> User Data Deletion & Privacy Rights (GDPR & Play Store Compliance)
                    </h2>
                    <p>
                        In strict compliance with Google Play Developer Policies and global privacy standards, users retain complete control over their personal data:
                    </p>
                    <ul class="list-disc pl-5 space-y-2 text-xs sm:text-sm text-slate-300">
                        <li>
                            <strong class="text-white">Immediate Self-Service Account Deletion:</strong> Authenticated users can permanently delete their account and associated profile data at any time by navigating to <strong>More &rarr; Settings &rarr; Delete Account</strong> within the application.
                        </li>
                        <li>
                            <strong class="text-white">Deletion by Email Request:</strong> You can also email our privacy team at <a href="mailto:team@cricwire.in" class="text-amber-400 font-mono underline">team@cricwire.in</a> with the subject "Data Deletion Request". Upon verification, your profile, poll responses, and authorization tokens will be permanently scrubbed from our database within 48 hours.
                        </li>
                    </ul>
                </section>

                <!-- SECTION 6: CHILDREN'S PRIVACY -->
                <section class="space-y-3 bg-slate-900/40 border border-slate-800/80 rounded-2xl p-5">
                    <h2 class="text-lg font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <span class="text-amber-400">6.</span> Children's Online Privacy Protection
                    </h2>
                    <p>
                        Cricwire does not knowingly solicit or collect personally identifiable information from children under the age of 13. If you believe your child has provided us with personal data, please contact us immediately, and we will promptly purge such records.
                    </p>
                </section>

                <!-- SECTION 7: GRIEVANCE & CONTACT -->
                <section id="grievance" class="space-y-3 pt-6 border-t border-slate-800">
                    <h2 class="text-lg font-bold text-white uppercase tracking-wider flex items-center gap-2">
                        <span class="text-amber-400">7.</span> Grievance Officer & Contact
                    </h2>
                    <p class="text-xs sm:text-sm text-slate-400">
                        For any legal inquiries, copyright concerns, privacy questions, or data deletion queries, please contact our Compliance Officer:
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
