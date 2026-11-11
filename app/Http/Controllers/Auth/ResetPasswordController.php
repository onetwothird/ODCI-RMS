<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Port of login/reset_password.php.
 *
 * The token travels in the URL, is looked up as a SHA-256 hash, and must be
 * younger than one hour. On success the form is hidden and a success message
 * is shown - exactly like the legacy $validToken = false behaviour.
 */
class ResetPasswordController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * GET /reset-password/{token?}
     */
    public function show(string $token = ''): View
    {
        [$user, $error] = $this->resolveToken($token);

        return view('auth.reset-password', [
            'token' => $token,
            'user' => $user,
            'validToken' => $user !== null,
            'error' => $error,
        ]);
    }

    /**
     * POST /reset-password/{token?}
     */
    public function update(Request $request, string $token = ''): View
    {
        [$user, $error] = $this->resolveToken($token);

        // Legacy only processed the POST when the token was valid; otherwise
        // the page rendered with the GET-time error and no form.
        if ($user === null) {
            return view('auth.reset-password', [
                'token' => $token,
                'user' => null,
                'validToken' => false,
                'error' => $error,
            ]);
        }

        $password = (string) $request->input('password', '');
        $confirmPassword = (string) $request->input('confirm_password', '');

        if ($password === '') {
            $error = 'Password is required.';
        } elseif (! preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{8,}$/', $password)) {
            // Legacy validatePassword() from includes/config.php.
            $error = 'Password must be at least 8 characters with uppercase, lowercase, and number.';
        } elseif ($password !== $confirmPassword) {
            $error = 'Passwords do not match.';
        }

        if ($error === null || $error === '') {
            try {
                // 'hashed' cast replaces password_hash(); tokens are cleared.
                $user->forceFill([
                    'password' => $password,
                    'password_reset_token' => null,
                    'password_reset_expires' => null,
                ])->save();

                // Legacy: logActivity(..., 'password_reset', 'user', ...)
                $this->activityLogger->logRequest(
                    $user->id,
                    'password_reset',
                    'user',
                    $user->id,
                    'Password reset successfully',
                );

                return view('auth.reset-password', [
                    'token' => $token,
                    'user' => null,
                    'validToken' => false,
                    'error' => null,
                    'success' => 'Password reset successfully! You can now log in with your new password.',
                ]);
            } catch (Throwable $e) {
                report($e);
                $error = 'An error occurred while resetting your password. Please try again.';
            }
        }

        return view('auth.reset-password', [
            'token' => $token,
            'user' => $user,
            'validToken' => true,
            'error' => $error,
        ]);
    }

    /**
     * @return array{0: User|null, 1: string|null}
     */
    private function resolveToken(string $token): array
    {
        // Legacy: no ?token= -> 'No password reset token provided.'
        if ($token === '') {
            return [null, 'No password reset token provided.'];
        }

        try {
            $tokenHash = hash('sha256', $token);

            $user = User::query()->where('password_reset_token', $tokenHash)->first();

            if (! $user) {
                return [null, 'Invalid password reset link.'];
            }

            if ($user->password_reset_expires && now()->greaterThan($user->password_reset_expires)) {
                return [null, 'Password reset link has expired. Please request a new one.'];
            }

            return [$user, null];
        } catch (Throwable $e) {
            report($e);

            return [null, 'An error occurred. Please try again.'];
        }
    }
}
