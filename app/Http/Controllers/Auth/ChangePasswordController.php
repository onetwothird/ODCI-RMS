<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

/**
 * Port of /changepass.php.
 *
 * Legacy verified the fields in this exact order and rendered its message
 * inline (no redirect on failure), then regenerated the session id after a
 * successful change. Messages and lengths (8-72 bytes) are kept verbatim.
 */
class ChangePasswordController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * GET /changepass
     */
    public function show(Request $request): View
    {
        // Legacy: $_SESSION['password_change_flash'] read once, then unset.
        $flash = $request->session()->pull('password_change_flash');

        return view('auth.changepass', [
            'message' => is_array($flash) ? (string) ($flash['message'] ?? '') : '',
            'messageType' => is_array($flash) ? (string) ($flash['type'] ?? '') : '',
        ]);
    }

    /**
     * POST /changepass
     */
    public function store(Request $request): RedirectResponse|View
    {
        $user = $request->user();

        $currentPassword = (string) $request->input('current_password', '');
        $newPassword = (string) $request->input('new_password', '');
        $confirmPassword = (string) $request->input('confirm_password', '');

        $message = '';
        $messageType = 'error';

        if ($currentPassword === '' || $newPassword === '' || $confirmPassword === '') {
            $message = 'Complete all password fields.';
        } elseif (strlen($newPassword) < 8 || strlen($newPassword) > 72) {
            $message = 'Use a new password between 8 and 72 bytes.';
        } elseif ($newPassword !== $confirmPassword) {
            $message = 'The password confirmation does not match.';
        } elseif (! password_verify($currentPassword, (string) $user->password)) {
            $message = 'Your current password is incorrect.';
        } elseif (password_verify($newPassword, (string) $user->password)) {
            $message = 'Choose a new password that differs from your current password.';
        } else {
            try {
                // 'hashed' cast replaces password_hash(); updated_at set explicitly.
                $user->forceFill([
                    'password' => $newPassword,
                    'updated_at' => now(),
                ])->save();

                // Legacy: logActivity(..., 'password_change', 'user', ...)
                $this->activityLogger->logRequest(
                    $user->id,
                    'password_change',
                    'user',
                    $user->id,
                    'User changed their account password.',
                );

                // Legacy flashed through the session, then regenerated the id
                // (session_regenerate_id(true)) and redirected (PRG).
                $request->session()->put('password_change_flash', [
                    'message' => 'Your password was changed successfully.',
                    'type' => 'success',
                ]);
                $request->session()->regenerate();
                $request->session()->regenerateToken();

                return redirect()->route('changepass');
            } catch (Throwable $e) {
                report($e);
                $message = 'The password could not be updated. Please try again.';
            }
        }

        return view('auth.changepass', [
            'message' => $message,
            'messageType' => $messageType,
        ]);
    }
}
