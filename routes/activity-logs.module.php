<?php

use App\Http\Controllers\SuperAdmin\ActivityLogController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| System activity logs module (super admin)
|--------------------------------------------------------------------------
|
| Legacy equivalent: roles/superadmin/activity_logs.php +
| assets/script/activity_logs.php?ajax=get_logs|get_stats|export_logs|clear_old_logs
|
*/

Route::middleware(['auth', 'active.account', 'role:super_admin'])->prefix('superadmin/activity-logs')->group(function () {
    Route::get('/', [ActivityLogController::class, 'index'])->name('superadmin.activity-logs');
    Route::get('/data', [ActivityLogController::class, 'logs'])->name('superadmin.activity-logs.data');
    Route::get('/stats', [ActivityLogController::class, 'stats'])->name('superadmin.activity-logs.stats');
    Route::get('/export', [ActivityLogController::class, 'export'])->name('superadmin.activity-logs.export');
    Route::post('/clear', [ActivityLogController::class, 'clearOldLogs'])->name('superadmin.activity-logs.clear');
});