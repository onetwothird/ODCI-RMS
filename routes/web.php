<?php

use App\Http\Controllers\Admin\DashboardController as AdminDashboardController;
use App\Http\Controllers\Auth\ChangePasswordController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\SuperAdmin\DashboardController as SuperAdminDashboardController;
use App\Http\Controllers\SuperAdmin\DepartmentController;
use App\Http\Controllers\SuperAdmin\UserController;
use App\Http\Controllers\User\DashboardController as UserDashboardController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
*/

// Public landing page. Signed-in visitors get an "Open dashboard" button.
Route::get('/', function () {
    $dashboard = match (auth()->user()?->role) {
        'super_admin' => 'superadmin.dashboard',
        'admin' => 'admin.dashboard',
        'user' => 'user.dashboard',
        default => null,
    };

    return view('welcome', ['dashboardUrl' => $dashboard ? route($dashboard) : null]);
})->name('home');

/*
|--------------------------------------------------------------------------
| Authentication
|--------------------------------------------------------------------------
|
| Legacy equivalents:
|   login.php                  -> GET  /login
|   login/script/login_function.php -> POST /login
|   logout.php                 -> POST /logout
|
| The `guest` middleware is deliberately NOT used here. The legacy page called
| checkAlreadyLoggedIn() itself and sent a signed-in visitor to THEIR OWN
| dashboard; relying on Laravel's default guest redirect would send them to "/"
| instead. LoginController::showLoginForm() reproduces the legacy behaviour.
|
*/

Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
Route::post('/login', [LoginController::class, 'login'])->name('login.attempt');

Route::post('/logout', [LoginController::class, 'logout'])
    ->middleware('auth')
    ->name('logout');

/*
| The legacy sidebar/navbar logout is a plain <a href="logout.php">
| (GET) link. Keep a named GET route for that exact anchor markup; tests
| that POST /logout still target the POST route above.
*/
Route::get('/logout', [LoginController::class, 'logout'])
    ->name('logout-link');

/*
|--------------------------------------------------------------------------
| Registration & password recovery
|--------------------------------------------------------------------------
|
| Legacy equivalents:
|   login/register.php            -> GET  /register
|   login/script/register_function.php -> POST /register
|   login/forgot_password.php     -> GET|POST /forgot-password
|   login/reset_password.php      -> GET|POST /reset-password/{token?}
|   changepass.php                -> GET|POST /changepass
|
*/

Route::get('/register', [RegisterController::class, 'show'])->name('register');
Route::post('/register', [RegisterController::class, 'store']);

Route::get('/forgot-password', [ForgotPasswordController::class, 'show'])->name('password.request');
Route::post('/forgot-password', [ForgotPasswordController::class, 'store'])->name('password.send');

Route::get('/reset-password/{token?}', [ResetPasswordController::class, 'show'])
    ->where('token', '.*')
    ->name('password.reset');
Route::post('/reset-password/{token?}', [ResetPasswordController::class, 'update'])
    ->where('token', '.*')
    ->name('password.update');

Route::get('/changepass', [ChangePasswordController::class, 'show'])
    ->middleware('auth')
    ->name('changepass');
Route::post('/changepass', [ChangePasswordController::class, 'store'])
    ->middleware('auth');

/*
|--------------------------------------------------------------------------
| Google Sign-In + email verification
|--------------------------------------------------------------------------
|
| Legacy equivalents:
|   login/script/google_oauth.php     -> GET  /login/google
|   login/script/google_callback.php  -> GET  /login/google/callback
|   login/oauth_cancelled.php         -> GET  /login/google/cancelled
|   login.php?verify=...              -> GET  /verify-email/{token}
|
*/
Route::get('/login/google', [\App\Http\Controllers\Auth\GoogleOAuthController::class, 'redirectToGoogle'])
    ->name('oauth.google.redirect');
Route::get('/login/google/callback', [\App\Http\Controllers\Auth\GoogleOAuthController::class, 'callback'])
    ->name('oauth.google.callback');
Route::get('/login/google/complete', [\App\Http\Controllers\Auth\GoogleOAuthController::class, 'showComplete'])
    ->name('oauth.google.complete');
Route::post('/login/google/complete', [\App\Http\Controllers\Auth\GoogleOAuthController::class, 'storeComplete'])
    ->name('oauth.google.complete.store');
Route::get('/login/google/cancelled', [\App\Http\Controllers\Auth\GoogleOAuthController::class, 'cancelled'])
    ->name('oauth.google.cancelled');

