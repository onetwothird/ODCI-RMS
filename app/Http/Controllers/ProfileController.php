<?php

namespace App\Http\Controllers;

use App\Services\ActivityLogger;
use App\Services\LegacyFileResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Account settings / profile module.
 *
 * Legacy equivalents:
 *   roles/user/settings.php           -> GET /user/settings
 *   roles/admin/profile.php           -> GET /admin/profile
 *   roles/superadmin/settings.php     -> GET /superadmin/settings (tabbed)
 *
 * New profile images are stored under storage/app/private/uploads/profiles/
 * and served through the profile.image route; legacy images are left
 * untouched inside the reference repo.
 */
class ProfileController extends Controller
{
    public function __construct(private readonly ActivityLogger $activity) {}

    /* ----------------------------------------------------------------------
     | Page renderers
     |---------------------------------------------------------------------- */

    public function userSettings(Request $request): \Illuminate\View\View
    {
        return view('user.settings', $this->settingsViewData($request->user(), 'user'));
    }

    public function adminProfile(Request $request): \Illuminate\View\View
    {
        return view('admin.profile', $this->settingsViewData($request->user(), 'admin'));
    }

    public function superadminSettings(Request $request): \Illuminate\View\View
    {
        $data = $this->settingsViewData($request->user(), 'super_admin');

        // Live statistics for the Statistics tab (real tables only).
        $users = DB::table('users');
        $data['stats'] = [
            'total_users' => (int) (clone $users)->count(),
            'approved_users' => (int) (clone $users)->where('is_approved', 1)->count(),
            'pending_users' => (int) (clone $users)->where('is_approved', 0)->count(),
            'super_admins' => (int) (clone $users)->where('role', 'super_admin')->count(),
            'admins' => (int) (clone $users)->where('role', 'admin')->count(),
            'total_departments' => (int) DB::table('departments')->count(),
            'active_departments' => (int) DB::table('departments')->where('is_active', 1)->count(),
            'total_files' => (int) DB::table('files')->where('is_deleted', 0)->count(),
            'file_downloads' => (int) DB::table('files')->where('is_deleted', 0)->sum('download_count'),
            'total_announcements' => (int) DB::table('announcements')->where('is_deleted', 0)->count(),
            'published_announcements' => (int) DB::table('announcements')->where('is_deleted', 0)->where('is_published', 1)->count(),
            'total_requests' => (int) DB::table('document_requests')->where('is_deleted', 0)->count(),
            'pending_requests' => (int) DB::table('document_requests')->where('status', 'pending')->where('is_deleted', 0)->count(),
            'completed_requests' => (int) DB::table('document_requests')->where('status', 'completed')->where('is_deleted', 0)->count(),
        ];
        $data['systemSettings'] = $this->systemSettingsMap();

        return view('superadmin.settings', $data);
    }

    /* ----------------------------------------------------------------------
     | Form handlers
     |---------------------------------------------------------------------- */

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();
        $action = (string) $request->string('action');
        $route = $this->settingsRouteFor($user->role);

