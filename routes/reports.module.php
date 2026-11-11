<?php

use App\Http\Controllers\SuperAdmin\AnalyticsController;
use App\Http\Controllers\User\MyReportsController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Reports module
|--------------------------------------------------------------------------
|
| Legacy equivalents:
|   roles/user/my_reports.php     -> GET /user/my-reports (+ CSV export)
|   roles/superadmin/reports.php  -> GET /superadmin/reports (+ CSV export)
|
*/

Route::middleware(['auth', 'active.account'])->group(function () {
    Route::get('/user/my-reports', [MyReportsController::class, 'index'])
        ->middleware('role:user')
        ->name('user.my-reports');

    Route::get('/user/my-reports/export', [MyReportsController::class, 'export'])
        ->middleware('role:user')
        ->name('user.my-reports.export');

    Route::get('/superadmin/reports', [AnalyticsController::class, 'index'])
        ->middleware('role:super_admin')
        ->name('superadmin.reports');

    Route::get('/superadmin/reports/export', [AnalyticsController::class, 'export'])
        ->middleware('role:super_admin')
        ->name('superadmin.reports.export');
});