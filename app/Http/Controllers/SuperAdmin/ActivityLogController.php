<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * System activity logs module (super admin).
 *
 * Legacy equivalent: roles/superadmin/activity_logs.php +
 * assets/script/activity_logs.php (AJAX endpoints).
 */
class ActivityLogController extends Controller
{
    public function index(Request $request): \Illuminate\View\View
    {
        return view('superadmin.activity_logs', [
            'currentUser' => $request->user(),
            'users' => DB::table('users')->where('is_approved', 1)
                ->orderBy('name')->orderBy('surname')
                ->get(['id', 'username']),
            'resourceTypes' => DB::table('activity_logs')
                ->distinct()->orderBy('resource_type')->pluck('resource_type'),
        ]);
    }

    public function logs(Request $request): JsonResponse
    {
        $page = max(1, (int) $request->integer('page', 1));
        $limit = min(100, max(10, (int) $request->integer('limit', 25)));
        $offset = ($page - 1) * $limit;

        $query = DB::table('activity_logs as al')
            ->leftJoin('users as u', 'al.user_id', '=', 'u.id')
            ->leftJoin('departments as d', 'u.department_id', '=', 'd.id')
            ->select(
                'al.*', 'u.username', 'u.name', 'u.surname', 'u.role', 'u.profile_image',
                'd.department_name', 'd.department_code'
            );

        if ($from = $request->string('date_from')->value()) {
            $query->where('al.created_at', '>=', $from.' 00:00:00');
        }
        if ($to = $request->string('date_to')->value()) {
            $query->where('al.created_at', '<=', $to.' 23:59:59');
        }
        if ($userId = $request->integer('user_id')) {
            $query->where('al.user_id', $userId);
        }
        if ($action = $request->string('action')->value()) {
            $query->where('al.action', 'LIKE', '%'.$action.'%');
        }
        if ($resourceType = $request->string('resource_type')->value()) {
            $query->where('al.resource_type', $resourceType);
        }
        if ($search = trim((string) $request->string('search'))) {
            $term = '%'.$search.'%';
            $query->where(fn ($q) => $q->where('al.description', 'LIKE', $term)
                ->orWhere('al.action', 'LIKE', $term)
                ->orWhere('u.username', 'LIKE', $term)
                ->orWhere('u.name', 'LIKE', $term)
                ->orWhere('u.surname', 'LIKE', $term));
        }

        $total = (clone $query)->count();

        $logs = (clone $query)
            ->orderByDesc('al.created_at')
            ->offset($offset)
            ->limit($limit)
            ->get()
            ->map(function ($log) {
                $log->user_full_name = trim(($log->name ?? '').' '.($log->surname ?? ''));
                if ($log->user_full_name === '') {
                    $log->user_full_name = $log->username ?? 'Unknown User';
                }
                $log->formatted_date = $log->created_at;
                $log->time_ago = $this->timeAgo($log->created_at);
                $log->action_badge = $this->actionBadgeClass($log->action);
                $log->resource_icon = $this->resourceIcon($log->resource_type);

                return $log;
            });

        return response()->json([
            'success' => true,
            'data' => $logs,
            'pagination' => [
                'current_page' => $page,
                'total_pages' => (int) ceil($total / $limit),
                'total_records' => (int) $total,
                'limit' => $limit,
            ],
        ]);
    }

