<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
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
        // Remember previous URL before starting OAuth flow
        if (!session()->has('url.intended')) {
            session()->put('url.intended', url()->previous());
        }

        return Socialite::driver('google')->redirect();
    }

    /**
     * Handle Google OAuth callback.
     */
    public function callback(): RedirectResponse
    {
        try {
            $googleUser = Socialite::driver('google')->user();

            $user = User::updateOrCreate(
                ['email' => $googleUser->getEmail()],
                [
                    'name' => $googleUser->getName() ?: $googleUser->getNickname() ?: 'Cricket Fan',
                    'google_id' => $googleUser->getId(),
                    'avatar' => $googleUser->getAvatar(),
                    'password' => bcrypt(Str::random(24)),
                ]
            );

            Auth::login($user, $remember = true);

            $intendedUrl = session()->pull('url.intended', route('home'));
            return redirect()->to($intendedUrl)->with('success', 'Logged in successfully!');
        } catch (Exception $e) {
            Log::error('Socialite Google Login Error: ' . $e->getMessage());
            return redirect()->route('home')->with('error', 'Google authentication failed. Please try again.');
        }
    }
}

