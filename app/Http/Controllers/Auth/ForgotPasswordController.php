<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Mail\PasswordResetMail;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use Illuminate\View\View;
use Throwable;

/**
 * Port of login/forgot_password.php + login/script/forgot_password_function.php.
 *
 * Only approved accounts receive a token (legacy behaviour), the token is
 * stored as a SHA-256 hash with a 1-hour expiry, and the page always answers
 * with the same generic success message so the form cannot be used to probe
 * which emails are registered.
 */
class ForgotPasswordController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * GET /forgot-password
     */
    public function show(): RedirectResponse|View
    {
        if (auth()->check()) {
            return redirect()->to(getDashboardUrl(auth()->user()->role));
        }

        return view('auth.forgot-password');
    }

    /**
     * POST /forgot-password
     */
    public function store(Request $request): RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->to(getDashboardUrl(auth()->user()->role));
        }

        $email = trim((string) $request->input('email', ''));

        // Legacy: $email === '' || !validateEmail($email) -> top-level error.
        if ($email === '' || ! filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return back()->with('error', 'Please enter a valid email address.')->withInput($request->only('email'));
        }

        try {
            // Legacy: SELECT id, email FROM users WHERE email = ? AND is_approved = 1
            $user = User::query()
                ->where('email', $email)
                ->where('is_approved', 1)
                ->first();

            if ($user) {
                $resetToken = bin2hex(random_bytes(32));
                $tokenHash = hash('sha256', $resetToken);

                $user->forceFill([
                    'password_reset_token' => $tokenHash,
                    'password_reset_expires' => now()->addHour(),
                ])->save();

                $resetUrl = route('password.reset', $resetToken);

                try {
                    Mail::to($user->email)->send(new PasswordResetMail($resetUrl));
                } catch (Throwable $mailError) {
                    // Legacy cleared the stored token when the mail failed, then
                    // rethrew so the caller showed the generic transport error.
                    User::query()
                        ->where('id', $user->id)
                        ->where('password_reset_token', $tokenHash)
                        ->update([
                            'password_reset_token' => null,
                            'password_reset_expires' => null,
                        ]);

                    throw $mailError;
                }

                // Legacy: logActivity(..., 'password_reset_request', 'user', ...)
                $this->activityLogger->logRequest(
                    $user->id,
                    'password_reset_request',
                    'user',
                    $user->id,
                    'Password reset email sent.',
                );
            }

            return back()->with(
                'success',
                'If an approved account uses that email address, password reset instructions have been sent.',
            );
        } catch (Throwable $e) {
            report($e);

            return back()->with(
                'error',
                'We could not send password reset instructions right now. Please try again later.',
            )->withInput($request->only('email'));
        }
    }
}
