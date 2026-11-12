<?php

namespace App\Livewire\Pages;

use App\Models\User;
use App\Support\LiveEngineConfig;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\Session;
use Livewire\Component;

class MorePage extends Component
{
    public bool $pushNotifications = true;

    public function mount(): void
    {
        // Default notification state (can be synchronized with LocalStorage or user model)
    }

    /**
     * Delete user account, invalidate session, clear cookies, and redirect to home.
     */
    public function deleteAccount(): void
    {
        $userId = Auth::id();

        if ($userId) {
            // Delete user record from database
            User::where('id', $userId)->delete();

            // Log out and flush session
            Auth::logout();
            Session::invalidate();
            Session::regenerateToken();

            // Clear authentication cookies
            Cookie::queue(Cookie::forget(Auth::getRecallerName()));
            Cookie::queue(Cookie::forget('remember_token'));

            session()->flash('success', 'Your account has been permanently deleted.');
        }

        $this->redirect('/', navigate: true);
    }

    public function render()
    {
        $socialLinks = LiveEngineConfig::getActiveSocialLinks();
        $appVersion = config('app.version', '1.0.4 - beta');
        $currentUser = Auth::user();

        return view('livewire.pages.more-page', [
            'socialLinks' => $socialLinks,
            'appVersion' => $appVersion,
            'user' => $currentUser,
        ])->layout('components.layouts.app');
    }
}