    public function stats(): JsonResponse
    {
        $stats = [
            'today_count' => (int) DB::table('activity_logs')->whereDate('created_at', now()->toDateString())->count(),
            'week_count' => (int) DB::table('activity_logs')->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()])->count(),
            'month_count' => (int) DB::table('activity_logs')->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()])->count(),
            'top_users' => DB::table('activity_logs as al')
                ->join('users as u', 'al.user_id', '=', 'u.id')
                ->where('al.created_at', '>=', now()->subDays(7))
                ->selectRaw('u.username, u.name, u.surname, COUNT(*) as activity_count')
                ->groupBy('al.user_id', 'u.username', 'u.name', 'u.surname')
                ->orderByDesc('activity_count')
                ->limit(5)
                ->get(),
            'resource_breakdown' => DB::table('activity_logs')
                ->where('created_at', '>=', now()->subDays(7))
                ->selectRaw('resource_type, COUNT(*) as count')
                ->groupBy('resource_type')
                ->orderByDesc('count')
                ->get(),
            'hourly_activity' => DB::table('activity_logs')
                ->where('created_at', '>=', now()->subDay())
                ->selectRaw('HOUR(created_at) as hour, COUNT(*) as count')
                ->groupBy('hour')
                ->orderBy('hour')
                ->get(),
            'common_actions' => DB::table('activity_logs')
                ->where('created_at', '>=', now()->subDays(7))
                ->selectRaw('action, COUNT(*) as count')
                ->groupBy('action')
                ->orderByDesc('count')
                ->limit(8)
                ->get(),
        ];

        return response()->json(['success' => true, 'stats' => $stats]);
    }

    public function export(Request $request): Response
    {
        $query = DB::table('activity_logs as al')
            ->leftJoin('users as u', 'al.user_id', '=', 'u.id')
            ->select('al.created_at', 'al.action', 'al.resource_type', 'al.description', 'al.ip_address', 'u.username');

        if ($from = $request->string('date_from')->value()) {
            $query->where('al.created_at', '>=', $from.' 00:00:00');
        }
        if ($to = $request->string('date_to')->value()) {
            $query->where('al.created_at', '<=', $to.' 23:59:59');
        }
        if ($userId = $request->integer('user_id')) {
            $query->where('al.user_id', $userId);
        }
        if ($resourceType = $request->string('resource_type')->value()) {
            $query->where('al.resource_type', $resourceType);
        }

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, ['Date/Time', 'User', 'Action', 'Resource Type', 'Description', 'IP Address']);

        foreach ($query->orderByDesc('al.created_at')->limit(5000)->get() as $log) {
            fputcsv($handle, [
                $log->created_at, $log->username ?? 'System', $log->action,
                $log->resource_type, $log->description, $log->ip_address,
            ]);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response("\xEF\xBB\xBF".$csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="activity-logs.csv"',
            'Cache-Control' => 'no-store',
        ]);
    }

    public function clearOldLogs(Request $request): JsonResponse
    {
        $days = max(1, $request->integer('days', 30));
        $cutoff = now()->subDays($days);

        $deleted = DB::table('activity_logs')->where('created_at', '<', $cutoff)->delete();

        $this->audit($request, $deleted, $days);

        return response()->json([
            'success' => true,
            'message' => $deleted > 0
                ? $deleted.' log(s) older than '.$days.' day(s) were cleared.'
                : 'No logs older than '.$days.' day(s) were found.',
            'deleted' => $deleted,
        ]);
    }

    /* ----------------------------------------------------------------------
     | Helpers
     |---------------------------------------------------------------------- */

    private function audit(Request $request, int $deleted, int $days): void
    {
        try {
            DB::table('activity_logs')->insert([
                'user_id' => $request->user()?->id,
                'action' => 'clear_old_logs',
                'resource_type' => 'system',
                'description' => 'Cleared '.$deleted.' activity log(s) older than '.$days.' days',
                'ip_address' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'created_at' => now(),
            ]);
        } catch (\Throwable $e) {
            // Never break the request on an audit failure.
        }
    }

    private function timeAgo(string $date): string
    {
        $diff = now()->diffInSeconds($date);

        return match (true) {
            $diff < 60 => 'just now',
            $diff < 3600 => floor($diff / 60).' min ago',
            $diff < 86400 => floor($diff / 3600).' hr ago',
            $diff < 604800 => floor($diff / 86400).' days ago',
            default => date('M j, Y', strtotime($date)),
        };
    }

    private function actionBadgeClass(string $action): string
    {
        return match (true) {
            str_contains($action, 'login') => 'badge-info',
            str_contains($action, 'delete') || str_contains($action, 'remove') => 'badge-danger',
            str_contains($action, 'create') || str_contains($action, 'upload') || str_contains($action, 'store') => 'badge-success',
            str_contains($action, 'update') || str_contains($action, 'edit') => 'badge-warning',
            default => 'badge-primary',
        };
    }

    private function resourceIcon(string $resourceType): string
    {
        return match ($resourceType) {
            'file' => 'bx-file',
            'folder' => 'bx-folder',
            'user' => 'bx-user',
            'announcement' => 'bxs-megaphone',
            'department' => 'bx-building',
            default => 'bx-cog',
        };
    }
}