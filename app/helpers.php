<?php

/**
 * Legacy helper functions.
 *
 * Ports of helpers that lived in the legacy includes/config.php and the
 * per-page scripts, kept byte-compatible so views render identical output.
 */

if (! function_exists('formatFileSize')) {
    /**
     * Format bytes into a human-readable size.
     *
     * Port of the legacy formatFileSize() used by dashboard-script.php and
     * departments-script.php:
     *
     *     $units = array('B', 'KB', 'MB', 'GB', 'TB');
     *     for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
     *         $bytes /= 1024;
     *     }
     *     return round($bytes, $precision) . ' ' . $units[$i];
     */
    function formatFileSize(int|float $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];

        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }

        return round($bytes, $precision).' '.$units[$i];
    }
}

if (! function_exists('sanitizeInput')) {
    /**
     * Sanitize input for display.
     *
     * Mirrors the legacy sanitizeInput() function.
     */
    function sanitizeInput(string $data): string
    {
        return htmlspecialchars(strip_tags(trim($data)), ENT_QUOTES, 'UTF-8');
    }
}

if (! function_exists('actionText')) {
    /**
     * Human-readable label for an activity_logs.action value.
     *
     * Port of getActionText() from roles/superadmin/assets/script/dashboard-script.php.
     */
    function actionText(string $action): string
    {
        $actions = [
            'login' => 'Logged in',
            'logout' => 'Logged out',
            'upload_file' => 'Uploaded file',
            'delete_file' => 'Deleted file',
            'create_folder' => 'Created folder',
            'delete_folder' => 'Deleted folder',
            'create_announcement' => 'Created announcement',
            'approve_user' => 'Approved user',
            'create_user' => 'Created user',
            'update_profile' => 'Updated profile',
        ];

        return $actions[$action] ?? ucfirst(str_replace('_', ' ', $action));
    }
}

if (! function_exists('getDashboardUrl')) {
    /**
     * URL of the dashboard that belongs to a role.
     *
     * Port of getDashboardUrl() from includes/auth_check.php. The legacy
     * function returned the role's dashboard path (or the login page with
     * ?error=invalid_role for an unknown role); here the equivalent Laravel
     * routes are returned instead.
     */
    function getDashboardUrl(string $role): string
    {
        return match ($role) {
            'super_admin' => route('superadmin.dashboard'),
            'admin' => route('admin.dashboard'),
            'user' => route('user.dashboard'),
            default => route('login').'?error=invalid_role',
        };
    }
}

if (! function_exists('activityIcon')) {
    /**
     * Boxicons class for an activity_logs.action value.
     *
     * Port of the $icons map inside roles/superadmin/dashboard.php.
     */
    function activityIcon(string $action): string
    {
        $icons = [
            'login' => 'bx-log-in',
            'upload_file' => 'bx-upload',
            'create_folder' => 'bx-folder-plus',
            'approve_user' => 'bx-check',
            'create_announcement' => 'bxs-megaphone',
            'delete_file' => 'bx-trash',
        ];

        return $icons[$action] ?? 'bx-pulse';
    }
}

if (! function_exists('fileTypeIcon')) {
    /**
     * Boxicons solid class for a file type / extension.
     *
     * Port of getFileTypeIcon() from roles/user/dashboard.php.
     */
    function fileTypeIcon(?string $fileType): string
    {
        if (empty($fileType)) {
            return 'bxs-file';
        }

        $icons = [
            // Documents
            'pdf' => 'bxs-file-pdf',
            'doc' => 'bxs-file-doc',
            'docx' => 'bxs-file-doc',
            'txt' => 'bxs-file-txt',
            'rtf' => 'bxs-file-doc',

            // Spreadsheets
            'xls' => 'bxs-spreadsheet',
            'xlsx' => 'bxs-spreadsheet',
            'csv' => 'bxs-spreadsheet',

            // Presentations
            'ppt' => 'bxs-file-doc',
            'pptx' => 'bxs-file-doc',

            // Images
            'jpg' => 'bxs-image',
            'jpeg' => 'bxs-image',
            'png' => 'bxs-image',
            'gif' => 'bxs-image',
            'bmp' => 'bxs-image',
            'svg' => 'bxs-image',
            'webp' => 'bxs-image',

            // Videos
            'mp4' => 'bxs-videos',
            'avi' => 'bxs-videos',
            'mov' => 'bxs-videos',
            'wmv' => 'bxs-videos',
            'flv' => 'bxs-videos',
            'webm' => 'bxs-videos',

            // Audio
            'mp3' => 'bxs-music',
            'wav' => 'bxs-music',
            'flac' => 'bxs-music',
            'aac' => 'bxs-music',

            // Archives
            'zip' => 'bxs-file-archive',
            'rar' => 'bxs-file-archive',
            '7z' => 'bxs-file-archive',
            'tar' => 'bxs-file-archive',
            'gz' => 'bxs-file-archive',

            // Code
            'html' => 'bxs-file-html',
            'css' => 'bxs-file-css',
            'js' => 'bxs-file-js',
            'php' => 'bxs-file-doc',
            'py' => 'bxs-file-doc',
            'java' => 'bxs-file-doc',
            'cpp' => 'bxs-file-doc',
            'c' => 'bxs-file-doc',
        ];

        return $icons[strtolower($fileType)] ?? 'bxs-file';
    }
}
