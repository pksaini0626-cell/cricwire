<?php

namespace App\Http\Controllers;

use App\Models\AdBanner;
use Illuminate\Http\RedirectResponse;

class AdRedirectController extends Controller
{
    /**
     * Increment click tracking counter and redirect to affiliate target.
     */
    public function trackAndRedirect(AdBanner $banner): RedirectResponse
    {
        if ($banner->is_active) {
            $banner->increment('clicks_count');
        }

        $target = $banner->target_url ?: route('home');
        return redirect()->away($target);
    }
}

