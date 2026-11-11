<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Mirrors the legacy role gate in includes/auth_check.php:
 *
 *   requireRole() / preventCrossRoleAccess()
 *     -> user not in the allowed roles
 *     -> redirect to the user's OWN dashboard with ?error=access_denied
 *
 * The legacy code did NOT bounce a logged-in user back to the login page for
 * a permission failure, so that exact behaviour is preserved here.
 *
 * Usage:  ->middleware('role:admin,super_admin')
 *
 * NOTE: Laravel passes the parameter list of `role:a,b` to the middleware as a
 * SINGLE string ('a,b'); it does not split on commas. That splitting happens
 * here, which is why `role:admin,super_admin` works.
 */
class RoleMiddleware
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        if (! Auth::check()) {
            return redirect()->route('login');
        }

        $allowed = collect($roles)
            ->flatMap(fn (string $role) => explode(',', $role))
            ->map(fn (string $role) => trim($role))
            ->filter()
            ->all();

        $user = Auth::user();

        if (in_array($user->role, $allowed, true)) {
            return $next($request);
        }

        return redirect()
            ->route($this->dashboardRouteFor($user->role))
            ->with('error', 'access_denied');
    }

    private function dashboardRouteFor(?string $role): string
    {
        return match ($role) {
            'super_admin' => 'superadmin.dashboard',
            'admin' => 'admin.dashboard',
            default => 'user.dashboard',
        };
    }
}