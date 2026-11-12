<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Data provider for the admin dashboard.
 *
 * Port of roles/admin/script/dashboard.php: every figure the dashboard renders
 * is computed here from the live database. A department admin is scoped to
 * their own department (the same rule files.php and reports.php use); a super
 * admin sees the whole system.
 *
 * Nothing in this class emits output - it only prepares variables for the view.
 */
class AdminDashboardData
{
    /**
     * Build every variable the admin dashboard view needs.
     *
     * @return array{scopeLabel: string, summary: array, changes: array, bars: array,
     *               recentActivity: array, recentNotifications: array,
     *               unreadNotifications: int, quotaBytes: float, freeBytes: float,
     *               account: array}
     */
    public function forUser(User $user): array
    {
        /* ------------------------------------------------------------------------
         * Scope
         * --------------------------------------------------------------------- */
        $departmentId = $user->department_id ? (int) $user->department_id : null;
        $departmentName = $user->department?->department_name ?? 'Unassigned';
        $isScoped = $user->role === 'admin' && $departmentId !== null;
        $scopeLabel = $isScoped ? $departmentName : 'All departments';

        // WHERE fragments that restrict a query to the admin's department.
        $filesScope = $isScoped ? ' AND fo.department_id = ?' : '';
        $foldersScope = $isScoped ? ' AND department_id = ?' : '';
        $usersScope = $isScoped ? ' AND department_id = ?' : '';
        $activityScope = $isScoped ? ' AND u.department_id = ?' : '';
        $scopeParams = $isScoped ? [$departmentId] : [];

        /* ------------------------------------------------------------------------
         * Aggregates
         * --------------------------------------------------------------------- */
        $summary = [
            'files_total' => 0,
            'files_total_system' => 0,
            'folders_total' => 0,
            'folders_total_system' => 0,
            'storage_bytes' => 0,
            'users_total' => 0,
            'users_approved' => 0,
            'users_pending' => 0,
            'users_active' => 0,
            'uploads_this_week' => 0,
        ];

        $current = ['files' => 0, 'folders' => 0, 'storage' => 0, 'users' => 0];
        $previous = ['files' => 0, 'folders' => 0, 'storage' => 0, 'users' => 0];

        // Documents in scope. files.folder_id is mandatory, so the join never drops a live row.
        $row = (object) DB::selectOne("
            SELECT COUNT(*) AS total_files,
                   COALESCE(SUM(f.file_size), 0) AS total_bytes
            FROM files f
            INNER JOIN folders fo ON f.folder_id = fo.id
            WHERE f.is_deleted = 0
            {$filesScope}
        ", $scopeParams);

        $summary['files_total'] = (int) $row->total_files;
        $summary['storage_bytes'] = (float) $row->total_bytes;

        // System-wide counts, used as the denominators for the scope share bars.
        $summary['files_total_system'] = (int) DB::selectOne(
            'SELECT COUNT(*) AS total FROM files WHERE is_deleted = 0'
        )->total;

        $summary['folders_total'] = (int) DB::selectOne(
            "SELECT COUNT(*) AS total_folders FROM folders WHERE is_deleted = 0 {$foldersScope}",
            $scopeParams
        )->total_folders;

        $summary['folders_total_system'] = (int) DB::selectOne(
            'SELECT COUNT(*) AS total FROM folders WHERE is_deleted = 0'
        )->total;

        // Faculty accounts in scope.
        $row = (object) DB::selectOne("
            SELECT COUNT(*) AS total_users,
                   COALESCE(SUM(is_approved = 1), 0) AS approved_users,
                   COALESCE(SUM(is_approved = 0), 0) AS pending_users,
                   COALESCE(SUM(is_approved = 1
                                AND last_login >= DATE_SUB(NOW(), INTERVAL 30 DAY)), 0) AS active_users
            FROM users
            WHERE role = 'user'
            {$usersScope}
        ", $scopeParams);

        $summary['users_total'] = (int) $row->total_users;
        $summary['users_approved'] = (int) $row->approved_users;
        $summary['users_pending'] = (int) $row->pending_users;
        $summary['users_active'] = (int) $row->active_users;

        /* Month-over-month deltas: last 30 days against the 30 days before that. */
        $windows = [
            'files' => [
                'table' => 'files f INNER JOIN folders fo ON f.folder_id = fo.id',
                'base' => 'f.is_deleted = 0',
                'scope' => $filesScope,
                'date' => 'f.uploaded_at',
                'agg' => '1',
            ],
            'folders' => [
                'table' => 'folders',
                'base' => 'is_deleted = 0',
                'scope' => $foldersScope,
                'date' => 'created_at',
                'agg' => '1',
            ],
            'users' => [
                'table' => 'users',
                'base' => "role = 'user'",
                'scope' => $usersScope,
                'date' => 'created_at',
                'agg' => '1',
            ],
            'storage' => [
                'table' => 'files f INNER JOIN folders fo ON f.folder_id = fo.id',
                'base' => 'f.is_deleted = 0',
                'scope' => $filesScope,
                'date' => 'f.uploaded_at',
                'agg' => 'f.file_size',
            ],
        ];

        foreach ($windows as $key => $window) {
            $row = (object) DB::selectOne("
                SELECT
                    COALESCE(SUM(CASE WHEN {$window['date']} >= DATE_SUB(NOW(), INTERVAL 30 DAY)
                                      THEN {$window['agg']} ELSE 0 END), 0) AS recent,
                    COALESCE(SUM(CASE WHEN {$window['date']} >= DATE_SUB(NOW(), INTERVAL 60 DAY)
                                       AND {$window['date']} < DATE_SUB(NOW(), INTERVAL 30 DAY)
                                      THEN {$window['agg']} ELSE 0 END), 0) AS prior
                FROM {$window['table']}
                WHERE {$window['base']}
                {$window['scope']}
            ", $scopeParams);

            $current[$key] = (float) $row->recent;
            $previous[$key] = (float) $row->prior;
        }

        // Recent uploads are only counted for files that still exist.
        $summary['uploads_this_week'] = (int) DB::selectOne("
            SELECT COUNT(*) AS total
            FROM files f
            INNER JOIN folders fo ON f.folder_id = fo.id
            WHERE f.is_deleted = 0
              AND f.uploaded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            {$filesScope}
        ", $scopeParams)->total;

        /* ------------------------------------------------------------------------
         * Recent activity (grouped over the last 7 days, most recent first)
         * --------------------------------------------------------------------- */
        $recentActivity = [];

        $activityRows = DB::select("
            SELECT al.action,
                   al.resource_type,
                   MAX(al.created_at) AS last_at,
                   COUNT(*) AS occurrences,
                   u.name,
                   u.mi,
                   u.surname,
                   d.department_name
            FROM activity_logs al
            INNER JOIN users u ON al.user_id = u.id
            LEFT JOIN departments d ON d.id = u.department_id
            WHERE al.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
            {$activityScope}
            GROUP BY al.action, al.resource_type, u.id, u.name, u.mi, u.surname, d.department_name
            ORDER BY last_at DESC
            LIMIT 8
        ", $scopeParams);

        foreach ($activityRows as $row) {
            [$label, $icon, $badgeClass, $badgeIcon] = $this->activityMeta($row->action, $row->resource_type);

            $recentActivity[] = [
                'label' => $label,
                'icon' => $icon,
                'user' => trim($row->name.' '.(! empty($row->mi) ? $row->mi.'. ' : '').$row->surname),
                'department' => ! empty($row->department_name) ? $row->department_name : 'No department',
                'when' => $this->timeAgo($row->last_at),
                'badge' => $badgeClass,
                'badgeIcon' => $badgeIcon,
                'badgeText' => ucwords(str_replace('_', ' ', (string) $row->resource_type)),
                'occurrences' => (int) $row->occurrences,
            ];
        }

        /* ------------------------------------------------------------------------
         * Recent notifications (both feeds merged, matching the navbar dropdown)
         * --------------------------------------------------------------------- */
        $recentNotifications = [];
        $unreadNotifications = 0;

        $notificationRows = DB::select('
            SELECT id, \'system\' AS source, title, message, type, is_read, created_at
            FROM notifications
            WHERE user_id = ? AND (expires_at IS NULL OR expires_at > NOW())
            UNION ALL
            SELECT id, \'social\' AS source,
                   CASE notification_type
                       WHEN \'new_post\'        THEN \'New post\'
                       WHEN \'post_comment\'    THEN \'Post comment\'
                       WHEN \'post_like\'       THEN \'Post reaction\'
                       WHEN \'comment_like\'    THEN \'Comment reaction\'
                       WHEN \'comment_reply\'   THEN \'Comment reply\'
                       WHEN \'post_mention\'    THEN \'Post mention\'
                       WHEN \'comment_mention\' THEN \'Comment mention\'
                       ELSE \'Social activity\'
                   END AS title,
                   message, notification_type AS type, is_read, created_at
            FROM post_notifications
            WHERE user_id = ?
            ORDER BY created_at DESC
            LIMIT 5
        ', [$user->id, $user->id]);

        foreach ($notificationRows as $row) {
            [$tone, $icon] = $this->notificationMeta($row->type);

            if (empty($row->is_read)) {
                $unreadNotifications++;
            }

            $recentNotifications[] = [
                'title' => $row->title,
                'message' => $row->message,
                'when' => $this->timeAgo($row->created_at),
                'tone' => $tone,
                'icon' => $icon,
                'isRead' => ! empty($row->is_read),
            ];
        }

        /* ------------------------------------------------------------------------
         * Derived values for the view
         * --------------------------------------------------------------------- */
        // The uploads volume is the real storage budget for stored documents.
        $uploadsDir = rtrim((string) config('odci.legacy_root'), '/'.chr(92)).'/'.trim((string) config('odci.uploads_dir'), '/'.chr(92));
        if (! is_dir($uploadsDir)) {
            $uploadsDir = base_path();
        }
        // Shared hosts often disable disk_*_space(); on PHP 8 calling a disabled
        // function throws, so treat the quota as unknown instead of crashing.
        $quotaBytes = $this->diskBytes('disk_total_space', $uploadsDir);
        $freeBytes = $this->diskBytes('disk_free_space', $uploadsDir);
        $storagePct = $quotaBytes > 0
            ? $this->percent($summary['storage_bytes'], $quotaBytes)
            : 0.0;

        $changes = [
            'files' => $this->change($current['files'], $previous['files']),
            'folders' => $this->change($current['folders'], $previous['folders']),
            'storage' => $this->change($current['storage'], $previous['storage'], false),
        ];

        $bars = [
            'files' => $this->percent($summary['files_total'], $summary['files_total_system']),
            'folders' => $this->percent($summary['folders_total'], $summary['folders_total_system']),
            'storage' => $storagePct,
            'users' => $this->percent($summary['users_active'], $summary['users_approved']),
        ];

        /* ------------------------------------------------------------------------
         * Account information
         *
         * Legacy wrote $_SESSION['login_time'] once at authentication, which is the
         * honest "last login" (users.last_login is not rewritten per request here,
         * so the column carries the same value in Laravel).
         * --------------------------------------------------------------------- */
        $loginTimestamp = (int) session('login_time') ?: ($user->last_login
            ? \Illuminate\Support\Carbon::parse($user->last_login)->getTimestamp()
            : 0);

        $account = [
            'department' => $departmentName,
            'employeeId' => $user->employee_id ?: 'Not assigned',
            'lastLogin' => $loginTimestamp ? date('M j, Y, g:i A', $loginTimestamp) : 'No record',
            'memberSince' => $user->created_at ? $user->created_at->format('M j, Y') : 'Unknown',
        ];

        if ($user->account_locked_until && strtotime($user->account_locked_until) > time()) {
            $account['status'] = ['Locked', 'bxs-lock', 'critical'];
        } elseif (! $user->is_approved) {
            $account['status'] = ['Pending Approval', 'bxs-time', 'warning'];
        } elseif ($user->is_restricted) {
            $account['status'] = ['Restricted', 'bxs-error-circle', 'warning'];
        } else {
            $account['status'] = ['Active', 'bx-check-circle', 'success'];
        }

        return [
            'scopeLabel' => $scopeLabel,
            'summary' => $summary,
            'changes' => $changes,
            'bars' => $bars,
            'recentActivity' => $recentActivity,
            'recentNotifications' => $recentNotifications,
            'unreadNotifications' => $unreadNotifications,
            'quotaBytes' => $quotaBytes,
            'freeBytes' => $freeBytes,
            'account' => $account,
        ];
    }

    /**
     * Renders a byte count as a short human readable string.
     *
     * Port of adminDash_formatBytes().
     */
    public static function formatBytes(int|float $bytes, int $precision = 1): string
    {
        $bytes = (float) $bytes;
        if ($bytes <= 0) {
            return '0 B';
        }

        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);
        $value = $bytes / (1024 ** $power);

        return ($power === 0 ? (string) $value : number_format($value, $precision)).' '.$units[$power];
    }

    /**
     * Renders a timestamp as "3 hours ago".
     *
     * Port of adminDash_timeAgo().
     */
    protected function timeAgo(?string $datetime): string
    {
        $timestamp = empty($datetime) ? false : strtotime($datetime);
        if ($timestamp === false) {
            return 'unknown';
        }

        $seconds = max(0, time() - $timestamp);
        $units = [
            ['minute', 60],
            ['hour', 3600],
            ['day', 86400],
            ['month', 2592000],
            ['year', 31536000],
        ];

        foreach ($units as [$label, $span]) {
            if ($seconds < $span) {
                continue;
            }
            $count = (int) floor($seconds / $span);

            return $count.' '.$label.($count === 1 ? '' : 's').' ago';
        }

        return 'just now';
    }

    /**
     * Safe percentage, clamped to 0-100. Returns 0 when there is no denominator.
     *
     * Port of adminDash_percent().
     */
    protected function percent(mixed $part, mixed $whole): float
    {
        $whole = (float) $whole;
        if ($whole <= 0) {
            return 0.0;
        }

        return max(0.0, min(100.0, round(((float) $part / $whole) * 100, 1)));
    }

    /**
     * Builds the month-over-month delta shown under a statistic.
     *
     * @param  bool  $higherIsBetter  Set false for metrics where growth is not good
     *                               news (storage consumption), so the trend is
     *                               coloured the way an admin reads it.
     *
     * Port of adminDash_change().
     */
    protected function change(mixed $current, mixed $previous, bool $higherIsBetter = true): array
    {
        $current = (float) $current;
        $previous = (float) $previous;

        if ($previous > 0) {
            $pct = (($current - $previous) / $previous) * 100;
        } elseif ($current > 0) {
            $pct = 100.0;
        } else {
            $pct = 0.0;
        }

        $pct = round($pct, 1);

        if ($pct === 0.0) {
            return [
                'label' => 'No change vs last month',
                'icon' => 'bx-minus',
                'class' => 'neutral',
            ];
        }

        $rising = $pct > 0;

        return [
            'label' => ($rising ? '+' : '-').number_format(abs($pct), 1).'% vs last month',
            'icon' => $rising ? 'bx-trending-up' : 'bx-trending-down',
            'class' => $rising === $higherIsBetter ? 'positive' : 'negative',
        ];
    }

    /**
     * Presentation details for an activity_logs row.
     *
     * Port of adminDash_activityMeta(). Every icon class is verified against
     * boxicons 2.0.9, the version the legacy pages load.
     *
     * @return array{0: string, 1: string, 2: string, 3: string}
     */
    protected function activityMeta(?string $action, ?string $resourceType): array
    {
        $actions = [
            'login' => ['Signed in', 'bxs-user', 'status-public', 'bx-right-arrow-alt'],
            'login_google' => ['Signed in with Google', 'bxs-user', 'status-public', 'bx-right-arrow-alt'],
            'auto_login' => ['Signed in automatically', 'bxs-user', 'status-public', 'bx-right-arrow-alt'],
            'logout' => ['Signed out', 'bxs-log-out', 'status-private', 'bx-right-arrow-alt'],
            'failed_login' => ['Failed sign-in attempt', 'bxs-error-circle', 'status-favorite', 'bx-error'],
            'session_expired' => ['Session expired', 'bxs-time', 'status-private', 'bx-time'],
            'upload_file' => ['Uploaded a file', 'bxs-file-pdf', 'status-completed', 'bx-check'],
            'document_upload' => ['Uploaded a document', 'bxs-file-doc', 'status-completed', 'bx-check'],
            'file_download' => ['Downloaded a file', 'bxs-download', 'status-completed', 'bx-check'],
            'create_folder' => ['Created a folder', 'bxs-folder', 'status-completed', 'bx-check'],
            'create_announcement' => ['Published an announcement', 'bxs-megaphone', 'status-public', 'bxs-megaphone'],
            'approve_user' => ['Approved a user', 'bxs-user-plus', 'status-completed', 'bx-check'],
            'register' => ['Registered an account', 'bxs-user-plus', 'status-favorite', 'bx-user-plus'],
            'register_google' => ['Registered with Google', 'bxs-user-plus', 'status-favorite', 'bx-user-plus'],
            'view_faculty_list' => ['Viewed the faculty list', 'bxs-group', 'status-private', 'bx-show'],
            'update_profile' => ['Updated their profile', 'bxs-user-detail', 'status-private', 'bx-edit'],
            'profile_image_update' => ['Updated their profile photo', 'bxs-camera', 'status-private', 'bx-edit'],
            'password_reset_request' => ['Requested a password reset', 'bxs-key', 'status-favorite', 'bx-error'],
            'send_message' => ['Sent a message', 'bxs-message', 'status-private', 'bx-share'],
            'create_user' => ['Created a user', 'bxs-user-plus', 'status-completed', 'bx-check'],
        ];

        if (isset($actions[$action])) {
            return $actions[$action];
        }

        // Unknown action: fall back to the resource it touched so the row still
        // reads sensibly instead of showing a raw enum value.
        $resources = [
            'file' => 'bxs-file',
            'folder' => 'bxs-folder',
            'user' => 'bxs-user',
            'announcement' => 'bxs-megaphone',
            'department' => 'bxs-buildings',
            'system' => 'bxs-cog',
        ];

        return [
            ucfirst(str_replace('_', ' ', (string) $action)),
            $resources[$resourceType] ?? 'bxs-cog',
            'status-private',
            'bx-cog',
        ];
    }

    /**
     * Presentation details for a notification, covering both notification feeds.
     *
     * Port of adminDash_notificationMeta().
     *
     * @return array{0: string, 1: string}
     */
    protected function notificationMeta(?string $type): array
    {
        $meta = [
            // notifications.type
            'success' => ['ok', 'bx-check'],
            'warning' => ['warn', 'bx-error'],
            'error' => ['error', 'bx-error-circle'],
            'info' => ['info', 'bx-info-circle'],
            // post_notifications.notification_type
            'new_post' => ['info', 'bx-news'],
            'post_comment' => ['info', 'bx-comment'],
            'post_like' => ['ok', 'bx-heart'],
            'comment_like' => ['ok', 'bx-heart'],
            'comment_reply' => ['info', 'bx-reply'],
            'post_mention' => ['info', 'bx-at'],
            'comment_mention' => ['info', 'bx-at'],
        ];

        return $meta[$type] ?? ['info', 'bx-bell'];
    }

    /**
     * Calls disk_total_space()/disk_free_space() when the host allows it.
     */
    private function diskBytes(string $function, string $path): float
    {
        if (! function_exists($function)) {
            return 0.0;
        }

        try {
            $bytes = @$function($path);
        } catch (\Throwable) {
            return 0.0;
        }

        return is_numeric($bytes) ? (float) $bytes : 0.0;
    }
}
