<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Announcement;
use App\Models\Department;
use App\Models\File;
use App\Models\Folder;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Port of roles/superadmin/dashboard.php + assets/script/dashboard-script.php.
 *
 * The stats array mirrors the legacy $stats keys one-for-one, so the view
 * renders exactly what the legacy page rendered.
 */
class DashboardController extends Controller
{
    /**
     * GET /superadmin/dashboard
     */
    public function index(Request $request): View
    {
        // Legacy: SELECT COUNT(*) FROM users WHERE role != 'super_admin'
        $totalUsers = User::query()->where('role', '!=', 'super_admin')->count();

        // Legacy: SELECT COUNT(*) FROM users WHERE is_approved = 0
        $pendingUsers = User::query()->where('is_approved', 0)->count();

        // Legacy: SELECT COUNT(*), SUM(file_size) FROM files WHERE is_deleted = 0
        $fileData = File::query()->notDeleted()
            ->selectRaw('COUNT(*) as count, COALESCE(SUM(file_size), 0) as size')
            ->first();

        $totalFolders = Folder::query()->notDeleted()->count();
        $totalDepartments = Department::query()->where('is_active', 1)->count();
        $totalAnnouncements = Announcement::query()->where('is_deleted', 0)->count();

        // Legacy: last 10 activity_logs joined with users.
        $recentActivities = ActivityLog::query()
            ->leftJoin('users', 'activity_logs.user_id', '=', 'users.id')
            ->select([
                'activity_logs.*',
                'users.name',
                'users.mi',
                'users.surname',
                'users.username',
            ])
            ->orderByDesc('activity_logs.created_at')
            ->limit(10)
            ->get();

        // Legacy: per-department aggregates for active departments.
        $departmentStats = DB::table('departments as d')
            ->leftJoin('users as u', function ($join) {
                $join->on('d.id', '=', 'u.department_id')->where('u.is_approved', '=', 1);
            })
            ->leftJoin('folders as f', function ($join) {
                $join->on('d.id', '=', 'f.department_id')->where('f.is_deleted', '=', 0);
            })
            ->leftJoin('files as fi', function ($join) {
                $join->on('f.id', '=', 'fi.folder_id')->where('fi.is_deleted', '=', 0);
            })
            ->where('d.is_active', 1)
            ->groupBy('d.id', 'd.department_name', 'd.department_code')
            ->selectRaw("
                d.department_name, d.department_code,
                COUNT(DISTINCT u.id) as user_count,
                COUNT(DISTINCT f.id) as folder_count,
                COUNT(DISTINCT fi.id) as file_count,
                COALESCE(SUM(fi.file_size), 0) as total_size
            ")
            ->orderByDesc('user_count')
            ->get();

        $stats = [
            'total_users' => $totalUsers,
            'pending_users' => $pendingUsers,
            'total_files' => (int) $fileData->count,
            'storage_used' => (int) $fileData->size,
            'total_folders' => $totalFolders,
            'total_departments' => $totalDepartments,
            'total_announcements' => $totalAnnouncements,
            'recent_activities' => $recentActivities,
            'department_stats' => $departmentStats,
        ];

        return view('superadmin.dashboard', [
            'stats' => $stats,
        ]);
    }
}