Route::get('/verify-email/{token}', [RegisterController::class, 'verifyEmail'])
    ->name('verify.email');

/*
|--------------------------------------------------------------------------
| Legacy file serving (profile images, documents, etc.)
|--------------------------------------------------------------------------
|
| Files stored in the legacy project's uploads/ directory are served
| read-only through this route. The DB stores relative paths like
| "uploads/profiles/profile_27_123.png".
|
*/
Route::get('/uploads/{path}', \App\Http\Controllers\LegacyUploadController::class)
    ->where('path', '.*')
    ->name('legacy.uploads');

/*
|--------------------------------------------------------------------------
| Role dashboards
|--------------------------------------------------------------------------
|
| Legacy equivalents:
|   roles/user/dashboard.php      -> GET /user/dashboard
|   roles/admin/dashboard.php     -> GET /admin/dashboard
|   roles/superadmin/dashboard.php-> GET /superadmin/dashboard
|
| `active.account` mirrors requireAuth() in includes/auth_check.php:
| it re-checks on every request that the account still exists and is
| still approved, exactly like the legacy code did.
|
*/

Route::middleware(['auth', 'active.account'])->group(function () {
    // Header notification bell (legacy includes/notifications_endpoint.php).
    Route::get('/notifications', [\App\Http\Controllers\NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications', [\App\Http\Controllers\NotificationController::class, 'update'])->name('notifications.update');

    Route::get('/user/dashboard', [UserDashboardController::class, 'index'])
        ->middleware('role:user')
        ->name('user.dashboard');

    Route::get('/admin/dashboard', [AdminDashboardController::class, 'index'])
        ->middleware('role:admin,super_admin')
        ->name('admin.dashboard');

    Route::get('/superadmin/dashboard', [SuperAdminDashboardController::class, 'index'])
        ->middleware('role:super_admin')
        ->name('superadmin.dashboard');

    /*
     |--------------------------------------------------------------------------
     | Super Admin — Users & Departments
     |--------------------------------------------------------------------------
     |
     | Legacy equivalents:
     |   roles/superadmin/users.php                  -> GET  /superadmin/users
     |   roles/superadmin/assets/script/users-script.php?action=*  -> POST /superadmin/users
     |   roles/superadmin/departments.php            -> GET  /superadmin/departments
     |   roles/superadmin/assets/script/departments-script.php?action=* -> POST /superadmin/departments
     |
     | All routes protected by role:super_admin (matches requireSuperAdmin()).
     |
     */
    Route::prefix('superadmin')->middleware('role:super_admin')->group(function () {
        Route::get('users', [UserController::class, 'index'])->name('superadmin.users.index');
        Route::post('users', [UserController::class, 'store'])->name('superadmin.users.store');
        Route::get('users/{user}/edit', [UserController::class, 'edit'])->name('superadmin.users.edit');
        Route::patch('users/{user}', [UserController::class, 'update'])->name('superadmin.users.update');
        Route::delete('users/{user}', [UserController::class, 'destroy'])->name('superadmin.users.destroy');
        Route::post('users/{user}/approve', [UserController::class, 'approve'])->name('superadmin.users.approve');
        Route::post('users/{user}/reset-password', [UserController::class, 'resetPassword'])->name('superadmin.users.reset-password');

        Route::get('departments', [DepartmentController::class, 'index'])->name('superadmin.departments.index');
        Route::post('departments', [DepartmentController::class, 'store'])->name('superadmin.departments.store');
        Route::get('departments/{department}/edit', [DepartmentController::class, 'edit'])->name('superadmin.departments.edit');
        Route::patch('departments/{department}', [DepartmentController::class, 'update'])->name('superadmin.departments.update');
        Route::delete('departments/{department}', [DepartmentController::class, 'destroy'])->name('superadmin.departments.destroy');
        Route::post('departments/{department}/toggle-status', [DepartmentController::class, 'toggleStatus'])->name('superadmin.departments.toggle-status');
    });
});

/*
|--------------------------------------------------------------------------
| Feature module fragments
|--------------------------------------------------------------------------
|
| Each module owns its own file so parallel migrations never conflict.
| The `active.account` / role checks live inside each fragment.
|
*/

require __DIR__.'/social-feed.module.php';
require __DIR__.'/document-tracker.module.php';
require __DIR__.'/faculty.module.php';
require __DIR__.'/files.module.php';
require __DIR__.'/profile.module.php';
require __DIR__.'/reports.module.php';
require __DIR__.'/activity-logs.module.php';