<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\CricGemsService;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Laravel\Socialite\Facades\Socialite;

class SocialiteController extends Controller
{
    /**
     * Redirect to Google OAuth provider.
     */
    public function redirect(): RedirectResponse
    {
        $intended = request()->query('intended');
        if (is_string($intended) && str_starts_with($intended, '/') && !str_starts_with($intended, '//')) {
            session()->put('url.intended', url($intended));
        } elseif (!session()->has('url.intended')) {
            // Remember previous URL before starting OAuth flow
            session()->put('url.intended', url()->previous());
        }

        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle Google OAuth callback.
     */
    public function callback(CricGemsService $cricGemsService): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            $user = User::where('google_id', $googleUser->getId())
                ->orWhere('email', $googleUser->getEmail())
                ->first();

            if ($user) {
                $user->update([
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar() ?: $user->avatar,
                    'email_verified_at' => $user->email_verified_at ?? now(),
                    'last_login_at' => now(),
                ]);
            } else {
                $user = User::create([
                    'name' => $googleUser->getName() ?: $googleUser->getNickname() ?: 'Cricket Fan',
                    'email' => $googleUser->getEmail(),
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                    'email_verified_at' => now(),
                    'last_login_at' => now(),
                    'password' => bcrypt(Str::random(24)),
                ]);
            }

            $referralCode = session()->pull('referral_code');

            Auth::login($user, true);
            request()->session()->regenerate();

            $cricGemsService->ensurePlayerCode($user);
            $cricGemsService->grantJoiningBonusIfEligible($user);

            if (is_string($referralCode) && $referralCode !== '') {
                $cricGemsService->applyReferralCode($user, $referralCode);
            }

            $streakResult = $cricGemsService->processDailyStreak($user);
            session([
                'streak_checked_ist_date' => Carbon::now('Asia/Kolkata')->toDateString(),
                'streak_checked_user_id' => $user->id,
            ]);

            if ($streakResult !== null) {
                session()->flash('cricgems_streak_toast', $streakResult);
            }

            $intendedUrl = session()->pull('url.intended', route('home'));
            return redirect()->to($intendedUrl)->with('success', 'Logged in successfully!');
        } catch (Exception $e) {
            Log::error('Socialite Google Login Error: ' . $e->getMessage());
            return redirect()->route('home')->with('error', 'Google authentication failed. Please try again.');
        }
    }
}

