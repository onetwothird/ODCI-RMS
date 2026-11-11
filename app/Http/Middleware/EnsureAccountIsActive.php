<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mirrors requireAuth() in includes/auth_check.php:
 *
 *   - session must be valid
 *   - user row must still exist
 *   - user must still have is_approved = 1
 *   - otherwise: destroy session, redirect to login?error=account_not_found
 *
 * This preserves the legacy behaviour where de-activating or de-approving an
 * account immediately revokes access on the next request.
 */
class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $user = Auth::user();

        if ($user === null || ! $user->is_approved) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', 'account_not_found');
        }

        return $next($request);
    }
}
