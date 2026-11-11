<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\View\View;

/**
 * Faithful port of login/script/login_function.php + logout.php.
 *
 * Preserved behaviour:
 *  - login accepts EITHER username OR email
 *  - only accounts with is_approved = 1 may sign in
 *  - password_verify() against users.password (existing bcrypt hashes kept as-is)
 *  - 5 failed attempts -> account locked for 15 minutes
 *  - remaining-attempts messaging
 *  - failed/successful logins written to activity_logs
 *  - role-based redirect to that role's dashboard
 */
class LoginController extends Controller
{
    private const MAX_ATTEMPTS = 5;

    private const LOCK_MINUTES = 15;

    public function __construct(private readonly ActivityLogger $activity) {}

    public function showLoginForm(Request $request): View|RedirectResponse
    {
        // Legacy checkAlreadyLoggedIn(): send an authenticated user to their
        // OWN dashboard, not to a generic page.
        if (Auth::check()) {
            return $this->redirectToDashboard(Auth::user());
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
        ]);

        $identifier = $credentials['username'];
        $password = $credentials['password'];

        try {
            $user = User::query()
                ->where(fn ($q) => $q->where('username', $identifier)->orWhere('email', $identifier))
                ->where('is_approved', true)
                ->first();

            if ($user && Auth::validate(['username' => $user->username, 'password' => $password])) {
                return $this->handleSuccessfulLogin($request, $user);
            }

            if ($user) {
                return $this->handleFailedLogin($request, $user, $identifier);
            }

            $this->activity->logRequest(
                userId: null,
                action: 'failed_login_attempt',
                resourceType: 'system',
                resourceId: null,
                description: 'Failed login attempt for non-existent user',
                metadata: ['username_tried' => $identifier, 'ip_address' => $request->ip()],
            );

            return back()
                ->withInput($request->only('username', 'remember'))
                ->withErrors(['username' => 'Invalid username/email or password.']);
        } catch (\Throwable $e) {
            Log::error('Login error: '.$e->getMessage());

            return back()
                ->withInput($request->only('username', 'remember'))
                ->withErrors(['username' => 'An error occurred during login. Please try again.']);
        }
    }

    private function handleSuccessfulLogin(Request $request, User $user): RedirectResponse
    {
        // Legacy: locked accounts are rejected even with the correct password.
        if ($user->isLocked()) {
            $remaining = Carbon::parse($user->account_locked_until)->diffInMinutes(Carbon::now());

            return back()
                ->withInput($request->only('username', 'remember'))
                ->withErrors([
                    'username' => "Account is temporarily locked for {$remaining} more minutes. Please try again later.",
                ]);
        }

        $user->forceFill([
            'failed_login_attempts' => 0,
            'account_locked_until' => null,
            'last_login' => Carbon::now(),
        ])->save();

        Auth::login($user, $request->boolean('remember'));

        $request->session()->regenerate();

        // Legacy login_function.php: $_SESSION['login_time'] = time();
        // The admin dashboard reads it as the honest "last login" timestamp.
        $request->session()->put('login_time', time());

        $this->activity->logRequest(
            userId: $user->id,
            action: 'login',
            resourceType: 'user',
            resourceId: $user->id,
            description: 'User logged in successfully',
            metadata: [
                'ip_address' => $request->ip() ?? 'unknown',
                'user_agent' => $request->userAgent() ?? 'unknown',
                'remember_me' => $request->boolean('remember') ? 'yes' : 'no',
            ],
        );

        return $this->redirectToDashboard($user);
    }

    private function handleFailedLogin(Request $request, User $user, string $identifier): RedirectResponse
    {
        $attempts = (int) $user->failed_login_attempts + 1;
        $lockedUntil = null;

        if ($attempts >= self::MAX_ATTEMPTS) {
            $lockedUntil = Carbon::now()->addMinutes(self::LOCK_MINUTES);
            $message = 'Too many failed login attempts. Your account has been locked for 15 minutes.';
        } else {
            $remaining = self::MAX_ATTEMPTS - $attempts;
            $message = "Invalid username/email or password. {$remaining} attempts remaining.";
        }

        $user->forceFill([
            'failed_login_attempts' => $attempts,
            'account_locked_until' => $lockedUntil,
        ])->save();

        $this->activity->logRequest(
            userId: $user->id,
            action: 'failed_login',
            resourceType: 'user',
            resourceId: $user->id,
            description: 'Failed login attempt',
            metadata: [
                'attempt_number' => $attempts,
                'ip_address' => $request->ip() ?? 'unknown',
                'username_tried' => $identifier,
            ],
        );

        return back()
            ->withInput($request->only('username', 'remember'))
            ->withErrors(['username' => $message]);
    }

    public function logout(Request $request): RedirectResponse
    {
        $userId = Auth::id();

        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($userId !== null) {
            $this->activity->logRequest(
                userId: $userId,
                action: 'logout',
                resourceType: 'user',
                resourceId: $userId,
                description: 'User logged out',
            );
        }

        return redirect()->route('login')->with('success', 'You have been successfully logged out.');
    }

    private function redirectToDashboard(User $user): RedirectResponse
    {
        return match ($user->role) {
            'super_admin' => redirect()->route('superadmin.dashboard'),
            'admin' => redirect()->route('admin.dashboard'),
            'user' => redirect()->route('user.dashboard'),
            default => redirect()->route('login')->with('error', 'invalid_role'),
        };
    }
}