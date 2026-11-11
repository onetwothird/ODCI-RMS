<?php

use App\Http\Controllers\Admin\FacultyStaffController;
use App\Http\Controllers\User\ColleaguesController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| People listing module (legacy: roles/admin/view-faculty-staff.php,
| roles/user/colleagues.php)
|--------------------------------------------------------------------------
|
| Loaded from routes/web.php inside the shared 'auth' + 'active.account'
| middleware group.
|
| GUARDS
|   - admin.view-faculty-staff mirrors the legacy requireAdmin() gate
|     (['admin', 'super_admin']); the legacy page itself only called
|     isLoggedIn(), but every admin page in this migration uses the role gate,
|     and super_admin is explicitly allowed -- exactly like requireAdmin().
|   - user.colleagues mirrors roles/user/colleagues.php's isLoggedIn() +
|     getCurrentUser() check, which is effectively role:user here.
|   - The JSON/POST endpoints keep the legacy access rules verbatim: the legacy
|     scripts (script/faculty-staff.php, script/messaging-system.php) only
|     checked isLoggedIn() + CSRF for AJAX actions, so these routes sit inside
|     the auth group without an extra role gate.
|
*/

Route::middleware(['auth', 'active.account'])->group(function () {
    // Pages
    Route::get('/admin/view-faculty-staff', [FacultyStaffController::class, 'index'])
        ->middleware('role:admin,super_admin')
        ->name('admin.view-faculty-staff');

    Route::get('/user/colleagues', [ColleaguesController::class, 'index'])
        ->middleware('role:user')
        ->name('user.colleagues');

    // AJAX endpoints used by public/js/admin/view-staff.js
    Route::post('/admin/view-faculty-staff/stats', [FacultyStaffController::class, 'stats'])
        ->name('admin.view-faculty-staff.stats');

    Route::post('/admin/view-faculty-staff/send-message', [FacultyStaffController::class, 'sendMessage'])
        ->name('admin.view-faculty-staff.send-message');

    Route::post('/admin/view-faculty-staff/send-bulk-message', [FacultyStaffController::class, 'sendBulkMessage'])
        ->name('admin.view-faculty-staff.send-bulk-message');
});