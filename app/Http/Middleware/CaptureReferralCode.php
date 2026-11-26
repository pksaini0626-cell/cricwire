<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CaptureReferralCode
{
    /**
     * Capture ?ref=CW-XXXXXX query parameters into the session for Socialite OAuth.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $ref = $request->query('ref');

        if (is_string($ref) && $request->hasSession()) {
            $clean = strtoupper(trim($ref));
            if ($clean !== '' && strlen($clean) <= 16 && preg_match('/^[A-Z0-9-]+$/', $clean)) {
                $request->session()->put('referral_code', $clean);
            }
        }

        return $next($request);
    }
}
