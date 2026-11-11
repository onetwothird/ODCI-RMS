<?php

use App\Http\Controllers\FileManagerController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Files & Folders module
|--------------------------------------------------------------------------
|
| Legacy equivalents:
|   roles/admin/files.php        -> GET /admin/files        (document_files)
|   roles/admin/folders.php      -> GET /admin/folders      (folders table)
|   roles/admin/shared.php       -> GET /admin/shared       (file_shares)
|   roles/superadmin/files.php   -> GET /superadmin/files   (document_files)
|   roles/superadmin/folders.php -> GET /superadmin/folders (folders table)
|   roles/user/folders.php       -> GET /user/folders       (department folders)
|
| Actions:
|   POST /folders/action           -> legacy folders.php form actions
|   POST /files/upload             -> department folders upload (files table)
|   GET  /folders/category-files   -> category semester files (JSON)
|   GET  /folders/category-counts  -> per-category counts (JSON)
|   POST /folders/custom           -> create custom folder (JSON)
|   GET  /folders/custom           -> list custom folders (JSON)
|   GET  /folders/custom/contents  -> files inside a custom folder (JSON)
|   POST /folders/custom/delete    -> delete custom folder (JSON)
|   POST /folders/custom/manage    -> rename / favourite / delete (JSON)
|   POST /files/delete             -> remove a document file (JSON)
|   POST /files/update             -> update a document file's description (JSON)
|   POST /files/share              -> share a document file (JSON)
|   GET  /files/{file}/download    -> stream a document file
|   GET  /files/{file}/preview     -> inline preview of a document file
|
*/

Route::middleware(['auth', 'active.account'])->group(function () {
    Route::get('/admin/files', [FileManagerController::class, 'adminFiles'])
        ->middleware('role:admin')
        ->name('admin.files');

    Route::get('/admin/folders', [FileManagerController::class, 'adminFolders'])
        ->middleware('role:admin')
        ->name('admin.folders');

    Route::get('/admin/shared', [FileManagerController::class, 'shared'])
        ->middleware('role:admin')
        ->name('admin.shared');

    Route::get('/superadmin/files', [FileManagerController::class, 'superadminFiles'])
        ->middleware('role:super_admin')
        ->name('superadmin.files');

    Route::get('/superadmin/folders', [FileManagerController::class, 'superadminFolders'])
        ->middleware('role:super_admin')
        ->name('superadmin.folders');

    Route::get('/user/folders', [FileManagerController::class, 'userFolders'])
        ->middleware('role:user')
        ->name('user.folders');
});

Route::middleware(['auth', 'active.account'])->group(function () {
    // Folder management page actions (POST forms, redirect back).
    Route::post('/folders/action', [FileManagerController::class, 'folderAction'])
        ->middleware('role:admin,super_admin')
        ->name('folders.action');

    // User department folders AJAX.
    Route::post('/files/upload', [FileManagerController::class, 'storeUpload'])
        ->middleware('role:user,admin,super_admin')
        ->name('files.upload');

    Route::get('/folders/category-files', [FileManagerController::class, 'getCategoryFiles'])
        ->middleware('role:user')
        ->name('folders.category-files');

    Route::get('/folders/category-counts', [FileManagerController::class, 'categoryCounts'])
        ->middleware('role:user')
        ->name('folders.category-counts');

    Route::post('/folders/custom', [FileManagerController::class, 'createCustomFolder'])
        ->middleware('role:user')
        ->name('folders.custom.store');

    Route::get('/folders/custom', [FileManagerController::class, 'customFolders'])
        ->middleware('role:user')
        ->name('folders.custom');

    Route::get('/folders/custom/contents', [FileManagerController::class, 'customFolderContents'])
        ->middleware('role:user')
        ->name('folders.custom.contents');

    Route::post('/folders/custom/delete', [FileManagerController::class, 'deleteCustomFolder'])
        ->middleware('role:user')
        ->name('folders.custom.delete');

    Route::post('/folders/custom/manage', [FileManagerController::class, 'folderManagement'])
        ->middleware('role:user')
        ->name('folders.custom.manage');

    // Document file actions (file_shares / document_files).
    Route::post('/files/delete', [FileManagerController::class, 'deleteFile'])
        ->middleware('role:admin,super_admin')
        ->name('files.delete');

    Route::post('/files/update', [FileManagerController::class, 'updateFile'])
        ->middleware('role:admin,super_admin')
        ->name('files.update');

    // Share a workspace file (files table -> file_shares, FK file_shares.file_id = files.id).
    Route::post('/files/share', [FileManagerController::class, 'shareFile'])
        ->middleware('role:user,admin,super_admin')
        ->name('files.share');

    Route::get('/files/{file}/download', [FileManagerController::class, 'downloadFile'])
        ->middleware('role:user,admin,super_admin')
        ->name('files.download')->whereNumber('file');

    Route::get('/files/{file}/preview', [FileManagerController::class, 'previewFile'])
        ->middleware('role:user,admin,super_admin')
        ->name('files.preview')->whereNumber('file');

    // `files` table workspace routes (used by the user department folders page).
    Route::get('/workspace/files/{file}/download', [FileManagerController::class, 'workspaceDownload'])
        ->middleware('role:user,admin,super_admin')
        ->name('files.workspace.download')->whereNumber('file');

    Route::get('/workspace/files/{file}/preview', [FileManagerController::class, 'workspacePreview'])
        ->middleware('role:user,admin,super_admin')
        ->name('files.workspace.preview')->whereNumber('file');

    Route::post('/workspace/files/delete', [FileManagerController::class, 'deleteWorkspaceFile'])
        ->middleware('role:user')
        ->name('files.workspace.delete');
});