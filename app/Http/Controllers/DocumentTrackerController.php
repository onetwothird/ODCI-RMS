<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use App\Services\DocumentPeriodHelper;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Document Tracker module — port of roles/admin/document-tracker.php and
 * roles/superadmin/document-tracker.php.
 *
 * ADMIN  (roles/admin/script/tracker.php + document-tracker.php)
 *   - scoped to the admin's own department
 *   - reads the new files + folders system (folder categories)
 *   - document types come from document_requirements for the period,
 *     falling back to the canonical category list
 *
 * SUPERADMIN (roles/superadmin/document-tracker.php)
 *   - department selector first (superadmin.document-tracker without ?department)
 *   - reads the older document_files table directly
 *   - document types are the fixed canonical list in legacy order
 */
class DocumentTrackerController extends Controller
{
    public function __construct(
        private readonly ActivityLogger $activity,
        private readonly DocumentPeriodHelper $periods,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | Admin tracker
    |--------------------------------------------------------------------------
    */

    /**
     * GET /admin/document-tracker
     */
    public function adminIndex(Request $request): View
    {
        $admin = $request->user();
        $departmentId = $this->resolveAdminDepartment($admin);

        if (! $departmentId) {
            return view('admin.document-tracker-no-department', [
                'message' => 'Your admin account is not assigned to any department.',
            ]);
        }

        return $this->renderAdminTracker($request, $admin, $departmentId);
    }

    /*
    |--------------------------------------------------------------------------
    | Superadmin tracker
    |--------------------------------------------------------------------------
    */

    /**
     * GET /superadmin/document-tracker
     */
    public function superadminIndex(Request $request): View
    {
        $superAdmin = $request->user();
        $selectedDepartment = $request->integer('department');

        $departments = $this->allDepartmentsWithCounts();

        if (! $selectedDepartment) {
            return view('superadmin.document-tracker-select', [
                'departments' => $departments,
            ]);
        }

        return $this->renderSuperadminTracker($request, $superAdmin, $selectedDepartment);
    }

    /*
    |--------------------------------------------------------------------------
    | AJAX endpoints
    |--------------------------------------------------------------------------
    */

    /**
     * GET /document-tracker/file-details
     * Returns the actual file rows behind one (faculty, document type) cell.
     * The admin variant reads files + folders; the superadmin variant reads
     * document_files. The correct table is chosen from the caller's role.
     */
    public function fileDetails(Request $request): JsonResponse
    {
        $facultyId = $request->integer('faculty_id');
        $docType = (string) $request->string('document_type');
        $semester = (string) $request->string('semester');

        if (! $facultyId || ! $docType || ! $semester) {
            return response()->json(['success' => false, 'message' => 'Missing parameters'], 400);
        }

        $faculty = DB::table('users')
            ->leftJoin('departments', 'users.department_id', '=', 'departments.id')
            ->where('users.id', $facultyId)
            ->select('users.id', 'users.department_id', 'departments.department_name')
            ->first();

        if (! $faculty) {
            return response()->json(['success' => false, 'message' => 'Faculty member not found'], 404);
        }

        if ($request->user()->isAdmin()) {
            $adminDeptId = $this->resolveAdminDepartment($request->user());
            if ($adminDeptId && $faculty->department_id !== $adminDeptId) {
                return response()->json(['success' => false, 'message' => 'Access denied: Faculty not in your department'], 403);
            }
        }

        return $request->user()->isSuperAdmin()
            ? $this->documentFileRows($facultyId, $docType, $semester)
            : $this->folderFileRows($facultyId, $docType, $semester);
    }

    /**
     * GET /document-tracker/faculty-details — superadmin profile modal.
     */
    public function facultyDetails(Request $request): JsonResponse
    {
        $facultyId = $request->integer('faculty_id');

        $faculty = User::query()
            ->leftJoin('departments', 'users.department_id', '=', 'departments.id')
            ->leftJoin('document_files as df', 'users.id', '=', 'df.uploaded_by')
            ->where('users.id', $facultyId)
            ->with('department')
            ->select(
                'users.*',
                'departments.department_name',
                'departments.department_code',
                DB::raw('COUNT(df.id) as total_files'),
                DB::raw('MAX(df.uploaded_at) as last_upload')
            )
            ->groupBy('users.id')
            ->first();

        if (! $faculty) {
            return response()->json(['success' => false, 'message' => 'Faculty not found'], 404);
        }

        $faculty->profile_image_url = $faculty->profile_image_url;

        return response()->json(['success' => true, 'faculty' => $faculty]);
    }

    /**
     * POST /document-tracker/send-reminder — superadmin only.
     */
    public function sendReminder(Request $request): JsonResponse
    {
        $facultyId = $request->integer('faculty_id');
        $documentType = (string) $request->string('document_type');

        $faculty = DB::table('users')
            ->where('id', $facultyId)
            ->select('name', 'surname', 'email')
            ->first();

        if (! $faculty) {
            return response()->json(['success' => false, 'message' => 'Faculty not found'], 404);
        }

        $title = 'Document Submission Reminder';
        $message = "Please submit your {$documentType} document for the current semester.";

        DB::table('notifications')->insert([
            'user_id' => $facultyId,
            'title' => $title,
            'message' => $message,
            'type' => 'warning',
            'action_url' => null,
            'created_at' => now(),
        ]);

        $this->activity->logRequest(
            $request->user()->id,
            'reminder_sent',
            'user',
            $facultyId,
            "Sent reminder for {$documentType} to {$faculty->name} {$faculty->surname}"
        );

        return response()->json(['success' => true, 'message' => 'Reminder sent successfully']);
    }

    /**
     * GET /document-tracker/all-submissions — superadmin only.
     */
    public function allSubmissions(Request $request): JsonResponse
    {
        $facultyId = $request->integer('faculty_id');
        $academicYear = $request->integer('academic_year');
        $semester = (string) $request->string('semester');

        $semesterDb = (str_contains($semester, 'First') || str_contains($semester, '1st'))
            ? '1st Semester'
            : '2nd Semester';

        $files = DB::table('document_files')
            ->where('uploaded_by', $facultyId)
            ->where('academic_year', $academicYear)
            ->where('semester_period', $semesterDb)
            ->orderByDesc('uploaded_at')
            ->get();

        return response()->json(['success' => true, 'files' => $files]);
    }

    /*
    |--------------------------------------------------------------------------
    | Rendering
    |--------------------------------------------------------------------------
    */

    /**
     * Rows behind an admin cell: files + folders under the folder category
     * that maps to the given canonical document type.
     */
    private function folderFileRows(int $facultyId, string $docType, string $semester): JsonResponse
    {
        $parsed = $this->periods->periodParse($semester);
        $academicYear = $parsed['start_year'] > 0 ? $parsed['start_year'] : (int) date('Y');
        $folderSemester = $parsed['start_year'] > 0 ? $parsed['semester'] : $this->periods->semesterColumn($semester);

        $docTypeToCategory = $this->periods->documentTypeToCategory();
        $folderCategory = $docTypeToCategory[$docType] ?? $docType;

        $files = DB::table('files as f')
            ->join('folders as fo', 'f.folder_id', '=', 'fo.id')
            ->join('users as u', 'f.uploaded_by', '=', 'u.id')
            ->where('f.uploaded_by', $facultyId)
            ->where('fo.category', $folderCategory)
            ->where('f.semester', $folderSemester)
            ->whereRaw($this->periods->academicYearSql('f.academic_year'), [$academicYear])
            ->where('f.is_deleted', 0)
            ->where('fo.is_deleted', 0)
            ->orderByDesc('f.uploaded_at')
            ->select(
                'f.id', 'f.file_name', 'f.original_name', 'f.file_path', 'f.file_size',
                'f.uploaded_at', 'f.description', 'f.mime_type', 'f.file_extension',
                DB::raw('COALESCE(f.download_count, 0) as download_count'),
                'fo.category as file_type',
                DB::raw("CONCAT(u.name, ' ', u.surname) as uploader_name"),
                'u.employee_id',
                DB::raw("CONCAT(CAST(LEFT(TRIM(f.academic_year), 4) AS UNSIGNED), '-', CAST(LEFT(TRIM(f.academic_year), 4) AS UNSIGNED) + 1) as period_display")
            )
            ->get();

        return response()->json([
            'success' => true,
            'files' => $files,
            'period' => $academicYear.'-'.($academicYear + 1),
            'semester' => $folderSemester,
            'document_type' => $docType,
        ]);
    }

    /**
     * Rows behind a superadmin cell: document_files rows.
     */
    private function documentFileRows(int $facultyId, string $docType, string $semester): JsonResponse
    {
        $normalizedSemester = '2nd Semester';
        $academicYear = (int) date('Y');

        if (preg_match('/^(.+?) AY (\d{4})-\d{4}$/', $semester, $matches)) {
            $normalizedSemester = $matches[1];
            $academicYear = (int) $matches[2];
        }

        $files = DB::table('document_files as df')
            ->join('users as u', 'df.uploaded_by', '=', 'u.id')
            ->where('df.uploaded_by', $facultyId)
            ->where('df.file_type', $docType)
            ->where('df.semester_period', $normalizedSemester)
            ->where('df.academic_year', $academicYear)
            ->orderByDesc('df.uploaded_at')
            ->select(
                'df.id', 'df.file_name', 'df.file_path', 'df.file_size',
                'df.uploaded_at', 'df.description', 'df.file_type',
                DB::raw('COALESCE(df.download_count, 0) as download_count'),
                DB::raw("CONCAT(u.name, ' ', u.surname) as uploader_name"),
                'u.employee_id',
                'df.academic_year',
                'df.semester_period'
            )
            ->get();

        return response()->json([
            'success' => true,
            'files' => $files,
            'period' => $academicYear.'-'.($academicYear + 1),
            'semester' => $normalizedSemester,
            'document_type' => $docType,
        ]);
    }

    /**
     * Admin tracker: files + folders system, scoped to one department.
     */
    private function renderAdminTracker(Request $request, User $user, int $departmentId): View
    {
        $departmentName = DB::table('departments')
            ->where('id', $departmentId)
            ->value('department_name') ?? 'Unknown Department';

        $hasParentId = DB::getSchemaBuilder()->hasColumn('departments', 'parent_id');

        // --- Selected period ---------------------------------------------------
        $selectedSemester = (string) $request->query('semester', '');
        $selectedYear = null;
        $normalizedSemester = '';
        $folderSemester = 'first';

        if ($selectedSemester === '') {
            $latest = DB::table('files as f')
                ->join('folders as fo', 'f.folder_id', '=', 'fo.id')
                ->join('users as u', 'f.uploaded_by', '=', 'u.id')
                ->where('u.role', 'user')
                ->where('u.is_approved', 1)
                ->where('u.department_id', $departmentId)
                ->where('f.is_deleted', 0)
                ->where('fo.is_deleted', 0)
                ->whereNotNull('fo.category')
                ->select(
                    DB::raw($this->periods->academicYearExpr('f.academic_year').' as start_year'),
                    'f.semester as semester',
                    DB::raw('COUNT(f.id) as file_count')
                )
                ->groupBy('start_year', 'f.semester')
                ->orderByDesc('start_year')
                ->orderByRaw("CASE f.semester WHEN 'first' THEN 1 WHEN 'second' THEN 2 ELSE 3 END DESC")
                ->limit(1)
                ->first();

            if ($latest && (int) $latest->start_year > 0) {
                $selectedYear = (int) $latest->start_year;
                $folderSemester = $this->periods->semesterColumn($latest->semester);
                $normalizedSemester = $this->periods->semesterOrdinal($latest->semester);
                $selectedSemester = $this->periods->periodKey($selectedYear, $latest->semester);
            } else {
                $selectedYear = (int) date('Y');
                $folderSemester = 'second';
                $normalizedSemester = '2nd Semester';
                $selectedSemester = $this->periods->periodKey($selectedYear, 'second');
            }
        } else {
            $parsed = $this->periods->periodParse($selectedSemester);
            if ($parsed['start_year'] > 0) {
                $folderSemester = $parsed['semester'];
                $normalizedSemester = $this->periods->semesterOrdinal($parsed['semester']);
                $selectedYear = $parsed['start_year'];
            }
        }

        // --- Document types -----------------------------------------------------
        $documentTypes = $this->periods->periodRequiredDocumentTypes($selectedYear, $folderSemester);
        if (empty($documentTypes)) {
            $documentTypes = $this->periods->defaultDocumentTypes();
        }

        // --- Faculty ------------------------------------------------------------
        $search = (string) $request->query('search', '');
        $statusFilter = (string) $request->query('status', '');

        $facultyQuery = DB::table('users as u')
            ->leftJoin('departments as d', 'u.department_id', '=', 'd.id')
            ->where('u.role', 'user')
            ->where('u.is_approved', 1)
            ->where('u.department_id', $departmentId);

        if ($search !== '') {
            $term = '%'.$search.'%';
            $facultyQuery->where(function ($q) use ($term) {
                $q->where(DB::raw("CONCAT(u.name, ' ', u.surname)"), 'LIKE', $term)
                    ->orWhere('u.employee_id', 'LIKE', $term)
                    ->orWhere('u.email', 'LIKE', $term);
            });
        }

        $faculty = $facultyQuery
            ->select(
                'u.id', 'u.name', 'u.mi', 'u.surname', 'u.employee_id', 'u.email',
                'u.position', 'u.profile_image', 'd.department_name', 'd.department_code'
            )
            ->orderBy('d.department_name')
            ->orderBy('u.surname')
            ->orderBy('u.name')
            ->get()
            ->each(function ($staff) {
                $staff->profile_image_url = $this->resolveProfileImage($staff->profile_image);
            });

        // --- Submissions (files + folders) --------------------------------------
        $fileSubmissions = $this->buildFolderSubmissions($faculty, $selectedYear, $folderSemester);

        // --- Filters / stats ----------------------------------------------------
        [$faculty, $submittedCount, $completeFaculty, $totalPossible] =
            $this->applyStatusFilterAndStats($faculty, $fileSubmissions, $documentTypes, $statusFilter);

        $totalFaculty = count($faculty);
        $completionRate = $totalPossible > 0 ? round(($submittedCount / $totalPossible) * 100, 1) : 0;
        $facultyCompletionRate = $totalFaculty > 0 ? round(($completeFaculty / $totalFaculty) * 100, 1) : 0;

        // --- Department filter options ------------------------------------------
        $departments = DB::table('departments');
        if ($hasParentId) {
            $departments->where(fn ($q) => $q->where('id', $departmentId)->orWhere('parent_id', $departmentId));
        } else {
            $departments->where('id', $departmentId);
        }
        $departments = $departments->orderBy('department_name')->get(['id', 'department_name']);

        // Available periods for the dropdown (validated against globals).
        $availablePeriods = $this->folderAvailablePeriods($departmentId);

        $this->activity->logRequest(
            $user->id,
            'view_document_tracker',
            'department',
            $departmentId,
            'Viewed document tracker',
            ['department_id' => $departmentId]
        );

        return view('admin.document-tracker', [
            'currentUser' => $user,
            'departmentId' => $departmentId,
            'departmentName' => $departmentName,
            'selectedSemester' => $selectedSemester,
            'selectedYear' => $selectedYear,
            'normalizedSemester' => $normalizedSemester,
            'documentTypes' => $documentTypes,
            'faculty' => $faculty,
            'fileSubmissions' => $fileSubmissions,
            'totalFaculty' => $totalFaculty,
            'submittedCount' => $submittedCount,
            'totalPossible' => $totalPossible,
            'completeFaculty' => $completeFaculty,
            'completionRate' => $completionRate,
            'facultyCompletionRate' => $facultyCompletionRate,
            'departments' => $departments,
            'search' => $search,
            'statusFilter' => $statusFilter,
            'availablePeriods' => $availablePeriods,
        ]);
    }

    /**
     * Superadmin tracker: reads document_files directly.
     */
    private function renderSuperadminTracker(Request $request, User $user, int $departmentId): View
    {
        $department = DB::table('departments')
            ->where('id', $departmentId)
            ->where('is_active', 1)
            ->first(['id', 'department_name', 'department_code']);

        if (! $department) {
            return view('superadmin.document-tracker-select', [
                'departments' => $this->allDepartmentsWithCounts(),
                'error' => 'Department not found or inactive.',
            ]);
        }

        // --- Selected period (document_files based) ------------------------------
        $selectedSemester = (string) $request->query('semester', '');
        $selectedYear = null;
        $normalizedSemester = '';

        if ($selectedSemester === '') {
            $latest = DB::table('document_files as df')
                ->join('users as u', 'df.uploaded_by', '=', 'u.id')
                ->where('u.role', 'user')
                ->where('u.is_approved', 1)
                ->where('u.department_id', $departmentId)
                ->select('df.academic_year', 'df.semester_period', DB::raw('COUNT(df.id) as file_count'))
                ->groupBy('df.academic_year', 'df.semester_period')
                ->orderByDesc('df.academic_year')
                ->orderByRaw("CASE df.semester_period WHEN '1st Semester' THEN 1 WHEN '2nd Semester' THEN 2 ELSE 3 END DESC")
                ->limit(1)
                ->first();

            if ($latest) {
                $selectedYear = (int) $latest->academic_year;
                $normalizedSemester = $latest->semester_period;
                $selectedSemester = $normalizedSemester.' AY '.$selectedYear.'-'.($selectedYear + 1);
            } else {
                $selectedYear = (int) date('Y');
                $normalizedSemester = '2nd Semester';
                $selectedSemester = $normalizedSemester.' AY '.$selectedYear.'-'.($selectedYear + 1);
            }
        } else {
            if (preg_match('/^(.+?) AY (\d{4})-\d{4}$/', $selectedSemester, $matches)) {
                $normalizedSemester = $matches[1];
                $selectedYear = (int) $matches[2];
            }
        }

        // --- Document types (canonical order, as legacy hard-coded) ---------------
        $documentTypes = [
            'IPCR Accomplishment',
            'IPCR Target',
            'Workload',
            'Course Syllabus',
            'Course Syllabus Acceptance Form',
            'Exam',
            'TOS',
            'Class Record',
            'Grading Sheets',
            'Attendance Sheet',
            "Stakeholder's Feedback Form w/ Summary",
            'Consultation',
            'Lecture',
            'Activities',
            'CEIT-QF-03 Discussion Form',
            'Others',
        ];

        // --- Faculty (admins + users, like legacy) --------------------------------
        $faculty = DB::table('users as u')
            ->leftJoin('departments as d', 'u.department_id', '=', 'd.id')
            ->where('u.department_id', $departmentId)
            ->whereIn('u.role', ['admin', 'user'])
            ->where('u.is_approved', 1)
            ->select(
                'u.id', 'u.name', 'u.mi', 'u.surname', 'u.employee_id', 'u.email',
                'u.position', 'u.profile_image', 'd.department_name', 'd.department_code'
            )
            ->orderBy('u.surname')
            ->orderBy('u.name')
            ->get()
            ->each(function ($staff) {
                $staff->profile_image_url = $this->resolveProfileImage($staff->profile_image);
            });

        // --- Submissions (document_files) ------------------------------------------
        $fileSubmissions = $this->buildDocumentFileSubmissions($faculty, $selectedYear, $normalizedSemester);

        // --- Stats -------------------------------------------------------------------
        $submittedCount = 0;
        $completeFaculty = 0;
        $totalPossible = count($faculty) * count($documentTypes);

        foreach ($faculty as $f) {
            $facultySubmitted = 0;
            foreach ($documentTypes as $dt) {
                if (isset($fileSubmissions[$f->id][$dt])) {
                    $submittedCount++;
                    $facultySubmitted++;
                }
            }
            if ($facultySubmitted === count($documentTypes)) {
                $completeFaculty++;
            }
        }

        $totalFaculty = count($faculty);
        $completionRate = $totalPossible > 0 ? round(($submittedCount / $totalPossible) * 100, 1) : 0;
        $facultyCompletionRate = $totalFaculty > 0 ? round(($completeFaculty / $totalFaculty) * 100, 1) : 0;

        $availablePeriods = $this->documentFileAvailablePeriods($departmentId);

        $this->activity->logRequest(
            $user->id,
            'view_document_tracker_superadmin',
            'department',
            $departmentId,
            'Viewed document tracker (superadmin)',
            ['department_id' => $departmentId]
        );

        return view('superadmin.document-tracker', [
            'currentUser' => $user,
            'department' => $department,
            'departmentId' => $departmentId,
            'departmentName' => $department->department_name,
            'selectedSemester' => $selectedSemester,
            'selectedYear' => $selectedYear,
            'normalizedSemester' => $normalizedSemester,
            'documentTypes' => $documentTypes,
            'faculty' => $faculty,
            'fileSubmissions' => $fileSubmissions,
            'totalFaculty' => $totalFaculty,
            'submittedCount' => $submittedCount,
            'totalPossible' => $totalPossible,
            'completeFaculty' => $completeFaculty,
            'completionRate' => $completionRate,
            'facultyCompletionRate' => $facultyCompletionRate,
            'availablePeriods' => $availablePeriods,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    private function resolveAdminDepartment(User $admin): ?int
    {
        if ($admin->department_id) {
            return $admin->department_id;
        }

        $result = DB::selectOne(
            "SELECT u.department_id FROM users u
             WHERE u.id = ? AND u.role = 'admin'",
            [$admin->id]
        );

        return $result && $result->department_id ? (int) $result->department_id : null;
    }

    private function allDepartmentsWithCounts(): \Illuminate\Support\Collection
    {
        return DB::table('departments as d')
            ->leftJoin('users as u', fn ($j) => $j
                ->on('d.id', '=', 'u.department_id')
                ->where('u.is_approved', 1))
            ->where('d.is_active', 1)
            ->select(
                'd.id', 'd.department_name', 'd.department_code',
                DB::raw('COUNT(DISTINCT u.id) as user_count'),
                DB::raw("COUNT(DISTINCT CASE WHEN u.role = 'admin' THEN u.id END) as admin_count")
            )
            ->groupBy('d.id', 'd.department_name', 'd.department_code')
            ->orderBy('d.department_name')
            ->get();
    }

    /**
     * files + folders grouped by (faculty, folder category) for a period.
     */
    private function buildFolderSubmissions($faculty, ?int $selectedYear, string $folderSemester): array
    {
        $submissions = [];

        if ($faculty->isEmpty() || ! $selectedYear) {
            return $submissions;
        }

        $facultyIds = $faculty->pluck('id')->all();
        $placeholders = implode(',', array_fill(0, count($facultyIds), '?'));

        $rows = DB::select("
            SELECT
                f.uploaded_by as faculty_id,
                fo.category as folder_category,
                COUNT(f.id) as file_count,
                MAX(f.uploaded_at) as latest_upload,
                MIN(f.uploaded_at) as first_upload,
                SUM(f.file_size) as total_size
            FROM files f
            INNER JOIN folders fo ON f.folder_id = fo.id
            WHERE f.uploaded_by IN ($placeholders)
              AND ".$this->periods->academicYearSql('f.academic_year')."
              AND f.semester = ?
              AND f.is_deleted = 0
              AND fo.is_deleted = 0
              AND fo.category IS NOT NULL
            GROUP BY f.uploaded_by, fo.category
        ", array_merge($facultyIds, [$selectedYear, $folderSemester]));

        $categoryToDocType = $this->periods->categoryToDocumentType();

        foreach ($rows as $row) {
            $matchedType = $categoryToDocType[$row->folder_category] ?? $row->folder_category;
            $submissions[$row->faculty_id][$matchedType] = [
                'file_count' => (int) $row->file_count,
                'latest_upload' => $row->latest_upload,
                'first_upload' => $row->first_upload,
                'total_size' => (int) ($row->total_size ?? 0),
                'status' => 'submitted',
            ];
        }

        return $submissions;
    }

    /**
     * document_files grouped by (faculty, file_type) for a period.
     */
    private function buildDocumentFileSubmissions($faculty, ?int $selectedYear, string $normalizedSemester): array
    {
        $submissions = [];

        if ($faculty->isEmpty() || ! $selectedYear) {
            return $submissions;
        }

        $facultyIds = $faculty->pluck('id')->all();
        $placeholders = implode(',', array_fill(0, count($facultyIds), '?'));

        $rows = DB::select("
            SELECT
                df.uploaded_by as faculty_id,
                df.file_type as document_type,
                COUNT(df.id) as file_count,
                MAX(df.uploaded_at) as latest_upload,
                MIN(df.uploaded_at) as first_upload,
                SUM(df.file_size) as total_size
            FROM document_files df
            WHERE df.uploaded_by IN ($placeholders)
              AND df.academic_year = ?
              AND df.semester_period = ?
            GROUP BY df.uploaded_by, df.file_type
        ", array_merge($facultyIds, [$selectedYear, $normalizedSemester]));

        foreach ($rows as $row) {
            $submissions[$row->faculty_id][$row->document_type] = [
                'file_count' => (int) $row->file_count,
                'latest_upload' => $row->latest_upload,
                'first_upload' => $row->first_upload,
                'total_size' => (int) ($row->total_size ?? 0),
                'status' => 'submitted',
            ];
        }

        return $submissions;
    }

    /**
     * Apply the status filter and compute overall stats over the (possibly
     * filtered) faculty list. Returns [faculty, submittedCount, completeFaculty, totalPossible].
     */
    private function applyStatusFilterAndStats($faculty, array $submissions, array $documentTypes, string $statusFilter): array
    {
        $submittedCount = 0;
        $completeFaculty = 0;

        $progressOf = function ($f) use ($submissions, $documentTypes): int {
            $count = 0;
            foreach ($documentTypes as $dt) {
                if (isset($submissions[$f->id][$dt])) {
                    $count++;
                }
            }

            return $count;
        };

        if ($statusFilter !== '') {
            $faculty = $faculty->filter(function ($f) use ($progressOf, $documentTypes, $statusFilter) {
                $submitted = $progressOf($f);
                $pct = count($documentTypes) > 0 ? ($submitted / count($documentTypes)) * 100 : 0;

                return match ($statusFilter) {
                    'complete' => $pct == 100,
                    'partial' => $pct > 0 && $pct < 100,
                    'none' => $pct == 0,
                    default => true,
                };
            })->values();
        }

        foreach ($faculty as $f) {
            $facultySubmitted = $progressOf($f);
            $submittedCount += $facultySubmitted;
            if ($facultySubmitted === count($documentTypes)) {
                $completeFaculty++;
            }
        }

        $totalPossible = count($faculty) * count($documentTypes);

        return [$faculty, $submittedCount, $completeFaculty, $totalPossible];
    }

    private function folderAvailablePeriods(int $departmentId): array
    {
        return DB::table('files as f')
            ->join('folders as fo', 'f.folder_id', '=', 'fo.id')
            ->join('users as u', 'f.uploaded_by', '=', 'u.id')
            ->where('u.role', 'user')
            ->where('u.is_approved', 1)
            ->where('u.department_id', $departmentId)
            ->where('f.is_deleted', 0)
            ->where('fo.is_deleted', 0)
            ->whereNotNull('fo.category')
            ->select(
                DB::raw($this->periods->academicYearExpr('f.academic_year').' as start_year'),
                'f.semester as semester',
                DB::raw('COUNT(f.id) as file_count')
            )
            ->groupBy('start_year', 'f.semester')
            ->orderByDesc('start_year')
            ->orderByRaw("CASE f.semester WHEN 'first' THEN 1 WHEN 'second' THEN 2 ELSE 3 END ASC")
            ->get()
            ->filter(fn ($p) => (int) $p->start_year >= 1)
            ->values()
            ->all();
    }

    private function documentFileAvailablePeriods(int $departmentId): array
    {
        return DB::table('document_files as df')
            ->join('users as u', 'df.uploaded_by', '=', 'u.id')
            ->where('u.role', 'user')
            ->where('u.is_approved', 1)
            ->where('u.department_id', $departmentId)
            ->select('df.academic_year', 'df.semester_period', DB::raw('COUNT(df.id) as file_count'))
            ->groupBy('df.academic_year', 'df.semester_period')
            ->orderByDesc('df.academic_year')
            ->orderByRaw("CASE df.semester_period WHEN '1st Semester' THEN 1 WHEN '2nd Semester' THEN 2 ELSE 3 END ASC")
            ->get()
            ->all();
    }

    private function resolveProfileImage(?string $stored): ?string
    {
        if (empty($stored)) {
            return null;
        }

        $cleanPath = ltrim($stored, '/');

        return app(\App\Services\LegacyFileResolver::class)->url($cleanPath, 'profiles');
    }
}