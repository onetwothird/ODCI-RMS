<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Profile / account settings module
|--------------------------------------------------------------------------
|
| Legacy equivalents:
|   roles/user/settings.php        -> GET  /user/settings
|   roles/admin/profile.php        -> GET  /admin/profile
|   roles/superadmin/settings.php  -> GET  /superadmin/settings
|
| Rendered profile images stored under storage/app/private/uploads/...
| are served read-only through GET /profile-image/{path}.
|
*/

Route::middleware(['auth', 'active.account'])->group(function () {
    Route::get('/user/settings', [ProfileController::class, 'userSettings'])
        ->middleware('role:user')
        ->name('user.settings');

    Route::get('/admin/profile', [ProfileController::class, 'adminProfile'])
        ->middleware('role:admin,super_admin')
        ->name('admin.profile');

    Route::get('/superadmin/settings', [ProfileController::class, 'superadminSettings'])
        ->middleware('role:super_admin')
        ->name('superadmin.settings');

    // Shared form handlers (role checked against the acting user).
    Route::post('/profile/update', [ProfileController::class, 'update'])
        ->middleware('role:user,admin,super_admin')
        ->name('profile.update');

    Route::post('/superadmin/settings', [ProfileController::class, 'updateSystemSettings'])
        ->middleware('role:super_admin')
        ->name('superadmin.settings.update');
});

// Profile image serving (private storage, read-only).
Route::get('/profile-image/{path}', [ProfileController::class, 'image'])
    ->where('path', '.*')
    ->name('profile.image');