        switch ($action) {
            case 'update_profile':
                return $this->updateProfile($request);
            case 'change_password':
                return $this->changePassword($request);
            case 'upload_image':
                return $this->uploadImage($request);
            case 'remove_image':
                return $this->removeImage($request);
            case 'clear_login_attempts':
                DB::table('users')->where('id', $user->id)
                    ->update(['failed_login_attempts' => 0, 'account_locked_until' => null]);
                $this->activity->logRequest($user->id, 'login_attempts_cleared', 'user', $user->id, 'Cleared recorded login attempts');

                return redirect()->route($route)->with('success_message', 'Login attempts cleared successfully!');
            default:
                return redirect()->route($route)->with('error_message', 'Unknown action.');
        }
    }

    public function updateSystemSettings(Request $request): RedirectResponse
    {
        $user = $request->user();
        $input = $request->input('system_settings', []);

        $values = [
            'maintenance_mode' => (int) ($input['maintenance_mode'] ?? 0),
            'max_file_size' => max(1, (int) ($input['max_file_size'] ?? 100)) * 1048576, // MB -> bytes
            'session_timeout' => max(5, (int) ($input['session_timeout'] ?? 60)) * 60,   // min -> seconds
            'email_notifications' => (int) ($input['email_notifications'] ?? 0),
            'user_registration' => (int) ($input['user_registration'] ?? 0),
            'auto_approve_users' => (int) ($input['auto_approve_users'] ?? 0),
            'backup_retention_days' => max(1, (int) ($input['backup_retention_days'] ?? 30)),
        ];

        foreach ($values as $key => $value) {
            DB::table('system_settings')->updateOrInsert(
                ['setting_key' => $key],
                [
                    'setting_value' => $value,
                    'setting_type' => in_array($key, ['maintenance_mode', 'email_notifications', 'user_registration', 'auto_approve_users'], true) ? 'boolean' : 'integer',
                    'updated_at' => now(),
                    'updated_by' => $user->id,
                ]
            );
        }

        $this->activity->logRequest($user->id, 'system_settings_update', 'system', null, 'Updated system configuration');

        return redirect()->route($this->settingsRouteFor('super_admin'))->with('success_message', 'System settings updated successfully!');
    }

    /* ----------------------------------------------------------------------
     | Individual profile actions
     |---------------------------------------------------------------------- */

    private function updateProfile(Request $request): RedirectResponse
    {
        $user = $request->user();
        $route = $this->settingsRouteFor($user->role);

        $name = trim((string) $request->string('name'));
        $mi = trim((string) $request->string('mi'));
        $surname = trim((string) $request->string('surname'));
        $email = trim((string) $request->string('email'));
        $phone = trim((string) $request->string('phone'));
        $address = trim((string) $request->string('address'));
        $position = trim((string) $request->string('position'));
        $employeeId = trim((string) $request->string('employee_id'));
        $departmentId = $request->input('department_id') ? (int) $request->input('department_id') : null;
        $dateOfBirth = $request->string('date_of_birth')->value() ?: null;
        $hireDate = $request->string('hire_date')->value() ?: null;

        if ($name === '' || $surname === '' || $email === '') {
            return $this->backWith('Name, surname, and email are required fields.', $route);
        }

        $emailTaken = DB::table('users')->where('email', $email)->where('id', '!=', $user->id)->exists();
        if ($emailTaken) {
            return $this->backWith('Email address is already in use by another user.', $route);
        }

        if ($employeeId !== '' && DB::table('users')->where('employee_id', $employeeId)->where('id', '!=', $user->id)->exists()) {
            return $this->backWith('Employee ID is already in use by another user.', $route);
        }

        DB::table('users')->where('id', $user->id)->update([
            'name' => $name,
            'mi' => $mi ?: null,
            'surname' => $surname,
            'email' => $email,
            'phone' => $phone ?: null,
            'address' => $address ?: null,
            'position' => $position ?: null,
            'employee_id' => $employeeId ?: null,
            'department_id' => $departmentId,
            'date_of_birth' => $dateOfBirth,
            'hire_date' => $hireDate,
            'updated_at' => now(),
        ]);

        $this->activity->logRequest($user->id, 'profile_update', 'user', $user->id, 'Updated profile information');

        return redirect()->route($route)->with('success_message', 'Profile updated successfully!');
    }

    private function changePassword(Request $request): RedirectResponse
    {
        $user = $request->user();
        $route = $this->settingsRouteFor($user->role);

        $current = (string) $request->string('current_password');
        $new = (string) $request->string('new_password');
        $confirm = (string) $request->string('confirm_password');

        if ($current === '' || $new === '' || $confirm === '') {
            return $this->backWith('All password fields are required.', $route);
        }
        if ($new !== $confirm) {
            return $this->backWith('New passwords do not match.', $route);
        }
        if (strlen($new) < 8) {
            return $this->backWith('New password must be at least 8 characters long.', $route);
        }
        if (! Hash::check($current, $user->password)) {
            return $this->backWith('Current password is incorrect.', $route);
        }

        DB::table('users')->where('id', $user->id)->update([
            'password' => Hash::make($new),
            'updated_at' => now(),
        ]);

        $this->activity->logRequest($user->id, 'password_change', 'user', $user->id, 'Password changed successfully');

        return redirect()->route($route)->with('success_message', 'Password changed successfully!');
    }

    private function uploadImage(Request $request): RedirectResponse
    {
        $user = $request->user();
        $route = $this->settingsRouteFor($user->role);

        if (! $request->hasFile('profile_image')) {
            return $this->backWith('Please select an image to upload.', $route);
        }

        $file = $request->file('profile_image');
        $allowed = ['image/jpeg', 'image/jpg', 'image/png', 'image/gif', 'image/webp'];
        $mime = mime_content_type($file->getPathname());
        $extension = strtolower($file->getClientOriginalExtension());

        if (! in_array($mime, $allowed, true) && ! in_array($file->getMimeType(), $allowed, true)) {
            return $this->backWith('Only JPG, PNG, GIF, and WEBP images are allowed.', $route);
        }
        if (! in_array($extension, ['jpg', 'jpeg', 'png', 'gif', 'webp'], true)) {
            return $this->backWith('Invalid image extension.', $route);
        }
        if ($file->getSize() > 5 * 1024 * 1024) {
            return $this->backWith('Image size must be less than 5MB.', $route);
        }

        $relativeDir = 'uploads/profiles';
        $dir = storage_path('app/private/'.str_replace('/', DIRECTORY_SEPARATOR, $relativeDir));
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $name = 'profile_'.$user->id.'_'.time().'.'.$extension;
        $file->move($dir, $name);

        $this->deleteStoredImage($user->profile_image);

        DB::table('users')->where('id', $user->id)->update([
            'profile_image' => $relativeDir.'/'.$name,
            'updated_at' => now(),
        ]);

        $this->activity->logRequest($user->id, 'profile_image_update', 'user', $user->id, 'Profile image updated');

        return redirect()->route($route)->with('success_message', 'Profile image updated successfully!');
    }

    private function removeImage(Request $request): RedirectResponse
    {
        $user = $request->user();
        $route = $this->settingsRouteFor($user->role);

        $this->deleteStoredImage($user->profile_image);

        DB::table('users')->where('id', $user->id)->update([
            'profile_image' => null,
            'updated_at' => now(),
        ]);

        $this->activity->logRequest($user->id, 'profile_image_update', 'user', $user->id, 'Profile image removed');

        return redirect()->route($route)->with('success_message', 'Profile image removed successfully!');
    }

    /* ----------------------------------------------------------------------
     | Profile image serving (new uploads live in storage/app/private)
     |---------------------------------------------------------------------- */

    public function image(string $path): BinaryFileResponse
    {
        $clean = ltrim((string) preg_replace('#^(\.\./)+#', '', $path), '/\\');
        $candidates = [
            storage_path('app/private/'.str_replace('/', DIRECTORY_SEPARATOR, $clean)),
            app(LegacyFileResolver::class)->resolve($clean, 'profiles'),
        ];

        foreach ($candidates as $candidate) {
            if ($candidate && is_file($candidate)) {
                return response()->file($candidate, ['Cache-Control' => 'private, max-age=86400']);
            }
        }

        abort(404);
    }

    /* ----------------------------------------------------------------------
     | Helpers
     |---------------------------------------------------------------------- */

    /**
     * Shared view data for the settings / profile pages.
     */
    private function settingsViewData($user, string $role): array
    {
        $profileImage = $user->profile_image_url;

        return [
            'currentUser' => $user,
            'profileImage' => $profileImage,
            'departments' => DB::table('departments')->where('is_active', 1)
                ->orderBy('department_name')->get(['id', 'department_name', 'department_code']),
            'hasProfileImage' => $profileImage !== null,
            'initials' => $user->initials,
            'securityScore' => $this->securityScore($user),
            'forSuperAdmin' => $role === 'super_admin',
        ];
    }

    private function securityScore($user): int
    {
        $score = 0;
        if ($user->profile_image) {
            $score += 10;
        }
        if ($user->phone) {
            $score += 10;
        }
        if ($user->date_of_birth) {
            $score += 10;
        }
        if ($user->hire_date) {
            $score += 10;
        }
        if ($user->employee_id) {
            $score += 10;
        }
        if ($user->position) {
            $score += 10;
        }
        if ($user->address) {
            $score += 10;
        }

        // Password quality (length >= 12 adds the final 30 points).
        if (strlen((string) $user->password) >= 12) {
            $score += 30;
        }

        return min(100, $score);
    }

    /**
     * Current system settings converted to display units (MB / minutes).
     */
    private function systemSettingsMap(): array
    {
        $rows = DB::table('system_settings')->pluck('setting_value', 'setting_key')->map(
            fn ($v) => json_decode((string) $v, true) ?? $v
        );

        return [
            'maintenance_mode' => (bool) ($rows['maintenance_mode'] ?? false),
            'max_file_size' => (int) ($rows['max_file_size'] ?? 104857600) / 1048576,
            'session_timeout' => (int) ($rows['session_timeout'] ?? 3600) / 60,
            'email_notifications' => (bool) ($rows['email_notifications'] ?? true),
            'user_registration' => (bool) ($rows['user_registration'] ?? true),
            'auto_approve_users' => (bool) ($rows['auto_approve_users'] ?? false),
            'backup_retention_days' => (int) ($rows['backup_retention_days'] ?? 30),
        ];
    }

    /**
     * Deletes an old profile image ONLY when it lives inside the Laravel
     * storage directory. Files under the legacy reference repo are never
     * touched.
     */
    private function deleteStoredImage(?string $stored): void
    {
        if (! $stored) {
            return;
        }

        $clean = ltrim((string) preg_replace('#^(\.\./)+#', '', $stored), '/\\');
        $path = storage_path('app/private/'.str_replace('/', DIRECTORY_SEPARATOR, $clean));
        $base = realpath(storage_path('app/private'));

        if ($path && is_file($path)) {
            $real = realpath($path);
            if ($real && str_starts_with($real, $base.DIRECTORY_SEPARATOR)) {
                @unlink($real);
            }
        }
    }

    private function backWith(string $message, string $route): RedirectResponse
    {
        return redirect()->route($route)->with('error_message', $message);
    }

    /**
     * The settings route for each role.
     */
    private function settingsRouteFor(string $role): string
    {
        return match ($role) {
            'admin' => 'admin.profile',
            'super_admin' => 'superadmin.settings',
            default => 'user.settings',
        };
    }
}