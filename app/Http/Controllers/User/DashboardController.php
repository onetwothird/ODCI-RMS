<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Port of roles/user/dashboard.php.
 *
 * Every figure comes from the same SQL the legacy page ran, scoped to the
 * signed-in user exactly as before.
 */
class DashboardController extends Controller
{
    /**
     * GET /user/dashboard
     */
    public function index(Request $request): View
    {
        $user = $request->user();

        // Legacy: SELECT COUNT(*), SUM(file_size), ... FROM files WHERE uploaded_by = ? AND is_deleted = 0
        $fileStats = (object) DB::selectOne("
            SELECT COUNT(*) as file_count,
                   COALESCE(SUM(file_size), 0) as total_size,
                   COALESCE(SUM(CASE WHEN is_public = 1 THEN 1 ELSE 0 END), 0) as public_count,
                   COALESCE(SUM(CASE WHEN is_favorite = 1 THEN 1 ELSE 0 END), 0) as favorite_count
            FROM files f
            WHERE f.uploaded_by = ? AND f.is_deleted = 0
        ", [$user->id]);

        // Legacy: SELECT COUNT(*) FROM folders WHERE created_by = ? AND is_deleted = 0
        $folderCount = (int) DB::selectOne(
            'SELECT COUNT(*) as folder_count FROM folders WHERE created_by = ? AND is_deleted = 0',
            [$user->id]
        )->folder_count;

        // Legacy: department file count (folder's department OR uploader in department)
        $departmentFiles = 0;
        if ($user->department_id) {
            $departmentFiles = (int) DB::selectOne("
                SELECT COUNT(*) as dept_files
                FROM files f
                LEFT JOIN folders fo ON f.folder_id = fo.id
                WHERE (fo.department_id = ? OR f.uploaded_by IN (
                    SELECT id FROM users WHERE department_id = ?
                )) AND f.is_deleted = 0
            ", [$user->department_id, $user->department_id])->dept_files;
        }

        // Legacy: downloads today
        $downloadsToday = (int) DB::selectOne('
            SELECT COUNT(*) as downloads_today
            FROM files f
            WHERE f.uploaded_by = ?
              AND DATE(f.last_downloaded) = CURDATE()
              AND f.is_deleted = 0
        ', [$user->id])->downloads_today;

        // Legacy: last 8 uploads, LEFT JOIN so files without folders still show.
        $recentUploads = DB::select('
            SELECT f.id, f.original_name, f.file_size, f.uploaded_at,
                   COALESCE(fo.folder_name, \'Uncategorized\') as folder_name,
                   f.file_type, f.file_extension,
                   COALESCE(f.download_count, 0) as download_count,
                   COALESCE(f.is_public, 0) as is_public,
                   COALESCE(f.is_favorite, 0) as is_favorite
            FROM files f
            LEFT JOIN folders fo ON f.folder_id = fo.id
            WHERE f.uploaded_by = ? AND f.is_deleted = 0
            ORDER BY f.uploaded_at DESC
            LIMIT 8
        ', [$user->id]);

        $stats = [
            'total_files' => (int) $fileStats->file_count,
            'storage_used' => (float) $fileStats->total_size,
            'public_files' => (int) $fileStats->public_count,
            'favorite_files' => (int) $fileStats->favorite_count,
            'total_folders' => $folderCount,
            'department_files' => $departmentFiles,
            'downloads_today' => $downloadsToday,
            'recent_uploads' => collect($recentUploads),
        ];

        return view('user.dashboard', [
            'stats' => $stats,
        ]);
    }
}
