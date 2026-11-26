<div id="cricket-loader"
     wire:persist="cricket-loader"
     x-data="{
        isLoading: false,
        safetyTimer: null,
        show() {
            this.isLoading = true;
            clearTimeout(this.safetyTimer);
            // Safety fallback: auto-hide after 4s so user is never stuck
            this.safetyTimer = setTimeout(() => { this.isLoading = false; }, 4000);
        },
        hide() {
            clearTimeout(this.safetyTimer);
            setTimeout(() => { this.isLoading = false; }, 100);
        }
     }"
     x-init="
        // 1. Trigger ONLY on actual SPA page navigation link clicks (never on wire:click or wire:poll)
        document.addEventListener('click', (e) => {
            const link = e.target.closest('a[href]');

            // Trigger on internal navigation links (excluding # anchors, mailto, tel, target=_blank, and nested action buttons)
            if (link && !e.target.closest('button, [data-no-loader]')) {
                const href = link.getAttribute('href');
                const target = link.getAttribute('target');
                if (href && !href.startsWith('#') && !href.startsWith('mailto:') && !href.startsWith('tel:') && target !== '_blank') {
                    try {
                        const url = new URL(href, window.location.origin);
                        if (url.origin === window.location.origin && (url.pathname !== window.location.pathname || url.search !== window.location.search)) {
                            show();
                        }
                    } catch (_) {}
                }
            }
        }, true);

        // 2. Livewire 3 SPA Page Navigation Events ONLY (never global Livewire.hook('request'))
        document.addEventListener('livewire:navigate', () => show());
        document.addEventListener('livewire:navigating', () => show());
        document.addEventListener('livewire:navigated', () => hide());
        document.addEventListener('DOMContentLoaded', () => hide());

        // 3. Browser back/forward cache restore cleanup
        window.addEventListener('pageshow', () => hide());
     "
     x-show="isLoading"
     x-transition:enter="transition ease-out duration-150"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0"
     class="cricket-loader-overlay"
     aria-label="Loading cricket data, please wait"
     role="alert"
     aria-live="assertive"
     style="display: none;">

    <div class="loader-card">
        <!-- Cricket Scene: Stumps + Bails + Ball -->
        <div class="cricket-scene">
            <!-- Stumps with Bails -->
            <div class="stumps">
                <div class="stump"></div>
                <div class="stump"></div>
                <div class="stump"></div>
                <div class="bails">
                    <div class="bail"></div>
                    <div class="bail"></div>
                </div>
            </div>

            <!-- Bouncing Cricket Ball with Rotating Seam -->
            <div class="cricket-ball"></div>
        </div>

        <!-- Loading Text with Animated Dots -->
        <div class="loader-label">
            LOADING
            <span>.</span>
            <span>.</span>
            <span>.</span>
        </div>
    </div>

    <style>
        /* ----- Loader Overlay: Full Viewport & Blocks Multiple Clicks ----- */
        .cricket-loader-overlay {
            position: fixed;
            inset: 0;
            width: 100vw;
            height: 100dvh; /* Dynamic viewport height for mobile browsers */
            min-height: -webkit-fill-available;
            background: rgba(12, 30, 18, 0.92);
            backdrop-filter: blur(4px);
            -webkit-backdrop-filter: blur(4px);
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 1rem;
            padding-top: max(1rem, env(safe-area-inset-top));
            padding-bottom: max(1rem, env(safe-area-inset-bottom));
            padding-left: max(1rem, env(safe-area-inset-left));
            padding-right: max(1rem, env(safe-area-inset-right));
            z-index: 99999;
            pointer-events: all;
            cursor: wait;
            touch-action: none; /* Prevents background scroll/tap on mobile */
        }

        .cricket-loader-overlay * {
            pointer-events: none !important;
            user-select: none;
            -webkit-user-select: none;
        }

        /* ----- Loader Card: Cricket Pitch Theme ----- */
        .loader-card {
            position: relative;
            background: #1e3a2b;
            padding: 2.5rem 3rem 2.8rem;
            border-radius: 3.5rem 3.5rem 2rem 2rem;
            box-shadow: 0 30px 35px -10px rgba(0, 0, 0, 0.7),
                        0 0 0 2px #b68b4b,
                        0 0 0 6px #2d4d2d;
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 1.8rem;
            max-width: 90vw;
        }

        /* ----- Cricket Ball + Stumps Scene ----- */
        .cricket-scene {
            position: relative;
            display: flex;
            align-items: flex-end;
            justify-content: center;
            gap: 0.8rem;
            filter: drop-shadow(0 10px 8px rgba(0, 0, 0, 0.4));
        }

        .cricket-scene::before {
            content: '🏏';
            position: absolute;
            font-size: 2.2rem;
            left: -28px;
            top: -28px;
            opacity: 0.22;
            transform: rotate(-25deg);
            filter: drop-shadow(0 0 6px gold);
        }

        /* ----- Stumps & Bails ----- */
        .stumps {
            display: flex;
            align-items: flex-end;
            gap: 6px;
            position: relative;
            margin-right: 8px;
        }

        .stump {
            width: 12px;
            height: 75px;
            background: linear-gradient(135deg, #d9b382, #b2824b, #8b5a2b);
            border-radius: 8px 8px 3px 3px;
            box-shadow: inset -1px -2px 3px rgba(0, 0, 0, 0.4), 0 3px 5px rgba(0, 0, 0, 0.3);
            transform-origin: bottom center;
            animation: clStumpPulse 1.3s infinite alternate ease-in-out;
        }

        .stump:nth-child(1) { animation-delay: 0s; }
        .stump:nth-child(2) { animation-delay: 0.15s; }
        .stump:nth-child(3) { animation-delay: 0.3s; }

        .bails {
            position: absolute;
            top: -10px;
            left: 2px;
            display: flex;
            gap: 12px;
        }

        .bail {
            width: 32px;
            height: 8px;
            background: linear-gradient(145deg, #e6c394, #b2824b);
            border-radius: 12px;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.4);
            transform: rotate(2deg);
            animation: clBailWobble 1.5s infinite alternate ease-in-out;
        }

        .bail:last-child {
            transform: rotate(-2deg);
            animation-delay: 0.3s;
        }

        /* ----- Cricket Ball & Rotating Seam ----- */
        .cricket-ball {
            width: 52px;
            height: 52px;
            background: radial-gradient(circle at 35% 30%, #bb2e2e, #8b1a1a, #4d0f0f);
            border-radius: 50%;
            box-shadow: inset -6px -6px 10px rgba(0, 0, 0, 0.7),
                        0 10px 15px -5px black,
                        0 0 0 2px #d4af37;
            position: relative;
            animation: clBallBounce 1.1s infinite alternate ease-in-out;
            transform-origin: center;
            margin-bottom: 5px;
        }

        .cricket-ball::before {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 70%;
            height: 70%;
            transform: translate(-50%, -50%) rotate(45deg);
            border-radius: 50%;
            border: 3px dashed #f0e6d2;
            opacity: 0.9;
            animation: clSeamSpin 2.5s infinite linear;
            box-shadow: 0 0 6px rgba(255, 240, 200, 0.6);
        }

        .cricket-ball::after {
            content: '';
            position: absolute;
            top: 50%;
            left: 50%;
            width: 30%;
            height: 30%;
            transform: translate(-50%, -50%) rotate(-45deg);
            border-radius: 50%;
            border: 2px dotted #e6d5b8;
            opacity: 0.8;
            animation: clSeamSpinReverse 2s infinite linear;
        }

        /* ----- Label & Dots ----- */
        .loader-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 5px;
            color: #f5ecd9;
            font-weight: 700;
            letter-spacing: 1.5px;
            text-transform: uppercase;
            font-size: 0.95rem;
            text-shadow: 0 4px 8px black;
            background: rgba(0, 0, 0, 0.45);
            padding: 0.55rem 1.6rem;
            border-radius: 40px;
            border: 1px solid #c9a86b;
        }

        .loader-label span {
            display: inline-block;
            animation: clDotJump 1.4s infinite ease-in-out;
        }

        .loader-label span:nth-child(1) { animation-delay: 0s; }
        .loader-label span:nth-child(2) { animation-delay: 0.2s; }
        .loader-label span:nth-child(3) { animation-delay: 0.4s; }

        /* ----- Keyframes ----- */
        @keyframes clBallBounce {
            0% { transform: translateY(0) scale(1, 1); }
            100% { transform: translateY(-18px) scale(1.02, 0.98); }
        }

        @keyframes clSeamSpin {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(360deg); }
        }

        @keyframes clSeamSpinReverse {
            0% { transform: translate(-50%, -50%) rotate(0deg); }
            100% { transform: translate(-50%, -50%) rotate(-360deg); }
        }

        @keyframes clStumpPulse {
            0% { transform: scaleY(1); opacity: 0.95; }
            100% { transform: scaleY(0.94); opacity: 1; filter: brightness(1.15); }
        }

        @keyframes clBailWobble {
            0% { transform: rotate(2deg) translateY(0); }
            100% { transform: rotate(5deg) translateY(-1px); }
        }

        @keyframes clDotJump {
            0%, 80%, 100% { transform: translateY(0); opacity: 0.6; }
            40% { transform: translateY(-5px); opacity: 1; }
        }

        /* ----- Mobile Responsiveness (< 500px) ----- */
        @media (max-width: 500px) {
            .loader-card {
                padding: 1.75rem 1.8rem 2rem;
                border-radius: 2.5rem 2.5rem 1.6rem 1.6rem;
                gap: 1.3rem;
            }
            .cricket-scene::before {
                font-size: 1.7rem;
                left: -20px;
                top: -20px;
            }
            .cricket-ball {
                width: 42px;
                height: 42px;
            }
            .stumps {
                gap: 5px;
            }
            .stump {
                width: 10px;
                height: 58px;
            }
            .bails {
                top: -8px;
                gap: 10px;
            }
            .bail {
                width: 26px;
                height: 6px;
            }
            .loader-label {
                font-size: 0.78rem;
                padding: 0.45rem 1.25rem;
                letter-spacing: 1.2px;
            }
        }
    </style>
</div>
