<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Services\FacultyStatsService;
use App\Services\LegacyFileResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Response;
use Illuminate\View\View;
use Throwable;

/**
 * Port of roles/admin/view-faculty-staff.php + script/faculty-staff.php.
 *
 * The legacy page includes script/faculty-staff.php, which builds:
 *   - the admin's department identity (banner),
 *   - the faculty listing (approved users with role 'user' in the admin's
 *     department),
 *   - per-faculty submission stats (summary cards + "View Stats" AJAX action),
 *   - the messaging AJAX actions this page's JS posts (send_message /
 *     send_bulk_message, action handlers from script/messaging-system.php).
 */
class FacultyStaffController extends Controller
{
    public function __construct(
        private readonly FacultyStatsService $stats,
        private readonly LegacyFileResolver $files,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * GET /admin/view-faculty-staff
     */
    public function index(Request $request): View|\Illuminate\Contracts\View\View|\Symfony\Component\HttpFoundation\Response
    {
        $admin = $request->user();

        $departmentId = $admin->department_id;
        $departmentName = null;
        $departmentCode = null;

        // Enhanced department validation with better error handling (legacy
        // re-fetched the user's department_id when the session value was empty).
        if (! $departmentId) {
            $result = DB::selectOne("
                SELECT u.department_id, d.department_name, d.department_code
                FROM users u
                LEFT JOIN departments d ON u.department_id = d.id
                WHERE u.id = ?
            ", [$admin->id]);

            if ($result) {
                $departmentId = $result->department_id;
                $departmentName = $result->department_name;
                $departmentCode = $result->department_code;
            }
        }

        if (! $departmentId) {
            // Legacy die() block: no department assignment.
            $errorMessage = 'Your account is not assigned to any department. Please contact the system administrator.';

            Log::error("Admin user {$admin->id} ({$admin->username}) has no department assignment");

            return Response::make("
                <div style='padding: 40px; text-align: center; font-family: Arial, sans-serif;'>
                    <h2 style='color: #dc3545;'>Department Assignment Required</h2>
                    <p style='color: #666; font-size: 16px;'>$errorMessage</p>
                    <a href='".route('admin.dashboard')."' style='display: inline-block; margin-top: 20px; padding: 12px 24px;
                       background: #007bff; color: white; text-decoration: none; border-radius: 5px;'>
                       Return to Dashboard
                    </a>
                </div>
            ");
        }

        // Get department details if not already fetched.
        if ($departmentName === null) {
            $department = DB::selectOne(
                'SELECT department_name, department_code, description FROM departments WHERE id = ?',
                [$departmentId]
            );

            if ($department) {
                $departmentName = $department->department_name;
                $departmentCode = $department->department_code;
            } else {
                Log::error("Department ID $departmentId referenced by user {$admin->id} does not exist");
                $departmentName = 'Unknown Department';
                $departmentCode = 'UNK';
            }
        }

        $currentAdmin = [
            'id' => $admin->id,
            'username' => $admin->username,
            'role' => $admin->role,
            'department_id' => $departmentId,
            'department_name' => $departmentName,
            'department_code' => $departmentCode,
        ];

        // Enhanced faculty staff query with better data validation and profile
        // image handling.
        $facultyStaff = [];
        try {
            $rows = DB::select("
                SELECT
                    u.id,
                    u.username,
                    u.email,
                    u.name,
                    u.mi,
                    u.surname,
                    CONCAT(
                        TRIM(u.name),
                        CASE
                            WHEN u.mi IS NOT NULL AND TRIM(u.mi) != ''
                            THEN CONCAT(' ', TRIM(u.mi), '. ')
                            ELSE ' '
                        END,
                        TRIM(u.surname)
                    ) AS full_name,
                    u.employee_id,
                    u.position,
                    u.profile_image,
                    u.last_login,
                    u.is_approved,
                    u.phone,
                    u.hire_date,
                    u.created_at as account_created,
                    u.email_verified,
                    u.is_restricted,
                    d.department_name,
                    d.department_code,
                    (SELECT COUNT(*) FROM faculty_document_submissions fds
                     WHERE fds.faculty_id = u.id AND fds.academic_year = YEAR(CURDATE())) as submissions_this_year,
                    (SELECT MAX(submitted_at) FROM faculty_document_submissions fds
                     WHERE fds.faculty_id = u.id) as last_submission
                FROM users u
                LEFT JOIN departments d ON u.department_id = d.id
                WHERE u.role = 'user'
                AND u.department_id = ?
                AND u.is_approved = 1
                AND u.id != ?
                ORDER BY u.surname ASC, u.name ASC
            ", [$departmentId, $admin->id]);

            $facultyStaff = array_map(fn ($row) => (array) $row, $rows);

            // Validate and enhance faculty data.
            foreach ($facultyStaff as &$faculty) {
                if (empty($faculty['full_name']) || trim($faculty['full_name']) == '') {
                    $faculty['full_name'] = $faculty['username'] ?: 'Unknown User';
                }

                $faculty['profile_image_url'] = $this->profileImageUrl($faculty['profile_image']);

                // Determine online status (logged in within last hour)
                $faculty['is_online'] = $faculty['last_login']
                    && strtotime($faculty['last_login']) > strtotime('-1 hour');

                // Determine activity status (logged in within last 7 days)
                $faculty['recently_active'] = $faculty['last_login']
                    && strtotime($faculty['last_login']) > strtotime('-7 days');

                if (empty($faculty['position'])) {
                    $faculty['position'] = 'Faculty Member';
                }

                // Format dates for display
                $faculty['last_login_formatted'] = $faculty['last_login']
                    ? date('M j, Y \a\t g:i A', strtotime($faculty['last_login'])) : 'Never';

                $faculty['account_age_days'] = $faculty['account_created']
                    ? floor((time() - strtotime($faculty['account_created'])) / 86400) : 0;

                // Add submission activity indicators
                $faculty['has_recent_submissions'] = $faculty['last_submission']
                    && strtotime($faculty['last_submission']) > strtotime('-30 days');
            }
            unset($faculty);
        } catch (Throwable $e) {
            Log::error('Error fetching faculty staff: '.$e->getMessage());
            $facultyStaff = [];
        }

        // Compute each faculty's stats once and reuse.
        $facultyStats = [];
        $totalFaculty = count($facultyStaff);
        $highCompletion = 0;
        $mediumCompletion = 0;
        $lowCompletion = 0;
        $activeCount = 0;
        $verifiedCount = 0;

        foreach ($facultyStaff as $faculty) {
            $facultyStats[$faculty['id']] = $this->stats->forUser((int) $faculty['id']);

            $completionRate = $facultyStats[$faculty['id']]['completion_rate'];
            if ($completionRate >= 80) {
                $highCompletion++;
            } elseif ($completionRate >= 50) {
                $mediumCompletion++;
            } else {
                $lowCompletion++;
            }

            if ($faculty['recently_active']) {
                $activeCount++;
            }
            if ($faculty['email_verified']) {
                $verifiedCount++;
            }
        }

        // Log page view (legacy logAdminAction(... 'view_faculty_list' ...)).
        $this->activity->logRequest($admin->id, 'view_faculty_list', 'user', null, 'Viewed faculty staff page');

        return view('admin.view-faculty-staff', [
            'currentAdmin' => $currentAdmin,
            'facultyStaff' => $facultyStaff,
            'facultyStats' => $facultyStats,
            'totalFaculty' => $totalFaculty,
            'highCompletion' => $highCompletion,
            'mediumCompletion' => $mediumCompletion,
            'lowCompletion' => $lowCompletion,
            'activeCount' => $activeCount,
            'verifiedCount' => $verifiedCount,
        ]);
    }

    /**
     * POST /admin/view-faculty-staff/stats
     * AJAX action 'get_faculty_stats' from script/faculty-staff.php.
     */
    public function stats(Request $request): JsonResponse
    {
        $facultyId = $request->input('faculty_id');

        if (! $facultyId) {
            return response()->json(['success' => false, 'error' => 'Faculty ID required']);
        }

        return response()->json([
            'success' => true,
            'data' => $this->stats->forUser((int) $facultyId),
        ]);
    }

    /**
     * POST /admin/view-faculty-staff/send-message
     * AJAX action 'send_message' from script/messaging-system.php.
     */
    public function sendMessage(Request $request): JsonResponse
    {
        $user = $request->user();

        $recipientId = $request->input('recipient_id');
        $subject = sanitizeInput((string) $request->input('subject', ''));
        $message = sanitizeInput((string) $request->input('message', ''));
        $priority = $request->input('priority', 'normal');
        $messageType = $request->input('message_type', 'general');

        if (! $recipientId || ! $message) {
            return response()->json(['success' => false, 'message' => 'Recipient and message are required']);
        }

        try {
            $recipient = DB::selectOne("
                SELECT id, email, name, surname, department_id
                FROM users
                WHERE id = ? AND is_approved = 1
            ", [$recipientId]);

            if (! $recipient) {
                return response()->json(['success' => false, 'message' => 'Recipient not found']);
            }

            // Check if admin can message this user (same department or admin
            // privileges).
            if ($user->role === 'admin' && $user->department_id !== $recipient->department_id) {
                return response()->json(['success' => false, 'message' => 'Cannot send message to user in different department']);
            }

            DB::insert("
                INSERT INTO messages (sender_id, recipient_id, subject, message, priority, message_type, sent_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ", [$user->id, $recipientId, $subject, $message, $priority, $messageType]);

            $messageId = (int) DB::getPdo()->lastInsertId();

            // Add notification
            DB::insert("
                INSERT INTO notifications (user_id, title, message, type, action_url)
                VALUES (?, ?, ?, ?, ?)
            ", [$recipientId, $subject ?: 'New Message', substr($message, 0, 100).(strlen($message) > 100 ? '...' : ''), 'info', "messages.php?id=$messageId"]);

            // Log activity
            $this->activity->logRequest($user->id, 'send_message', 'user', $recipientId, "Sent message: $subject");

            return response()->json([
                'success' => true,
                'message' => 'Message sent successfully',
                'message_id' => $messageId,
            ]);
        } catch (Throwable $e) {
            Log::error('Error sending message: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Failed to send message']);
        }
    }

    /**
     * POST /admin/view-faculty-staff/send-bulk-message
     * AJAX action 'send_bulk_message' from script/messaging-system.php.
     */
    public function sendBulkMessage(Request $request): JsonResponse
    {
        $user = $request->user();

        $recipientIds = json_decode((string) $request->input('recipient_ids', '[]'), true) ?: [];
        $subject = sanitizeInput((string) $request->input('subject', ''));
        $message = sanitizeInput((string) $request->input('message', ''));
        $priority = $request->input('priority', 'normal');
        $messageType = $request->input('message_type', 'announcement');

        if (empty($recipientIds) || ! $message) {
            return response()->json(['success' => false, 'message' => 'Recipients and message are required']);
        }

        try {
            $successCount = 0;
            $failedCount = 0;

            foreach ($recipientIds as $recipientId) {
                try {
                    $recipient = DB::selectOne("
                        SELECT id, department_id
                        FROM users
                        WHERE id = ? AND is_approved = 1
                    ", [$recipientId]);

                    if ($recipient && ($user->role === 'super_admin' || $user->department_id === $recipient->department_id)) {
                        DB::insert("
                            INSERT INTO messages (sender_id, recipient_id, subject, message, priority, message_type, sent_at)
                            VALUES (?, ?, ?, ?, ?, ?, NOW())
                        ", [$user->id, $recipientId, $subject, $message, $priority, $messageType]);

                        // Add notification
                        DB::insert("
                            INSERT INTO notifications (user_id, title, message, type, action_url)
                            VALUES (?, ?, ?, ?, ?)
                        ", [$recipientId, $subject ?: 'Bulk Message', substr($message, 0, 100).(strlen($message) > 100 ? '...' : ''), 'info', null]);

                        $successCount++;
                    } else {
                        $failedCount++;
                    }
                } catch (Throwable $e) {
                    Log::error("Error sending bulk message to user $recipientId: ".$e->getMessage());
                    $failedCount++;
                }
            }

            // Log activity
            $this->activity->logRequest($user->id, 'send_bulk_message', 'system', null, "Sent bulk message to $successCount users. Failed: $failedCount");

            return response()->json([
                'success' => true,
                'message' => "Message sent to $successCount recipients. $failedCount failed.",
                'success_count' => $successCount,
                'failed_count' => $failedCount,
            ]);
        } catch (Throwable $e) {
            Log::error('Error sending bulk message: '.$e->getMessage());

            return response()->json(['success' => false, 'message' => 'Failed to send bulk message']);
        }
    }

    /**
     * Resolve a stored users.profile_image to a browser-loadable URL.
     * Mirrors odci_profile_image_url(): returns the fallback CVSU logo when the
     * file is missing, prefixed with a cache-buster when it exists.
     */
    private function profileImageUrl(?string $stored): string
    {
        $absolute = $this->files->resolve((string) $stored, 'profiles');

        if ($absolute !== null) {
            return route('legacy.uploads', ['path' => $this->files->relativePath($absolute)])
                .'?v='.filemtime($absolute);
        }

        return asset('img/cvsu-logo.png');
    }
}