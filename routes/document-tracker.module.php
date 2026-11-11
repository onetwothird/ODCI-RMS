<?php

use App\Http\Controllers\DocumentTrackerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Document Tracker module
|--------------------------------------------------------------------------
|
| Legacy equivalents:
|   roles/admin/document-tracker.php        -> GET /admin/document-tracker
|   roles/admin/script/tracker.php          -> page bootstrap for admin
|   roles/superadmin/document-tracker.php   -> GET /superadmin/document-tracker
|
| AJAX (superadmin page used ?action=... on the same URL; the admin page
| used a separate document-tracker.js expecting get_file_details):
|
|   GET  /document-tracker/file-details     -> rows behind one table cell
|   GET  /document-tracker/faculty-details  -> superadmin faculty profile modal
|   GET  /document-tracker/all-submissions  -> superadmin all files for a period
|   POST /document-tracker/send-reminder    -> superadmin reminder notification
|
*/

Route::middleware(['auth', 'active.account'])->group(function () {
    Route::get('/admin/document-tracker', [DocumentTrackerController::class, 'adminIndex'])
        ->middleware('role:admin')
        ->name('admin.document-tracker');

    Route::get('/superadmin/document-tracker', [DocumentTrackerController::class, 'superadminIndex'])
        ->middleware('role:super_admin')
        ->name('superadmin.document-tracker');

    Route::get('/document-tracker/file-details', [DocumentTrackerController::class, 'fileDetails'])
        ->middleware('role:admin,super_admin')
        ->name('document-tracker.file-details');

    Route::get('/document-tracker/faculty-details', [DocumentTrackerController::class, 'facultyDetails'])
        ->middleware('role:super_admin')
        ->name('document-tracker.faculty-details');

    Route::get('/document-tracker/all-submissions', [DocumentTrackerController::class, 'allSubmissions'])
        ->middleware('role:super_admin')
        ->name('document-tracker.all-submissions');

    Route::post('/document-tracker/send-reminder', [DocumentTrackerController::class, 'sendReminder'])
        ->middleware('role:super_admin')
        ->name('document-tracker.send-reminder');
});