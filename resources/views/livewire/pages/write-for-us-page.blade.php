<div class="min-h-screen bg-slate-950 text-slate-100 font-sans antialiased selection:bg-amber-500 selection:text-black flex flex-col">
    <!-- Top Global Header -->
    <x-header />

    <main class="flex-1 max-w-4xl mx-auto w-full px-4 py-8 pb-28 md:pb-14 space-y-8">
        <!-- Hero & Editorial Guidelines Banner -->
        <section class="relative overflow-hidden rounded-3xl bg-gradient-to-br from-slate-900 via-slate-900 to-amber-950/30 border border-amber-500/30 p-6 sm:p-8 shadow-2xl">
            <div class="absolute -top-16 -right-16 w-48 h-48 rounded-full bg-amber-500/10 blur-3xl pointer-events-none"></div>
            <div class="absolute -bottom-16 -left-16 w-48 h-48 rounded-full bg-emerald-500/10 blur-3xl pointer-events-none"></div>

            <div class="relative space-y-5">
                <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-amber-500/15 border border-amber-400/30 text-amber-300 text-[11px] font-black uppercase tracking-wider">
                    <span>🏏</span>
                    <span>Cricwire Community Desk</span>
                </div>

                <div class="space-y-2">
                    <h1 class="text-2xl sm:text-3xl font-black text-white tracking-tight leading-tight">
                        ✍️ Write for Cricwire — Publish Your Cricket Analysis &amp; Match Previews
                    </h1>
                    <p class="text-sm sm:text-base text-slate-300 leading-relaxed max-w-2xl">
                        Share data-backed match previews, pitch reports, tactical breakdowns, and fantasy cricket insights with passionate cricket fans. Every article is reviewed by our editorial desk before going live.
                    </p>
                </div>

                <!-- 3-Point Editorial Guidelines -->
                <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 pt-2">
                    <div class="p-3.5 rounded-2xl bg-slate-950/70 border border-slate-800/90 space-y-1">
                        <div class="flex items-center gap-2 text-xs font-extrabold text-amber-400">
                            <span>🏏</span>
                            <span>100% Cricket Topics</span>
                        </div>
                        <p class="text-[11px] text-slate-400 leading-relaxed">
                            Only international, domestic, or T20 league cricket analysis, pitch reports, and previews.
                        </p>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-slate-950/70 border border-slate-800/90 space-y-1">
                        <div class="flex items-center gap-2 text-xs font-extrabold text-emerald-400">
                            <span>✨</span>
                            <span>Original Writing</span>
                        </div>
                        <p class="text-[11px] text-slate-400 leading-relaxed">
                            Minimum 1,200 characters (~250+ words) of authentic human insight. Zero copied or spun text.
                        </p>
                    </div>

                    <div class="p-3.5 rounded-2xl bg-slate-950/70 border border-slate-800/90 space-y-1">
                        <div class="flex items-center gap-2 text-xs font-extrabold text-rose-400">
                            <span>🛡️</span>
                            <span>Zero Betting/Casino Links</span>
                        </div>
                        <p class="text-[11px] text-slate-400 leading-relaxed">
                            No gambling, betting, or spam links. External references are automatically marked <code class="text-slate-300">nofollow</code>.
                        </p>
                    </div>
                </div>
            </div>
        </section>

        @guest
            <!-- Auth Gate -->
            <section class="rounded-3xl bg-slate-900/90 border border-slate-800 p-6 sm:p-10 text-center shadow-xl space-y-5">
                <div class="w-14 h-14 mx-auto rounded-2xl bg-amber-500/15 border border-amber-400/30 flex items-center justify-center text-2xl shadow-inner">
                    🔐
                </div>
                <div class="space-y-2 max-w-lg mx-auto">
                    <h2 class="text-xl font-black text-white">Sign in to Submit Your Cricket Article</h2>
                    <p class="text-sm text-slate-400 leading-relaxed">
                        To prevent automated spam and attribute credit accurately to your contributor profile, please sign in with your Google account. You will return right back here.
                    </p>
                </div>

                <div class="pt-1">
                    <a
                        href="{{ route('auth.google', ['intended' => '/write-for-us']) }}"
                        class="inline-flex items-center justify-center gap-3 px-6 py-3.5 rounded-2xl bg-gradient-to-r from-amber-400 via-yellow-400 to-amber-500 hover:from-amber-300 hover:to-amber-400 text-slate-950 font-black text-sm tracking-wide shadow-lg shadow-amber-500/25 transition active:scale-[0.99]"
                    >
                        <svg class="w-5 h-5" viewBox="0 0 24 24" fill="currentColor" aria-hidden="true">
                            <path d="M12.545,10.239v3.821h5.445c-0.712,2.315-2.647,3.972-5.445,3.972c-3.332,0-6.033-2.701-6.033-6.032s2.701-6.032,6.033-6.032c1.498,0,2.866,0.549,3.921,1.453l2.814-2.814C17.503,2.988,15.139,2,12.545,2C7.021,2,2.543,6.477,2.543,12s4.478,10,10.002,10c8.396,0,10.249-7.85,9.426-11.761H12.545z"/>
                        </svg>
                        <span>Continue with Google to Submit Article</span>
                    </a>
                </div>
            </section>
        @else
            <!-- Confirmation Banner -->
            @if($submittedSuccess)
                <div class="rounded-2xl bg-emerald-950/50 border border-emerald-500/40 p-5 shadow-lg flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                    <div class="flex items-start gap-3">
                        <span class="text-2xl shrink-0">✅</span>
                        <div class="space-y-1">
                            <h3 class="text-sm sm:text-base font-black text-emerald-300">
                                Article Submitted for Editorial Review!
                            </h3>
                            <p class="text-xs sm:text-sm text-slate-300 leading-relaxed">
                                <strong class="text-white">"{{ $submittedTitle }}"</strong> has been queued as <span class="px-2 py-0.5 rounded bg-amber-500/20 text-amber-300 font-bold text-[11px]">Pending Review</span>. Once approved by our editors, it will appear live on Cricwire News with your author credit.
                            </p>
                        </div>
                    </div>
                </div>
            @endif

            <!-- Rate Limit Warning -->
            @if($rateLimitError || $errors->has('rate_limit'))
                <div class="rounded-2xl bg-rose-950/50 border border-rose-500/40 p-4 text-xs sm:text-sm text-rose-200 flex items-center gap-3">
                    <span class="text-xl">⏳</span>
                    <span>{{ $rateLimitError ?: $errors->first('rate_limit') }}</span>
                </div>
            @endif

            <!-- Guest Submission Form -->
            <section
                class="rounded-3xl bg-slate-900/90 border border-slate-800 p-5 sm:p-8 shadow-2xl space-y-6"
                x-data="{
                    content: $wire.entangle('content'),
                    title: $wire.entangle('title'),
                    summary: $wire.entangle('summary'),
                    tab: 'write',
                    get charCount() {
                        return (this.content || '').length;
                    },
                    get wordCount() {
                        const stripped = (this.content || '').replace(/<[^>]*>/g, ' ').trim();
                        return stripped ? stripped.split(/\s+/).length : 0;
                    },
                    get progressPercent() {
                        return Math.min(100, Math.round((this.charCount / 1200) * 100));
                    },
                    wrapSelection(before, after) {
                        const el = this.$refs.editor;
                        if (!el) return;
                        const start = el.selectionStart || 0;
                        const end = el.selectionEnd || 0;
                        const val = this.content || '';
                        const selected = val.substring(start, end) || 'text';
                        const updated = val.substring(0, start) + before + selected + after + val.substring(end);
                        this.content = updated;
                        this.$nextTick(() => {
                            el.focus();
                            const cursor = start + before.length + selected.length + after.length;
                            el.setSelectionRange(cursor, cursor);
                        });
                    },
                    insertLink() {
                        const url = window.prompt('Enter reference URL (https://...)');
                        if (!url) return;
                        this.wrapSelection(`<a href=&quot;${url}&quot;>`, '</a>');
                    }
                }"
            >
                <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pb-4 border-b border-slate-800">
                    <div>
                        <h2 class="text-lg font-black text-white flex items-center gap-2">
                            <span>📝</span>
                            <span>Submit a Cricket Article</span>
                        </h2>
                        <p class="text-xs text-slate-400 mt-0.5">
                            Signed in as <span class="text-slate-200 font-semibold">{{ auth()->user()->name }}</span> ({{ auth()->user()->email }})
                        </p>
                    </div>

                    <div class="inline-flex items-center gap-2 px-3 py-1.5 rounded-xl bg-slate-950 border border-slate-800 text-xs font-mono">
                        <span class="w-2 h-2 rounded-full {{ $remainingToday > 0 ? 'bg-emerald-400' : 'bg-rose-400' }}"></span>
                        <span class="text-slate-300">
                            <strong class="{{ $remainingToday > 0 ? 'text-emerald-400' : 'text-rose-400' }}">{{ $remainingToday }}/3</strong> submissions left today
                        </span>
                    </div>
                </div>

                <form wire:submit="submit" class="space-y-6">
                    <!-- Headline -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label for="guest-title" class="block text-xs font-bold uppercase tracking-wider text-slate-300">
                                Article Headline <span class="text-rose-400">*</span>
                            </label>
                            <span class="text-[11px] font-mono text-slate-500" x-text="(title || '').length + ' / 140'"></span>
                        </div>
                        <input
                            id="guest-title"
                            type="text"
                            wire:model="title"
                            maxlength="140"
                            placeholder="e.g. Why India's Middle-Over Spin Strategy Could Decide the Champions Trophy Final"
                            class="w-full rounded-xl bg-slate-950 border border-slate-800 focus:border-amber-400 focus:ring-1 focus:ring-amber-400/50 px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none transition"
                        >
                        @error('title') <p class="text-xs text-rose-400 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <!-- Category & Series Row -->
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div class="space-y-1.5">
                            <label for="guest-category" class="block text-xs font-bold uppercase tracking-wider text-slate-300">
                                Category <span class="text-rose-400">*</span>
                            </label>
                            <select
                                id="guest-category"
                                wire:model="category"
                                class="w-full rounded-xl bg-slate-950 border border-slate-800 focus:border-amber-400 focus:ring-1 focus:ring-amber-400/50 px-4 py-3 text-sm text-white focus:outline-none transition"
                            >
                                <option value="news">Cricket News &amp; Analysis</option>
                                <option value="pitch_report">Pitch Report</option>
                                <option value="prediction">Match Prediction</option>
                                <option value="fantasy_tips">Fantasy XI Tips</option>
                            </select>
                            @error('category') <p class="text-xs text-rose-400 font-medium">{{ $message }}</p> @enderror
                        </div>

                        <div class="space-y-1.5">
                            <label for="guest-series" class="block text-xs font-bold uppercase tracking-wider text-slate-300">
                                Related Series <span class="text-slate-500 font-normal normal-case">(Optional)</span>
                            </label>
                            <select
                                id="guest-series"
                                wire:model="series_name"
                                class="w-full rounded-xl bg-slate-950 border border-slate-800 focus:border-amber-400 focus:ring-1 focus:ring-amber-400/50 px-4 py-3 text-sm text-white focus:outline-none transition"
                            >
                                <option value="">General Cricket / No Specific Series</option>
                                @foreach($activeSeries as $seriesOption)
                                    <option value="{{ $seriesOption }}">{{ $seriesOption }}</option>
                                @endforeach
                            </select>
                            @error('series_name') <p class="text-xs text-rose-400 font-medium">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <!-- Summary -->
                    <div class="space-y-1.5">
                        <div class="flex items-center justify-between">
                            <label for="guest-summary" class="block text-xs font-bold uppercase tracking-wider text-slate-300">
                                Short Summary / Lead Excerpt <span class="text-rose-400">*</span>
                            </label>
                            <span class="text-[11px] font-mono text-slate-500" x-text="(summary || '').length + ' / 300'"></span>
                        </div>
                        <textarea
                            id="guest-summary"
                            rows="2"
                            wire:model="summary"
                            maxlength="300"
                            placeholder="Write a crisp 2-sentence takeaway summarizing your cricket analysis (40–300 characters)..."
                            class="w-full rounded-xl bg-slate-950 border border-slate-800 focus:border-amber-400 focus:ring-1 focus:ring-amber-400/50 px-4 py-3 text-sm text-white placeholder-slate-500 focus:outline-none transition"
                        ></textarea>
                        @error('summary') <p class="text-xs text-rose-400 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <!-- Featured Image Uploader (500 KB max) -->
                    <div class="space-y-2">
                        <label for="guest-image" class="block text-xs font-bold uppercase tracking-wider text-slate-300">
                            Featured Banner Image <span class="text-rose-400">*</span>
                            <span class="text-[11px] text-slate-500 font-normal normal-case ml-1">(JPG, PNG, WEBP • Max 500 KB)</span>
                        </label>

                        <div class="rounded-2xl bg-slate-950 border border-dashed border-slate-700 hover:border-amber-400/60 p-4 transition">
                            <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
                                <div class="space-y-1">
                                    <input
                                        id="guest-image"
                                        type="file"
                                        wire:model="featured_image"
                                        accept=".jpg,.jpeg,.png,.webp,image/jpeg,image/png,image/webp"
                                        class="block w-full text-xs text-slate-300 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-black file:bg-amber-400 file:text-slate-950 hover:file:bg-amber-300 cursor-pointer"
                                    >
                                    <p class="text-[11px] text-slate-500">
                                        Recommended landscape 1200×630 banner. Strict 500 KB maximum file size.
                                    </p>
                                    <div wire:loading wire:target="featured_image" class="text-xs text-amber-400 font-semibold">
                                        Uploading &amp; verifying image...
                                    </div>
                                </div>

                                @if($featured_image && method_exists($featured_image, 'temporaryUrl'))
                                    <div class="shrink-0">
                                        <img
                                            src="{{ $featured_image->temporaryUrl() }}"
                                            alt="Featured image preview"
                                            class="w-36 h-20 object-cover rounded-xl border border-amber-400/40 shadow-md"
                                        >
                                    </div>
                                @endif
                            </div>
                        </div>
                        @error('featured_image') <p class="text-xs text-rose-400 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <!-- Formatted Content Editor with Live Word/Character Counter -->
                    <div class="space-y-2">
                        <div class="flex flex-wrap items-center justify-between gap-2">
                            <label for="guest-content" class="block text-xs font-bold uppercase tracking-wider text-slate-300">
                                Full Article Content <span class="text-rose-400">*</span>
                            </label>
                            <div class="flex items-center gap-3 text-[11px] font-mono">
                                <span class="text-slate-400">
                                    <strong class="text-white" x-text="wordCount"></strong> words
                                </span>
                                <span :class="charCount >= 1200 ? 'text-emerald-400 font-bold' : 'text-amber-400 font-bold'">
                                    <span x-text="charCount.toLocaleString()"></span> / 1,200 min chars
                                </span>
                            </div>
                        </div>

                        <!-- Progress Bar -->
                        <div class="w-full h-1.5 rounded-full bg-slate-800 overflow-hidden">
                            <div
                                class="h-full transition-all duration-200 rounded-full"
                                :class="charCount >= 1200 ? 'bg-emerald-500' : 'bg-amber-400'"
                                :style="'width: ' + progressPercent + '%'"
                            ></div>
                        </div>

                        <!-- Toolbar + Write/Preview Switch -->
                        <div class="rounded-2xl bg-slate-950 border border-slate-800 overflow-hidden focus-within:border-amber-400 transition">
                            <div class="flex flex-wrap items-center justify-between gap-2 px-3 py-2 bg-slate-900/80 border-b border-slate-800">
                                <div class="flex flex-wrap items-center gap-1">
                                    <button type="button" @click="wrapSelection('<h2>', '</h2>')" class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold transition" title="Heading 2">H2</button>
                                    <button type="button" @click="wrapSelection('<h3>', '</h3>')" class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-bold transition" title="Heading 3">H3</button>
                                    <button type="button" @click="wrapSelection('<strong>', '</strong>')" class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-black transition" title="Bold">B</button>
                                    <button type="button" @click="wrapSelection('<em>', '</em>')" class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs italic font-semibold transition" title="Italic">I</button>
                                    <button type="button" @click="wrapSelection('<blockquote>', '</blockquote>')" class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold transition" title="Quote">❝ Quote</button>
                                    <button type="button" @click="wrapSelection('<ul>\n  <li>', '</li>\n</ul>')" class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-slate-200 text-xs font-semibold transition" title="Bullet List">• List</button>
                                    <button type="button" @click="insertLink()" class="px-2.5 py-1 rounded-lg bg-slate-800 hover:bg-slate-700 text-amber-300 text-xs font-semibold transition" title="Insert Link">🔗 Link</button>
                                </div>

                                <div class="flex items-center rounded-lg bg-slate-950 border border-slate-800 p-0.5 text-[11px] font-bold">
                                    <button type="button" @click="tab = 'write'" :class="tab === 'write' ? 'bg-amber-400 text-slate-950' : 'text-slate-400 hover:text-white'" class="px-2.5 py-1 rounded-md transition">Write</button>
                                    <button type="button" @click="tab = 'preview'" :class="tab === 'preview' ? 'bg-amber-400 text-slate-950' : 'text-slate-400 hover:text-white'" class="px-2.5 py-1 rounded-md transition">Preview</button>
                                </div>
                            </div>

                            <div x-show="tab === 'write'">
                                <textarea
                                    id="guest-content"
                                    x-ref="editor"
                                    rows="14"
                                    wire:model="content"
                                    placeholder="Write your in-depth cricket match analysis, pitch breakdown, key player matchups, or tactical preview here (minimum 1,200 characters). Plain text paragraphs or clean HTML tags (&lt;h2&gt;, &lt;p&gt;, &lt;strong&gt;, &lt;ul&gt;) are supported."
                                    class="w-full bg-transparent px-4 py-3 text-sm text-slate-100 placeholder-slate-500 focus:outline-none leading-relaxed font-sans"
                                ></textarea>
                            </div>

                            <div x-show="tab === 'preview'" x-cloak class="p-4 min-h-[280px] prose prose-invert prose-sm max-w-none text-slate-200">
                                <div x-html="content ? content : '<p class=\'text-slate-500 italic\'>Nothing written yet...</p>'"></div>
                            </div>
                        </div>
                        @error('content') <p class="text-xs text-rose-400 font-medium">{{ $message }}</p> @enderror
                    </div>

                    <!-- Author Credit Details -->
                    <div class="pt-4 border-t border-slate-800 space-y-4">
                        <h3 class="text-xs font-black uppercase tracking-wider text-amber-400 flex items-center gap-2">
                            <span>👤</span>
                            <span>Author Credit Details</span>
                        </h3>

                        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                            <div class="space-y-1.5">
                                <label for="guest-author-name" class="block text-xs font-bold uppercase tracking-wider text-slate-300">
                                    Display Name <span class="text-rose-400">*</span>
                                </label>
                                <input
                                    id="guest-author-name"
                                    type="text"
                                    wire:model="author_name"
                                    maxlength="80"
                                    class="w-full rounded-xl bg-slate-950 border border-slate-800 focus:border-amber-400 focus:ring-1 focus:ring-amber-400/50 px-4 py-2.5 text-sm text-white focus:outline-none transition"
                                >
                                @error('author_name') <p class="text-xs text-rose-400 font-medium">{{ $message }}</p> @enderror
                            </div>

                            <div class="space-y-1.5">
                                <label for="guest-author-social" class="block text-xs font-bold uppercase tracking-wider text-slate-300">
                                    Social / X / LinkedIn URL <span class="text-slate-500 font-normal normal-case">(Optional)</span>
                                </label>
                                <input
                                    id="guest-author-social"
                                    type="url"
                                    wire:model="author_social_url"
                                    maxlength="200"
                                    placeholder="https://x.com/yourhandle"
                                    class="w-full rounded-xl bg-slate-950 border border-slate-800 focus:border-amber-400 focus:ring-1 focus:ring-amber-400/50 px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none transition"
                                >
                                @error('author_social_url') <p class="text-xs text-rose-400 font-medium">{{ $message }}</p> @enderror
                            </div>
                        </div>

                        <div class="space-y-1.5">
                            <label for="guest-author-bio" class="block text-xs font-bold uppercase tracking-wider text-slate-300">
                                Short Author Bio <span class="text-rose-400">*</span>
                            </label>
                            <input
                                id="guest-author-bio"
                                type="text"
                                wire:model="author_bio"
                                maxlength="220"
                                placeholder="e.g. Cricket analyst & fantasy stats writer covering IPL and ICC tournaments"
                                class="w-full rounded-xl bg-slate-950 border border-slate-800 focus:border-amber-400 focus:ring-1 focus:ring-amber-400/50 px-4 py-2.5 text-sm text-white placeholder-slate-500 focus:outline-none transition"
                            >
                            @error('author_bio') <p class="text-xs text-rose-400 font-medium">{{ $message }}</p> @enderror
                        </div>
                    </div>

                    <!-- Cricket-Only Confirmation Checkbox -->
                    <div class="p-4 rounded-2xl bg-slate-950/80 border border-slate-800 space-y-1.5">
                        <label class="flex items-start gap-3 cursor-pointer">
                            <input
                                type="checkbox"
                                wire:model="cricket_only_confirmed"
                                class="mt-0.5 w-4 h-4 rounded border-slate-700 bg-slate-900 text-amber-400 focus:ring-amber-400/50"
                            >
                            <span class="text-xs sm:text-sm text-slate-300 leading-relaxed">
                                I confirm this submission is <strong class="text-white">100% original</strong>, strictly <strong class="text-white">cricket-related</strong>, and contains <strong class="text-white">no gambling, betting, casino, or promotional spam links</strong>.
                            </span>
                        </label>
                        @error('cricket_only_confirmed') <p class="text-xs text-rose-400 font-medium pl-7">{{ $message }}</p> @enderror
                    </div>

                    <!-- Submit CTA -->
                    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 pt-2">
                        <p class="text-[11px] text-slate-400">
                            All submissions enter <span class="text-amber-400 font-semibold">Pending Review</span> and are published only after editorial approval.
                        </p>

                        <button
                            type="submit"
                            wire:loading.attr="disabled"
                            @if($remainingToday <= 0) disabled @endif
                            class="px-6 py-3.5 rounded-2xl bg-gradient-to-r from-amber-400 via-yellow-400 to-amber-500 hover:from-amber-300 hover:to-amber-400 disabled:opacity-50 disabled:cursor-not-allowed text-slate-950 font-black text-sm uppercase tracking-wider shadow-lg shadow-amber-500/25 transition active:scale-[0.99] inline-flex items-center justify-center gap-2"
                        >
                            <span wire:loading.remove wire:target="submit">Submit for Editorial Review →</span>
                            <span wire:loading wire:target="submit">Submitting Article...</span>
                        </button>
                    </div>
                </form>
            </section>

            <!-- My Submissions Tracker -->
            <section class="rounded-3xl bg-slate-900/80 border border-slate-800 p-5 sm:p-7 shadow-xl space-y-4">
                <div class="flex items-center justify-between border-b border-slate-800 pb-3">
                    <h2 class="text-base sm:text-lg font-black text-white flex items-center gap-2">
                        <span>📋</span>
                        <span>My Submissions</span>
                    </h2>
                    <span class="text-xs font-mono text-slate-400">
                        {{ $mySubmissions->count() }} {{ $mySubmissions->count() === 1 ? 'article' : 'articles' }}
                    </span>
                </div>

                @forelse($mySubmissions as $submission)
                    @php
                        $statusBadge = match($submission->status) {
                            'published' => ['label' => 'Published', 'class' => 'bg-emerald-500/15 text-emerald-400 border-emerald-500/30'],
                            'rejected' => ['label' => 'Rejected', 'class' => 'bg-rose-500/15 text-rose-400 border-rose-500/30'],
                            'pending_review' => ['label' => 'Pending Review', 'class' => 'bg-amber-500/15 text-amber-300 border-amber-500/30'],
                            default => ['label' => ucfirst((string) $submission->status), 'class' => 'bg-slate-800 text-slate-300 border-slate-700'],
                        };
                    @endphp

                    <div class="p-4 rounded-2xl bg-slate-950/80 border border-slate-800/90 flex flex-col sm:flex-row sm:items-center justify-between gap-3">
                        <div class="space-y-1 min-w-0">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="px-2.5 py-0.5 rounded-full text-[10px] font-black uppercase tracking-wider border {{ $statusBadge['class'] }}">
                                    {{ $statusBadge['label'] }}
                                </span>
                                <span class="text-[11px] font-mono text-slate-500">
                                    {{ $submission->created_at?->format('M d, Y • h:i A') }}
                                </span>
                                @if($submission->series_name)
                                    <span class="text-[11px] text-slate-400 truncate">• {{ $submission->series_name }}</span>
                                @endif
                            </div>

                            <h3 class="text-sm font-bold text-white truncate">
                                {{ $submission->title }}
                            </h3>

                            @if($submission->status === 'rejected' && $submission->rejection_reason)
                                <p class="text-xs text-rose-300 bg-rose-950/40 border border-rose-500/30 rounded-xl px-3 py-2 mt-2">
                                    <strong>Editor Note:</strong> {{ $submission->rejection_reason }}
                                </p>
                            @endif
                        </div>

                        <div class="shrink-0">
                            @if($submission->status === 'published')
                                <a
                                    href="{{ route('news.show', ['article' => $submission->slug]) }}"
                                    wire:navigate
                                    class="inline-flex items-center gap-1.5 px-3.5 py-2 rounded-xl bg-emerald-500/15 hover:bg-emerald-500/25 border border-emerald-500/30 text-emerald-300 text-xs font-bold transition"
                                >
                                    <span>View Live</span>
                                    <span>↗</span>
                                </a>
                            @endif
                        </div>
                    </div>
                @empty
                    <div class="text-center py-8 text-slate-500 text-xs sm:text-sm">
                        You haven't submitted any guest articles yet. Share your first cricket analysis above!
                    </div>
                @endforelse
            </section>
        @endguest
    </main>

    <!-- Desktop-only Footer -->
    <x-footer />

    <!-- Mobile Bottom Navigation -->
    <x-bottom-nav />
</div>
