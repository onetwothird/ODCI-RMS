<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

/**
 * Files & Folders module.
 *
 * Legacy equivalents:
 *   roles/admin/files.php        -> GET /admin/files        (document_files)
 *   roles/admin/folders.php      -> GET /admin/folders      (folders table)
 *   roles/admin/shared.php       -> GET /admin/shared       (file_shares)
 *   roles/superadmin/files.php   -> GET /superadmin/files   (document_files)
 *   roles/superadmin/folders.php -> GET /superadmin/folders (folders table)
 *   roles/user/folders.php       -> GET /user/folders       (department folders)
 *
 * The faculty Document Tracker reads files+folders (files table); the All
 * Files pages read document_files, which is what the upload handler writes.
 */
class FileManagerController extends Controller
{
    private const FOLDER_TYPES = ['category', 'custom', 'system'];
    private const FOLDER_STATUS = ['active', 'archived', 'hidden'];

    public function __construct(
        private readonly ActivityLogger $activity,
    ) {}

    /*
    |--------------------------------------------------------------------------
    | All Files (admin + superadmin)
    |--------------------------------------------------------------------------
    */

    public function files(Request $request, string $role): \Illuminate\View\View
    {
        $user = $request->user();
        $isSuperAdmin = $user->isSuperAdmin();

        $page = max(1, (int) $request->integer('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $search = trim((string) $request->string('search'));
        $departmentFilter = trim((string) $request->string('department'));
        $typeFilter = trim((string) $request->string('file_type'));
        $userFilter = (int) $request->integer('user_filter');
        $dateFilter = trim((string) $request->string('date_filter'));
        $yearFilter = trim((string) $request->string('academic_year'));
        $semesterFilter = trim((string) $request->string('semester'));

        $where = ['1=1'];
        $params = [];

        // Scope: admins stay inside their own department.
        if (! $isSuperAdmin) {
            $deptId = $this->resolveAdminDepartment($user);
            if ($deptId) {
                $where[] = 'u.department_id = ?';
                $params[] = $deptId;
            }
        }

        if ($search !== '') {
            $term = '%'.$search.'%';
            $where[] = '(df.file_name LIKE ? OR df.description LIKE ? OR u.name LIKE ? OR u.surname LIKE ?)';
            array_push($params, $term, $term, $term, $term);
        }
        if ($departmentFilter !== '') {
            $where[] = 'u.department_id = ?';
            $params[] = $departmentFilter;
        }
        if ($typeFilter !== '') {
            $where[] = 'df.file_type = ?';
            $params[] = $typeFilter;
        }
        if ($userFilter > 0) {
            $where[] = 'df.uploaded_by = ?';
            $params[] = $userFilter;
        }
        if ($yearFilter !== '') {
            $where[] = 'df.academic_year = ?';
            $params[] = $yearFilter;
        }
        if ($semesterFilter !== '') {
            $where[] = 'df.semester_period = ?';
            $params[] = $semesterFilter;
        }
        if ($dateFilter !== '') {
            $where[] = match ($dateFilter) {
                'today' => 'DATE(df.uploaded_at) = CURDATE()',
                'week' => 'df.uploaded_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)',
                'month' => 'df.uploaded_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)',
                'quarter' => 'df.uploaded_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)',
                default => '1=1',
            };
        }

        $from = ' FROM document_files df
                  LEFT JOIN users u ON df.uploaded_by = u.id
                  LEFT JOIN departments d ON u.department_id = d.id';
        $whereClause = implode(' AND ', $where);

        $totalFiles = (int) DB::selectOne(
            'SELECT COUNT(*) as c'.$from.' WHERE '.$whereClause,
            $params
        )->c;

        $totalPages = (int) ceil($totalFiles / $limit);
        if ($totalPages > 0 && $page > $totalPages) {
            $page = $totalPages;
            $offset = ($page - 1) * $limit;
        }

        $files = DB::select(
            'SELECT df.*,
                    u.username, u.name AS user_name, u.surname, u.mi, u.employee_id, u.position,
                    d.department_name, d.department_code,
                    CONCAT(u.name, " ", COALESCE(u.mi, ""), " ", u.surname) AS uploader_full_name,
                    SUBSTRING_INDEX(df.file_name, ".", -1) AS file_extension
             '.$from.' WHERE '.$whereClause.'
             ORDER BY df.uploaded_at DESC
             LIMIT '.(int) $limit.' OFFSET '.(int) $offset,
            $params
        );

        foreach ($files as $f) {
            $f->file_size = (int) $f->file_size;
            $f->download_count = (int) $f->download_count;
            $f->icon = $this->fileIcon(strtolower($f->file_extension));
            $f->previewable = $this->isPreviewable(strtolower($f->file_extension ?? ''));
        }

        // Filter options ---------------------------------------------------
        $departments = $isSuperAdmin
            ? DB::table('departments')->where('is_active', 1)->orderBy('department_name')->get(['id', 'department_name', 'department_code'])
            : collect($this->resolveAdminDepartment($user) ? [DB::table('departments')->where('id', $this->resolveAdminDepartment($user))->first(['id', 'department_name', 'department_code'])] : []);

        $fileTypes = collect(DB::select(
            'SELECT DISTINCT df.file_type FROM document_files df
             LEFT JOIN users u ON df.uploaded_by = u.id
             WHERE df.file_type IS NOT NULL '
            .($isSuperAdmin ? '' : 'AND u.department_id = ? ')
            .'ORDER BY df.file_type',
            $isSuperAdmin ? [] : [$this->resolveAdminDepartment($user)]
        ))->pluck('file_type');

        $users = DB::table('users as u')
            ->leftJoin('departments as d', 'u.department_id', '=', 'd.id')
            ->where('u.is_approved', 1)
            ->when(! $isSuperAdmin && $this->resolveAdminDepartment($user), fn ($q) => $q->where('u.department_id', $this->resolveAdminDepartment($user)))
            ->orderBy('u.name')->orderBy('u.surname')
            ->get(['u.id', 'u.name', 'u.surname', 'u.mi', 'u.employee_id', 'u.position', 'd.department_code']);

        $academicYears = DB::table('document_files')
            ->distinct()->whereNotNull('academic_year')->orderByDesc('academic_year')->pluck('academic_year');
        $semesters = DB::table('document_files')
            ->distinct()->whereNotNull('semester_period')->orderBy('semester_period')->pluck('semester_period');

        // Stats -------------------------------------------------------------
        $stats = DB::selectOne(
            'SELECT COALESCE(SUM(df.file_size),0) AS total_size,
                    COUNT(DISTINCT df.uploaded_by) AS unique_uploaders
             '.$from.' WHERE '.$whereClause,
            $params
        );
        $todayUploads = DB::selectOne(
            'SELECT COUNT(*) AS c'.$from.' WHERE '.$whereClause.' AND DATE(df.uploaded_at) = CURDATE()',
            $params
        )->c;

        $this->activity->logRequest(
            $user->id,
            'view_files',
            'system',
            null,
            'Viewed All Files ('.$role.')'
        );

        return view($isSuperAdmin ? 'superadmin.files' : 'admin.files', [
            'currentUser' => $user,
            'files' => $files,
            'totalFiles' => $totalFiles,
            'totalPages' => $totalPages,
            'page' => $page,
            'limit' => $limit,
            'stats' => $stats,
            'todayUploads' => (int) $todayUploads,
            'departments' => $departments,
            'fileTypes' => $fileTypes,
            'users' => $users,
            'academicYears' => $academicYears,
            'semesters' => $semesters,
            'filters' => compact('search', 'departmentFilter', 'typeFilter', 'userFilter', 'dateFilter', 'yearFilter', 'semesterFilter'),
            'isSuperAdmin' => $isSuperAdmin,
        ]);
    }

    public function adminFiles(Request $request): \Illuminate\View\View
    {
        return $this->files($request, 'admin');
    }

    public function superadminFiles(Request $request): \Illuminate\View\View
    {
        return $this->files($request, 'superadmin');
    }

    /*
    |--------------------------------------------------------------------------
    | All Folders (admin + superadmin)
    |--------------------------------------------------------------------------
    */

    public function folders(Request $request, string $role): \Illuminate\View\View
    {
        $user = $request->user();

        $search = trim((string) $request->string('search'));
        if (mb_strlen($search) > 100) {
            $search = mb_substr($search, 0, 100);
        }
        $departmentFilter = trim((string) $request->string('department'));
        $typeFilter = trim((string) $request->string('folder_type'));
        $statusFilter = trim((string) $request->string('status'));

        if (! in_array($typeFilter, self::FOLDER_TYPES, true)) {
            $typeFilter = '';
        }
        if (! in_array($statusFilter, self::FOLDER_STATUS, true)) {
            $statusFilter = '';
        }

        $page = max(1, (int) $request->integer('page', 1));
        $limit = 20;
        $offset = ($page - 1) * $limit;

        $where = ['f.is_deleted = 0'];
        $params = [];

        if ($search !== '') {
            $where[] = '(f.folder_name LIKE ? OR f.description LIKE ?)';
            array_push($params, "%$search%", "%$search%");
        }
        if ($departmentFilter !== '') {
            $where[] = 'f.department_id = ?';
            $params[] = $departmentFilter;
        }
        if ($typeFilter !== '') {
            $where[] = 'f.folder_type = ?';
            $params[] = $typeFilter;
        }
        if ($statusFilter !== '') {
            $where[] = 'f.folder_status = ?';
            $params[] = $statusFilter;
        }

        $hasActiveFilters = $search !== '' || $departmentFilter !== '' || $typeFilter !== '' || $statusFilter !== '';

        $from = ' FROM folders f
                  LEFT JOIN departments d ON f.department_id = d.id
                  LEFT JOIN users u ON f.created_by = u.id';
        $whereClause = implode(' AND ', $where);

        $totalFolders = (int) DB::selectOne(
            'SELECT COUNT(*) AS c'.$from.' WHERE '.$whereClause,
            $params
        )->c;
        $totalPages = (int) ceil($totalFolders / $limit);
        if ($totalPages > 0 && $page > $totalPages) {
            $page = $totalPages;
            $offset = ($page - 1) * $limit;
        }

        $folders = DB::select(
            'SELECT f.*, d.department_name, d.department_code,
                    u.username, u.name AS creator_name, u.surname,
                    CONCAT(COALESCE(u.name, ""), " ", COALESCE(u.mi, ""), " ", COALESCE(u.surname, "")) AS creator_full_name,
                    pf.folder_name AS parent_folder_name
             '.$from.' LEFT JOIN folders pf ON f.parent_id = pf.id
             WHERE '.$whereClause.'
             ORDER BY f.created_at DESC
             LIMIT '.(int) $limit.' OFFSET '.(int) $offset,
            $params
        );

        foreach ($folders as $folder) {
            $folder->file_count = (int) ($folder->file_count ?? 0);
            $folder->folder_size = (int) ($folder->folder_size ?? 0);
            $folder->icon = $this->folderIconClass($folder->folder_icon ?? '');
            $folder->colour = $this->folderHexColor($folder->folder_color ?? '');
            $folder->status_class = in_array($folder->folder_status ?? 'active', self::FOLDER_STATUS, true) ? $folder->folder_status : 'active';
            $folder->status_label = ucfirst($folder->status_class);
        }

        $summary = DB::selectOne(
            'SELECT COUNT(*) AS total,
                    COALESCE(SUM(is_public), 0) AS public_count,
                    COALESCE(SUM(CASE WHEN is_public = 0 THEN 1 ELSE 0 END), 0) AS private_count,
                    COALESCE(SUM(file_count), 0) AS total_files,
                    COALESCE(SUM(folder_size), 0) AS total_size
             FROM folders f WHERE '.$whereClause,
            $params
        );

        $departments = DB::table('departments')->where('is_active', 1)->orderBy('department_name')->get(['id', 'department_name', 'department_code']);

        return view($user->isSuperAdmin() ? 'superadmin.folders' : 'admin.folders', [
            'currentUser' => $user,
            'folders' => $folders,
            'totalFolders' => $totalFolders,
            'totalPages' => $totalPages,
            'page' => $page,
            'limit' => $limit,
            'queryState' => compact('search', 'departmentFilter', 'typeFilter', 'statusFilter', 'page'),
            'hasActiveFilters' => $hasActiveFilters,
            'departments' => $departments,
            'summary' => $summary,
            'statuses' => self::FOLDER_STATUS,
            'types' => self::FOLDER_TYPES,
        ]);
    }

    public function adminFolders(Request $request): \Illuminate\View\View
    {
        return $this->folders($request, 'admin');
    }

    public function superadminFolders(Request $request): \Illuminate\View\View
    {
        return $this->folders($request, 'superadmin');
    }

    /*
    |--------------------------------------------------------------------------
    | Folder actions (POST -> redirect back, like legacy)
    |--------------------------------------------------------------------------
    */

    public function folderAction(Request $request): RedirectResponse
    {
        $action = (string) $request->string('action');
        $folderId = $request->integer('folder_id');

        $flashError = function (string $text) use ($request) {
            return redirect()->route(auth()->user()->isSuperAdmin() ? 'superadmin.folders' : 'admin.folders', [
                'search' => request()->string('search', '')->value(),
                'department' => request()->string('department', '')->value(),
                'folder_type' => request()->string('folder_type', '')->value(),
                'status' => request()->string('status', '')->value(),
                'page' => request()->integer('page', 1) > 1 ? request()->integer('page') : null,
            ])->with('error', $text);
        };

        if ($action === 'delete' && $folderId > 0) {
            $counts = DB::selectOne(
                'SELECT (SELECT COUNT(*) FROM files WHERE folder_id = ? AND is_deleted = 0) AS file_count,
                        (SELECT COUNT(*) FROM folders WHERE parent_id = ? AND is_deleted = 0) AS subfolder_count',
                [$folderId, $folderId]
            );

            if ((int) $counts->file_count > 0 || (int) $counts->subfolder_count > 0) {
                $parts = [];
                if ((int) $counts->file_count > 0) {
                    $parts[] = number_format((int) $counts->file_count).' file'.((int) $counts->file_count === 1 ? '' : 's');
                }
                if ((int) $counts->subfolder_count > 0) {
                    $parts[] = number_format((int) $counts->subfolder_count).' subfolder'.((int) $counts->subfolder_count === 1 ? '' : 's');
                }

                return $flashError('Cannot delete this folder - it still contains '.implode(' and ', $parts).'. Empty or move them first.');
            }

            DB::table('folders')
                ->where('id', $folderId)->where('is_deleted', 0)
                ->update(['is_deleted' => 1, 'deleted_at' => now(), 'deleted_by' => $request->user()->id]);

            return $this->backWith('success', 'Folder deleted.');
        }

        if ($action === 'toggle_public' && $folderId > 0) {
            DB::table('folders')->where('id', $folderId)->where('is_deleted', 0)->update(['is_public' => DB::raw('NOT is_public')]);
            $now = DB::table('folders')->where('id', $folderId)->value('is_public');

            return $this->backWith('success', 'Folder is now '.($now ? 'public' : 'private').'.');
        }

        if ($action === 'change_status' && $folderId > 0) {
            $status = trim((string) $request->string('status'));
            if (! in_array($status, self::FOLDER_STATUS, true)) {
                return $flashError('Unknown folder status.');
            }
            DB::table('folders')->where('id', $folderId)->where('is_deleted', 0)
                ->update(['folder_status' => $status]);

            return $this->backWith('success', 'Folder status set to '.$status.'.');
        }

        return $this->backWith('error', 'Unknown folder action.');
    }

    private function backWith(string $type, string $text): RedirectResponse
    {
        return redirect()->back()->with($type, $text);
    }

    /*
    |--------------------------------------------------------------------------
    | Shared with me (admin)
    |--------------------------------------------------------------------------
    */

    public function shared(Request $request): \Illuminate\View\View
    {
        $user = $request->user();

        $search = trim((string) $request->string('search'));

        // file_shares.file_id carries a FK to the `files` table (legacy DB schema:
        // CONSTRAINT file_shares_file_fk REFERENCES files(id)), so shared files are
        // uploaded workspace / department-folder files, NOT document_files rows.
        $query = DB::table('file_shares as s')
            ->join('files as f', 's.file_id', '=', 'f.id')
            ->leftJoin('users as sh', 's.shared_by', '=', 'sh.id')
            ->leftJoin('departments as d', 'sh.department_id', '=', 'd.id')
            ->where('s.is_active', 1)
            ->where(function ($q) use ($user) {
                $q->where('s.shared_with', $user->id)
                    ->orWhere('s.share_type', 'public')
                    ->orWhere(function ($dept) use ($user) {
                        $dept->where('s.share_type', 'department')
                            ->where('sh.department_id', $user->department_id);
                    });
            });

        if ($search !== '') {
            $term = '%'.$search.'%';
            $query->where(fn ($q) => $q->where('f.original_name', 'LIKE', $term)->orWhere('f.file_type', 'LIKE', $term));
        }

        $shares = $query
            ->select(
                's.*',
                'f.original_name AS file_name', 'f.file_path', 'f.file_size', 'f.file_type',
                'f.academic_year', 'f.semester AS semester_period', 'f.mime_type', 'f.description',
                'sh.name AS sharer_name', 'sh.surname AS sharer_surname', 'd.department_name'
            )
            ->orderByDesc('s.created_at')
            ->paginate(20);

        foreach ($shares as $share) {
            $share->file_size = (int) $share->file_size;
            $share->icon = $this->fileIcon(strtolower(pathinfo($share->file_name, PATHINFO_EXTENSION)));
        }

        return view('admin.shared', [
            'currentUser' => $user,
            'shares' => $shares,
            'search' => $search,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | User department folders
    |--------------------------------------------------------------------------
    */

    public function userFolders(Request $request): \Illuminate\View\View
    {
        $user = $request->user();
        $departmentId = (int) ($user->department_id ?? 0);

        if (! $departmentId) {
            return view('user.folders', [
                'currentUser' => $user,
                'noDepartment' => true,
                'department' => null,
                'categories' => collect(),
                'categoryCounts' => collect(),
                'customFolders' => collect(),
                'semesters' => collect(),
                'academicYears' => collect([date('Y').'-'.(date('Y') + 1)]),
                'shareUsers' => collect(),
            ]);
        }

        $department = DB::table('departments')->where('id', $departmentId)->where('is_active', 1)
            ->first(['id', 'department_name', 'department_code']);

        $categories = $this->departmentCategories($departmentId);

        // Counts of the user's own files per category (files inside category folders).
        $categoryRows = DB::table('files as f')
            ->join('folders as fo', 'f.folder_id', '=', 'fo.id')
            ->where('fo.department_id', $departmentId)
            ->where('f.uploaded_by', $user->id)
            ->where('f.is_deleted', 0)
            ->where('fo.is_deleted', 0)
            ->whereNotNull('fo.category')
            ->select('fo.category', DB::raw('COUNT(*) as c'))
            ->groupBy('fo.category')
            ->get()
            ->pluck('c', 'category');

        $categoryCounts = collect();
        foreach ($categories as $key => $cat) {
            $categoryCounts[$key] = (int) ($categoryRows[$key] ?? 0);
        }

        // Custom folders are the user's own parent-less non-category folders.
        $customFolders = DB::table('folders')
            ->where('department_id', $departmentId)
            ->where('created_by', $user->id)
            ->where('parent_id', null)
            ->whereNull('category')
            ->where('folder_type', 'custom')
            ->where('is_deleted', 0)
            ->orderByDesc('created_at')
            ->get();

        foreach ($customFolders as $folder) {
            $folder->file_count = $this->customFolderFileCount((int) $folder->id);
            $folder->icon = $this->folderIconClass($folder->folder_icon ?? 'fa-folder');
            $folder->colour = $this->folderHexColor($folder->folder_color ?? '#14532d');
        }
        $year = (int) date('Y');
        $semesters = collect([
            ['key' => 'first', 'label' => 'First Semester', 'value' => '1st Semester', 'year' => $year],
            ['key' => 'second', 'label' => 'Second Semester', 'value' => '2nd Semester', 'year' => $year],
        ]);

        $academicYears = DB::table('files')
            ->distinct()->whereNotNull('academic_year')->orderByDesc('academic_year')
            ->pluck('academic_year')
            ->filter(fn ($y) => preg_match('/^\d{4}-\d{4}$/', (string) $y))
            ->values();

        if (! $academicYears->contains($year.'-'.($year + 1))) {
            $academicYears = $academicYears->prepend($year.'-'.($year + 1));
        }

        return view('user.folders', [
            'currentUser' => $user,
            'noDepartment' => false,
            'department' => $department,
            'departmentId' => $departmentId,
            'categories' => $categories,
            'categoryCounts' => $categoryCounts,
            'customFolders' => $customFolders,
            'semesters' => $semesters,
            'academicYears' => $academicYears,
            'shareUsers' => DB::table('users as u')
                ->leftJoin('departments as d', 'u.department_id', '=', 'd.id')
                ->where('u.is_approved', 1)
                ->orderBy('u.name')->orderBy('u.surname')
                ->get(['u.id', 'u.name', 'u.mi', 'u.surname', 'd.department_code']),
        ]);
    }

    /**
     * Legacy fileCategories definition (roles/user/folders.php): the canonical
     * 16 categories. A category drops out of the list only when the department
     * carries a '__deleted_category__:<key>' tombstone folder.
     */
    private function departmentCategories(int $departmentId): \Illuminate\Support\Collection
    {
        $tombstones = DB::table('folders')
            ->where('department_id', $departmentId)
            ->whereNull('parent_id')
            ->where('is_deleted', 0)
            ->whereNotNull('category')
            ->where('folder_type', 'category')
            ->whereRaw('folder_name = CONCAT("__deleted_category__:", category)')
            ->pluck('category')
            ->map(fn ($cat) => (string) $cat)
            ->all();

        $cats = [];
        foreach ($this->fileCategories() as $key => $category) {
            if (! in_array($key, $tombstones, true)) {
                $cats[$key] = $category;
            }
        }

        return collect($cats);
    }

    /** The 16 canonical categories, matching roles/user/folders.php exactly. */
    private function fileCategories(): array
    {
        return [
            'ipcr_accomplishment' => ['name' => 'IPCR Accomplishment', 'icon' => 'bxs-trophy', 'color' => '#f59e0b'],
            'ipcr_target' => ['name' => 'IPCR Target', 'icon' => 'bxs-bullseye', 'color' => '#ef4444'],
            'workload' => ['name' => 'Workload', 'icon' => 'bxs-briefcase', 'color' => '#8b5cf6'],
            'course_syllabus' => ['name' => 'Course Syllabus', 'icon' => 'bxs-book-content', 'color' => '#06b6d4'],
            'syllabus_acceptance' => ['name' => 'Course Syllabus Acceptance Form', 'icon' => 'bxs-check-circle', 'color' => '#10b981'],
            'exam' => ['name' => 'Exam', 'icon' => 'bxs-file-doc', 'color' => '#dc2626'],
            'tos' => ['name' => 'TOS', 'icon' => 'bxs-spreadsheet', 'color' => '#059669'],
            'class_record' => ['name' => 'Class Record', 'icon' => 'bxs-data', 'color' => '#7c3aed'],
            'grading_sheet' => ['name' => 'Grading Sheet', 'icon' => 'bxs-calculator', 'color' => '#ea580c'],
            'attendance_sheet' => ['name' => 'Attendance Sheet', 'icon' => 'bxs-user-check', 'color' => '#0284c7'],
            'stakeholder_feedback' => ['name' => "Stakeholder's Feedback Form w/ Summary", 'icon' => 'bxs-comment-detail', 'color' => '#9333ea'],
            'consultation' => ['name' => 'Consultation', 'icon' => 'bxs-chat', 'color' => '#0d9488'],
            'lecture' => ['name' => 'Lecture', 'icon' => 'bxs-chalkboard', 'color' => '#7c2d12'],
            'activities' => ['name' => 'Activities', 'icon' => 'bxs-game', 'color' => '#be185d'],
            'consultation_log' => ['name' => 'Consultation Log Sheet Form', 'icon' => 'bxs-notepad', 'color' => '#374151'],
            'exam_acknowledgement' => ['name' => 'CEIT-QF-03 Discussion of Examination Acknowledgement Receipt Form', 'icon' => 'bxs-receipt', 'color' => '#1e40af'],
        ];
    }

    /*
    |--------------------------------------------------------------------------
    | Uploads
    |--------------------------------------------------------------------------
    */

    public function storeUpload(Request $request): JsonResponse
    {
        $user = $request->user();

        try {
            $category = trim((string) $request->string('category'));
            $semester = strtolower(trim((string) $request->string('semester', 'first')));
            $academicYear = trim((string) $request->string('academic_year', date('Y').'-'.(date('Y') + 1)));
            $description = trim((string) $request->string('description'));
            $customFolderId = $request->integer('custom_folder_id');

            // Normalise the semester to the files.semester enum value.
            $semester = app(\App\Services\DocumentPeriodHelper::class)->semesterColumn($semester);

            if (! preg_match('/^\d{4}-\d{4}$/', $academicYear)) {
                throw new \RuntimeException('Invalid academic year');
            }
            if (! $user->department_id) {
                throw new \RuntimeException('No department assigned - contact the administrator');
            }

            $categoryKeys = array_keys($this->fileCategories());
            if ($customFolderId <= 0 && ! in_array($category, $categoryKeys, true)) {
                throw new \RuntimeException('Select a document category');
            }

            $files = $request->file('file');
            if (! $files) {
                $files = $request->file('files');
            }
            if (! $files) {
                throw new \RuntimeException('No file uploaded or upload error occurred');
            }
            $files = is_array($files) ? $files : [$files];
            $files = array_values(array_filter($files));

            $semesterName = $semester === 'first' ? 'First Semester' : 'Second Semester';
            $departmentId = (int) $user->department_id;

            // Resolve the destination folder (category semester folder or custom semester folder).
            if ($customFolderId > 0) {
                $customFolder = DB::table('folders')
                    ->where('id', $customFolderId)
                    ->where('department_id', $departmentId)
                    ->whereNull('category')->whereNull('parent_id')
                    ->where('is_deleted', 0)
                    ->where(fn ($q) => $q->where('is_public', 1)->orWhere('created_by', $user->id))
                    ->first();

                if (! $customFolder) {
                    throw new \RuntimeException('The selected custom folder is no longer available');
                }

                $folderId = DB::table('folders')
                    ->where('parent_id', $customFolderId)
                    ->where('department_id', $departmentId)
                    ->where('folder_name', $semesterName)
                    ->whereNull('category')
                    ->where('is_deleted', 0)
                    ->value('id');

                if (! $folderId) {
                    $folderId = DB::table('folders')->insertGetId([
                        'folder_name' => $semesterName,
                        'description' => "{$semesterName} files for {$customFolder->folder_name}",
                        'created_by' => $customFolder->created_by,
                        'department_id' => $departmentId,
                        'category' => null,
                        'folder_path' => rtrim((string) $customFolder->folder_path, '/').'/'.$semester,
                        'folder_level' => (int) $customFolder->folder_level + 1,
                        'folder_color' => $customFolder->folder_color,
                        'folder_icon' => $customFolder->folder_icon,
                        'parent_id' => $customFolderId,
                        'is_public' => (int) $customFolder->is_public,
                        'folder_type' => 'custom',
                        'folder_status' => 'active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $uploadDir = storage_path('app/private/uploads/departments/'.$departmentId.'/custom/'.$customFolderId.'/'.$semester.'/'.$academicYear);
            } else {
                $folderName = $academicYear.' - '.$semesterName;
                $folderId = DB::table('folders')
                    ->where('department_id', $departmentId)
                    ->where('category', $category)
                    ->where('folder_name', $folderName)
                    ->where('is_deleted', 0)
                    ->value('id');

                if (! $folderId) {
                    $folderId = DB::table('folders')->insertGetId([
                        'folder_name' => $folderName,
                        'description' => "Academic files for {$semesterName} {$academicYear}",
                        'created_by' => $user->id,
                        'department_id' => $departmentId,
                        'category' => $category,
                        'folder_path' => "/departments/{$departmentId}/{$category}/{$semester}/{$academicYear}",
                        'folder_level' => 2,
                        'folder_color' => '#10b981',
                        'folder_icon' => 'bxs-folder',
                        'folder_type' => 'category',
                        'folder_status' => 'active',
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                $uploadDir = storage_path('app/private/uploads/departments/'.$departmentId.'/'.$category.'/'.$semester.'/'.$academicYear);
            }

            if (! is_dir($uploadDir) && ! @mkdir($uploadDir, 0755, true) && ! is_dir($uploadDir)) {
                throw new \RuntimeException('The server could not prepare the upload directory');
            }

            $saved = [];
            $allowed = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'jpg', 'jpeg', 'png', 'gif', 'txt', 'zip', 'rar'];

            foreach ($files as $file) {
                $original = $file->getClientOriginalName();
                $extension = strtolower($file->getClientOriginalExtension());
                $size = $file->getSize();

                if ($size > 50 * 1024 * 1024) {
                    throw new \RuntimeException('File size exceeds 50MB limit: '.$original);
                }
                if (! in_array($extension, $allowed, true)) {
                    throw new \RuntimeException('File type not allowed: '.$original);
                }

                $extension = $extension === 'jpeg' ? 'jpg' : $extension;
                $storedName = bin2hex(random_bytes(16)).'.'.$extension;

                $file->move($uploadDir, $storedName);

                $relativePath = ltrim(substr(
                    ($customFolderId > 0
                        ? storage_path('app/private/uploads/departments/'.$departmentId.'/custom/'.$customFolderId.'/'.$semester.'/'.$academicYear.'/'.$storedName)
                        : storage_path('app/private/uploads/departments/'.$departmentId.'/'.$category.'/'.$semester.'/'.$academicYear.'/'.$storedName)),
                    strlen(storage_path('app/private'))
                ), '/\\');

                $fileId = DB::table('files')->insertGetId([
                    'file_name' => $storedName,
                    'original_name' => $original,
                    'file_path' => $relativePath,
                    'file_size' => $size,
                    'file_type' => 'document',
                    'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                    'file_extension' => $extension,
                    'uploaded_by' => $user->id,
                    'folder_id' => (int) $folderId,
                    'tags' => null,
                    'description' => $description ?: null,
                    'academic_year' => $academicYear,
                    'semester' => $semester,
                    'download_count' => 0,
                    'uploaded_at' => now(),
                    'updated_at' => now(),
                ]);

                $saved[] = [
                    'file_id' => $fileId,
                    'file_name' => $original,
                    'file_size' => $size,
                    'category' => $customFolderId > 0 ? 'custom' : $category,
                    'semester' => $semesterName,
                    'academic_year' => $academicYear,
                ];
            }

            $this->activity->logRequest($user->id, 'upload', 'file', $saved[0]['file_id'] ?? null, 'Uploaded '.count($saved).' file(s) to '.($customFolderId > 0 ? 'a custom folder' : $category), ['semester' => $semester, 'academic_year' => $academicYear]);

            return response()->json(['success' => true, 'message' => 'File uploaded successfully', 'files' => $saved]);
        } catch (Throwable $e) {
            return response()->json(['success' => false, 'message' => $e->getMessage()], 400);
        }
    }

    public function getCategoryFiles(Request $request): JsonResponse
    {
        $user = $request->user();
        $departmentId = $request->integer('department_id');
        $category = (string) $request->string('category');

        // Security: users can only view files belonging to their own department.
        if ($departmentId !== (int) ($user->department_id ?? 0)) {
            return response()->json(['success' => false, 'message' => 'Access denied: Can only view files from your department'], 403);
        }

        if (! array_key_exists($category, $this->fileCategories())) {
            return response()->json(['success' => false, 'message' => 'Unknown category'], 422);
        }

        $tombstoned = DB::table('folders')
            ->where('department_id', $departmentId)
            ->whereNull('parent_id')
            ->where('folder_type', 'category')
            ->where('is_deleted', 0)
            ->whereRaw('folder_name = CONCAT("__deleted_category__:", category)')
            ->where('category', $category)
            ->exists();

        if ($tombstoned) {
            return response()->json(['success' => false, 'message' => 'This department folder has been deleted.']);
        }

        $rows = DB::table('files as f')
            ->join('folders as fo', 'f.folder_id', '=', 'fo.id')
            ->leftJoin('users as u', 'f.uploaded_by', '=', 'u.id')
            ->where('fo.department_id', $departmentId)
            ->where('fo.category', $category)
            ->where('f.uploaded_by', $user->id)
            ->where('f.is_deleted', 0)
            ->where('fo.is_deleted', 0)
            ->get([
                'f.id', 'f.file_name', 'f.original_name', 'f.file_path', 'f.file_size',
                'f.file_type', 'f.mime_type', 'f.file_extension',
                'f.semester', 'f.academic_year', 'f.description', 'f.tags',
                'f.download_count', 'f.uploaded_at',
                DB::raw("COALESCE(CONCAT(u.name, ' ', COALESCE(u.mi, ''), ' ', u.surname), u.username, 'Unknown User') as uploaded_by_name"),
            ]);

        $first = collect();
        $second = collect();

        foreach ($rows as $f) {
            $f->file_size = (int) $f->file_size;
            $f->download_count = (int) $f->download_count;
            $f->icon = $this->fileIcon(strtolower($f->file_extension ?? pathinfo($f->file_name, PATHINFO_EXTENSION)));

            if ($f->semester === 'second') {
                $second->push($f);
            } else {
                $first->push($f);
            }
        }

        $first = $first->sortByDesc('uploaded_at')->values();
        $second = $second->sortByDesc('uploaded_at')->values();

        $createdAt = [
            'first' => DB::table('folders')
                ->where('department_id', $departmentId)->where('category', $category)->where('is_deleted', 0)
                ->where('folder_name', 'LIKE', '%First Semester%')->min('created_at'),
            'second' => DB::table('folders')
                ->where('department_id', $departmentId)->where('category', $category)->where('is_deleted', 0)
                ->where('folder_name', 'LIKE', '%Second Semester%')->min('created_at'),
        ];

        return response()->json([
            'success' => true,
            'total_files' => $first->count() + $second->count(),
            'created_at' => $createdAt,
            'first_semester' => $first,
            'second_semester' => $second,
        ]);
    }

    /*
    |--------------------------------------------------------------------------
    | Category / custom folders AJAX (user department folders page)
    |--------------------------------------------------------------------------
    */

    public function categoryCounts(Request $request): JsonResponse
    {
        $user = $request->user();
        $departmentId = $request->integer('department_id', (int) $user->department_id);

        $perCategory = DB::table('files as f')
            ->join('folders as fo', 'f.folder_id', '=', 'fo.id')
            ->where('fo.department_id', $departmentId)
            ->where('f.uploaded_by', $user->id)
            ->where('f.is_deleted', 0)
            ->where('fo.is_deleted', 0)
            ->whereNotNull('fo.category')
            ->pluck('fo.category')
            ->countBy();

        $counts = [];
        foreach ($this->fileCategories() as $key => $cat) {
            $counts[$key] = $perCategory[$key] ?? 0;
        }

        return response()->json(['success' => true, 'counts' => $counts]);
    }

    public function createCustomFolder(Request $request): JsonResponse
    {
        $user = $request->user();
        $name = trim((string) $request->string('folder_name'));
        $description = trim((string) $request->string('folder_description'));
        $colour = $this->folderHexColor((string) $request->string('folder_color', '#14532d'));
        $icon = preg_match('/^bxs?-[a-z0-9-]+$/', (string) $request->string('folder_icon', 'bxs-folder')) ? $request->string('folder_icon', 'bxs-folder')->value() : 'bxs-folder';

        if ($name === '' || mb_strlen($name) > 100) {
            return response()->json(['success' => false, 'message' => 'Folder name is required (max 100 characters)'], 422);
        }

        $departmentId = $user->department_id;
        if (! $departmentId) {
            return response()->json(['success' => false, 'message' => 'No department assigned'], 422);
        }

        $exists = DB::table('folders')
            ->where('department_id', $departmentId)
            ->where('created_by', $user->id)
            ->where('folder_name', $name)
            ->whereNull('parent_id')
            ->whereNull('category')
            ->where('is_deleted', 0)
            ->exists();

        if ($exists) {
            return response()->json(['success' => false, 'message' => 'A folder with that name already exists'], 422);
        }

        DB::table('folders')->insert([
            'folder_name' => $name,
            'description' => $description ?: null,
            'created_by' => $user->id,
            'department_id' => $departmentId,
            'folder_path' => '/departments/'.$departmentId.'/custom/'.$name,
            'folder_level' => 1,
            'folder_type' => 'custom',
            'folder_color' => $colour,
            'folder_icon' => $icon,
            'folder_status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return response()->json(['success' => true, 'message' => 'Folder created successfully']);
    }

    public function customFolders(Request $request): JsonResponse
    {
        $user = $request->user();
        $folderId = $request->integer('folder_id');
        $contents = $request->boolean('contents');

        $folders = DB::table('folders')
            ->where('department_id', $user->department_id)
            ->where('created_by', $user->id)
            ->whereNull('parent_id')
            ->whereNull('category')
            ->where('folder_type', 'custom')
            ->where('is_deleted', 0)
            ->orderByDesc('created_at')
            ->get();

        foreach ($folders as $folder) {
            $folder->file_count = $this->customFolderFileCount((int) $folder->id);
            $folder->icon = $this->folderIconClass($folder->folder_icon ?? 'fa-folder');
            $folder->colour = $this->folderHexColor($folder->folder_color ?? '#14532d');
        }

        $payload = ['success' => true, 'folders' => $folders];

        if ($contents && $folderId > 0) {
            $payload['contents'] = DB::table('files as f')
                ->leftJoin('users as u', 'f.uploaded_by', '=', 'u.id')
                ->where('f.folder_id', $folderId)
                ->where('f.is_deleted', 0)
                ->orderByDesc('f.uploaded_at')
                ->get([
                    'f.id', 'f.file_name', 'f.original_name', 'f.file_path', 'f.file_size',
                    'f.mime_type', 'f.file_extension', 'f.uploaded_at', 'f.description',
                    'f.academic_year', 'f.semester', 'f.download_count',
                    'u.name', 'u.surname',
                ]);
        }

        return response()->json($payload);
    }

    public function customFolderContents(Request $request): JsonResponse
    {
        $user = $request->user();
        $folderId = $request->integer('folder_id');

        $folder = DB::table('folders')
            ->where('id', $folderId)
            ->where('department_id', $user->department_id)
            ->where('is_deleted', 0)
            ->where(fn ($q) => $q->where('created_by', $user->id)->orWhere('is_public', 1))
            ->first();

        if (! $folder) {
            return response()->json(['success' => false, 'message' => 'Folder not found'], 404);
        }

        $childIds = $this->customFolderChildIds($folderId);

        $files = DB::table('files as f')
            ->leftJoin('users as u', 'f.uploaded_by', '=', 'u.id')
            ->where('f.is_deleted', 0)
            ->where(function ($q) use ($folderId, $childIds) {
                $q->where('f.folder_id', $folderId);
                if ($childIds) {
                    $q->orWhereIn('f.folder_id', $childIds);
                }
            })
            ->orderByDesc('f.uploaded_at')
            ->get([
                'f.id', 'f.file_name', 'f.original_name', 'f.file_path', 'f.file_size',
                'f.mime_type', 'f.file_extension', 'f.uploaded_at', 'f.description',
                'f.academic_year', 'f.semester', 'f.download_count',
                'u.name', 'u.surname',
            ]);

        foreach ($files as $f) {
            $f->file_size = (int) $f->file_size;
            $f->download_count = (int) $f->download_count;
            $f->icon = $this->fileIcon(strtolower($f->file_extension ?? pathinfo($f->file_name, PATHINFO_EXTENSION)));
        }

        return response()->json(['success' => true, 'files' => $files]);
    }

    public function deleteCustomFolder(Request $request): JsonResponse
    {
        $user = $request->user();
        $folderId = $request->integer('folder_id');
        $force = $request->boolean('force_delete');

        $folder = DB::table('folders')
            ->where('id', $folderId)
            ->where('created_by', $user->id)
            ->whereNull('category')
            ->where('is_deleted', 0)
            ->first();

        if (! $folder) {
            return response()->json(['success' => false, 'message' => 'Folder not found'], 404);
        }

        $childIds = $this->customFolderChildIds($folderId);

        $fileCount = $this->customFolderFileCount($folderId);
        if ($fileCount > 0 && ! $force) {
            return response()->json(['success' => false, 'message' => 'This folder contains '.$fileCount.' file(s). Delete anyway?', 'confirm' => true]);
        }

        if ($force || $fileCount > 0) {
            $scope = DB::table('files')->where('is_deleted', 0);
            $scope->where(function ($q) use ($folderId, $childIds) {
                $q->where('folder_id', $folderId);
                if ($childIds) {
                    $q->orWhereIn('folder_id', $childIds);
                }
            });
            $scope->update(['is_deleted' => 1, 'deleted_at' => now(), 'deleted_by' => $user->id]);

            if ($childIds) {
                DB::table('folders')->whereIn('id', $childIds)
                    ->update(['is_deleted' => 1, 'deleted_at' => now(), 'deleted_by' => $user->id]);
            }
        }

        DB::table('folders')->where('id', $folderId)->update(['is_deleted' => 1, 'deleted_at' => now(), 'deleted_by' => $user->id]);

        return response()->json(['success' => true, 'message' => 'Folder deleted']);
    }

    public function folderManagement(Request $request): JsonResponse
    {
        $user = $request->user();
        $action = (string) $request->string('action');
        $folderId = $request->integer('folder_id');

        $folder = DB::table('folders')->where('id', $folderId)
            ->where('created_by', $user->id)
            ->whereNull('category')->where('is_deleted', 0)->first();
        if (! $folder) {
            return response()->json(['success' => false, 'message' => 'Folder not found'], 404);
        }

        switch ($action) {
            case 'rename':
                $newName = trim((string) $request->string('new_name'));
                if ($newName === '' || mb_strlen($newName) > 100) {
                    return response()->json(['success' => false, 'message' => 'Invalid folder name'], 422);
                }
                DB::table('folders')->where('id', $folderId)->update(['folder_name' => $newName, 'updated_at' => now()]);
                break;

            case 'toggle_favorite':
                DB::table('folders')->where('id', $folderId)->update(['is_favorite' => DB::raw('NOT is_favorite'), 'updated_at' => now()]);
                break;

            case 'delete':
                $force = $request->boolean('force_delete');
                $childIds = $this->customFolderChildIds($folderId);
                $fileCount = $this->customFolderFileCount($folderId);
                if ($fileCount > 0 && ! $force) {
                    return response()->json(['success' => false, 'message' => 'This folder contains '.$fileCount.' file(s). Delete anyway?', 'confirm' => true]);
                }
                if ($fileCount > 0) {
                    $scope = DB::table('files')->where('is_deleted', 0);
                    $scope->where(function ($q) use ($folderId, $childIds) {
                        $q->where('folder_id', $folderId);
                        if ($childIds) {
                            $q->orWhereIn('folder_id', $childIds);
                        }
                    });
                    $scope->update(['is_deleted' => 1, 'deleted_at' => now(), 'deleted_by' => $user->id]);

                    if ($childIds) {
                        DB::table('folders')->whereIn('id', $childIds)
                            ->update(['is_deleted' => 1, 'deleted_at' => now(), 'deleted_by' => $user->id]);
                    }
                }
                DB::table('folders')->where('id', $folderId)->update(['is_deleted' => 1, 'deleted_at' => now(), 'deleted_by' => $user->id]);
                break;

            case 'access':
                DB::table('folders')->where('id', $folderId)->increment('access_count');
                break;

            default:
                return response()->json(['success' => false, 'message' => 'Unknown action'], 422);
        }

        return response()->json(['success' => true, 'message' => 'Folder updated']);
    }

    /*
    |--------------------------------------------------------------------------
    | File actions
    |--------------------------------------------------------------------------
    */

    public function deleteFile(Request $request): JsonResponse
    {
        $user = $request->user();
        $fileId = $request->integer('file_id');

        $file = DB::table('document_files')->where('id', $fileId)->first();
        if (! $file) {
            return response()->json(['success' => false, 'message' => 'File not found'], 404);
        }

        if (! $this->canManageDocumentFile($user, $file)) {
            return response()->json(['success' => false, 'message' => "You don't have permission to perform this action"], 403);
        }

        $path = $this->resolveFilePath($file->file_path);
        if ($path && is_file($path)) {
            @unlink($path);
        }

        DB::table('document_files')->where('id', $fileId)->delete();

        $this->activity->logRequest($user->id, 'delete', 'file', $fileId, 'Deleted file: '.$file->file_name);

        return response()->json(['success' => true, 'message' => 'File deleted successfully']);
    }

    public function updateFile(Request $request): JsonResponse
    {
        $user = $request->user();
        $fileId = $request->integer('file_id');
        $description = trim((string) $request->string('description'));

        $file = DB::table('document_files')->where('id', $fileId)->first();
        if (! $file) {
            return response()->json(['success' => false, 'message' => 'File not found'], 404);
        }

        if (! $this->canManageDocumentFile($user, $file)) {
            return response()->json(['success' => false, 'message' => "You don't have permission to perform this action"], 403);
        }

        DB::table('document_files')->where('id', $fileId)->update(['description' => $description ?: null]);

        return response()->json(['success' => true, 'message' => 'File description updated']);
    }

    public function downloadFile(Request $request, int $fileId): BinaryFileResponse
    {
        $file = DB::table('document_files')->where('id', $fileId)->first();

        if (! $file) {
            abort(404, 'File not found');
        }

        $path = $this->resolveFilePath($file->file_path);
        if (! $path || ! is_file($path)) {
            abort(404, 'The physical file is no longer available on the server');
        }

        header('X-Accel-Buffering: no');

        DB::table('document_files')->where('id', $fileId)->increment('download_count');

        return response()->download($path, $file->file_name);
    }

    public function previewFile(Request $request, int $fileId): BinaryFileResponse
    {
        $file = DB::table('document_files')->where('id', $fileId)->first();
        if (! $file) {
            abort(404, 'File not found');
        }

        $path = $this->resolveFilePath($file->file_path);
        if (! $path || ! is_file($path)) {
            abort(404, 'The physical file is no longer available on the server');
        }

        $mime = $file->mime_type ?: mime_content_type($path);

        return response()->file($path, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.rawurlencode($file->file_name).'"',
        ]);
    }

    /**
     * Shares an uploaded workspace file (the `files` table) to a user / department / public.
     *
     * Mirrors the legacy file sharing model: file_shares.file_id has a FK to files(id),
     * so only `files`-table rows can be shared (department folder / workspace uploads).
     */
    public function shareFile(Request $request): JsonResponse
    {
        $user = $request->user();
        $fileId = $request->integer('file_id');
        $sharedWith = $request->integer('shared_with');
        $shareType = in_array($request->string('share_type', 'user')->value(), ['user', 'public', 'department'], true) ? $request->string('share_type')->value() : 'user';

        $file = DB::table('files')
            ->where('id', $fileId)
            ->where('is_deleted', 0)
            ->first();

        if (! $file) {
            return response()->json(['success' => false, 'message' => 'File not found'], 404);
        }

        // Permission: superadmins share anything; otherwise only the uploader.
        if ($user->role !== 'super_admin' && (int) $file->uploaded_by !== (int) $user->id) {
            return response()->json(['success' => false, 'message' => 'You can only share files you uploaded'], 403);
        }

        if ($shareType === 'user' && $sharedWith <= 0) {
            return response()->json(['success' => false, 'message' => 'Select a user to share with'], 422);
        }

        DB::table('file_shares')->insert([
            'file_id' => $fileId,
            'shared_by' => $user->id,
            'shared_with' => $shareType === 'user' ? $sharedWith : null,
            'share_token' => bin2hex(random_bytes(16)),
            'share_type' => $shareType,
            'is_active' => 1,
            'created_at' => now(),
        ]);

        $this->activity->logRequest(
            $user->id,
            'share',
            'file',
            $fileId,
            'Shared file: '.($file->original_name ?: $file->file_name).' ('.$shareType.')'
        );

        return response()->json(['success' => true, 'message' => 'File shared successfully']);
    }

    /*
    |--------------------------------------------------------------------------
    | Workspace files (department folders / trackers read the `files` table)
    |--------------------------------------------------------------------------
    */

    public function workspaceDownload(Request $request, int $fileId): BinaryFileResponse
    {
        $file = DB::table('files')->where('id', $fileId)->where('is_deleted', 0)->first();

        if (! $file) {
            abort(404, 'File not found');
        }

        $path = $this->resolveFilePath($file->file_path);
        if (! $path || ! is_file($path)) {
            abort(404, 'The physical file is no longer available on the server');
        }

        DB::table('files')->where('id', $fileId)->increment('download_count');

        return response()->download($path, $file->original_name ?: $file->file_name);
    }

    public function workspacePreview(Request $request, int $fileId): BinaryFileResponse
    {
        $file = DB::table('files')->where('id', $fileId)->where('is_deleted', 0)->first();
        if (! $file) {
            abort(404, 'File not found');
        }

        $path = $this->resolveFilePath($file->file_path);
        if (! $path || ! is_file($path)) {
            abort(404, 'The physical file is no longer available on the server');
        }

        $mime = $file->mime_type ?: mime_content_type($path);

        return response()->file($path, [
            'Content-Type' => $mime,
            'Content-Disposition' => 'inline; filename="'.rawurlencode($file->original_name ?: $file->file_name).'"',
        ]);
    }

    public function deleteWorkspaceFile(Request $request): JsonResponse
    {
        $user = $request->user();
        $fileId = $request->integer('file_id');

        $file = DB::table('files')
            ->where('id', $fileId)
            ->where('uploaded_by', $user->id)
            ->where('is_deleted', 0)
            ->first();

        if (! $file) {
            return response()->json(['success' => false, 'message' => 'File not found'], 404);
        }

        $path = $this->resolveFilePath($file->file_path);
        if ($path && is_file($path)) {
            @unlink($path);
        }

        DB::table('files')->where('id', $fileId)->update(['is_deleted' => 1, 'deleted_at' => now(), 'deleted_by' => $user->id]);

        return response()->json(['success' => true, 'message' => 'File deleted successfully']);
    }

    /*
    |--------------------------------------------------------------------------
    | Helpers
    |--------------------------------------------------------------------------
    */

    /** File count for a custom folder including its semester subfolders. */
    private function customFolderFileCount(int $folderId): int
    {
        return (int) DB::table('files as f')
            ->leftJoin('folders as fo', 'f.folder_id', '=', 'fo.id')
            ->where('f.is_deleted', 0)
            ->where(function ($q) use ($folderId) {
                $q->where('f.folder_id', $folderId)
                    ->orWhere('fo.parent_id', $folderId);
            })
            ->count();
    }

    /** All (non-deleted) child folder ids of a custom folder. */
    private function customFolderChildIds(int $folderId): array
    {
        return DB::table('folders')
            ->where('parent_id', $folderId)
            ->where('is_deleted', 0)
            ->pluck('id')
            ->all();
    }

    private function resolveAdminDepartment(User $user): ?int
    {
        if ($user->department_id) {
            return (int) $user->department_id;
        }

        $result = DB::selectOne("SELECT department_id FROM users WHERE id = ? AND role = 'admin'", [$user->id]);

        return $result && $result->department_id ? (int) $result->department_id : null;
    }

    private function canManageDocumentFile(User $user, object $file): bool
    {
        if ($user->isSuperAdmin()) {
            return true;
        }

        $uploaderDept = DB::table('users')->where('id', $file->uploaded_by)->value('department_id');
        $deptId = $this->resolveAdminDepartment($user);

        return $deptId !== null && (int) $uploaderDept === $deptId;
    }

    /**
     * Map stored file paths to physical files on disk.
     * New uploads live under storage/app/private/uploads; legacy uploads live
     * under the read-only legacy root (uploads/...).
     */
    public function resolveFilePath(string $dbPath): ?string
    {
        $clean = ltrim((string) preg_replace('#^(\.\./)+#', '', $dbPath), '/\\');
        $candidates = [
            storage_path('app/private/'.str_replace('/', DIRECTORY_SEPARATOR, $clean)),
            storage_path('app/'.str_replace('/', DIRECTORY_SEPARATOR, $clean)),
            config('odci.legacy_root').DIRECTORY_SEPARATOR.str_replace('/', DIRECTORY_SEPARATOR, $clean),
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function folderIconClass(?string $stored): string
    {
        $stored = strtolower(trim((string) $stored));

        if (preg_match('/^bxs?-[a-z0-9-]+$/', $stored)) {
            return $stored;
        }

        $map = [
            'fa-folder' => 'bxs-folder', 'fa-folder-open' => 'bxs-folder-open',
            'fa-folder-plus' => 'bxs-folder-plus', 'fa-chart-line' => 'bxs-chart',
            'fa-chart-bar' => 'bxs-chart', 'fa-line-chart' => 'bxs-chart',
            'fa-file' => 'bxs-file', 'fa-file-alt' => 'bxs-file-doc',
            'fa-file-lines' => 'bxs-file-doc', 'fa-file-signature' => 'bxs-file-doc',
            'fa-file-invoice' => 'bxs-file-doc', 'fa-clock' => 'bxs-time',
            'fa-users' => 'bxs-group', 'fa-user' => 'bxs-user',
            'fa-book' => 'bxs-book', 'fa-graduation-cap' => 'bxs-magic-hat',
            'fa-award' => 'bxs-award', 'fa-medal' => 'bxs-medal',
            'fa-table' => 'bxs-spreadsheet', 'fa-th' => 'bxs-grid',
            'fa-th-large' => 'bxs-grid', 'fa-calendar' => 'bxs-calendar',
            'fa-archive' => 'bxs-archive', 'fa-clipboard' => 'bxs-clipboard',
            'fa-sticky-note' => 'bxs-note', 'fa-envelope' => 'bxs-envelope',
            'fa-paperclip' => 'bxs-attachment', 'fa-star' => 'bxs-star',
            'fa-heart' => 'bxs-heart', 'fa-bookmark' => 'bxs-bookmark',
            'fa-tag' => 'bxs-tag', 'fa-cog' => 'bx-cog',
            'fa-wrench' => 'bxs-wrench', 'fa-cube' => 'bxs-cube',
            'fa-home' => 'bxs-home', 'fa-building' => 'bxs-building',
            'fa-briefcase' => 'bxs-briefcase', 'fa-image' => 'bxs-image',
            'fa-camera' => 'bxs-camera', 'fa-video' => 'bxs-video',
            'fa-music' => 'bxs-music',
        ];

        return $map[$stored] ?? 'bxs-folder';
    }

    private function folderHexColor(?string $stored, string $fallback = '#0f6b3d'): string
    {
        $stored = trim((string) $stored);

        return preg_match('/^#[0-9a-fA-F]{6}$/', $stored) ? $stored : $fallback;
    }

    private function fileIcon(string $ext): string
    {
        $map = [
            'pdf' => 'bxs-file-pdf', 'doc' => 'bxs-file-doc', 'docx' => 'bxs-file-doc',
            'xls' => 'bxs-spreadsheet', 'xlsx' => 'bxs-spreadsheet',
            'ppt' => 'bxs-file-blank', 'pptx' => 'bxs-file-blank',
            'jpg' => 'bxs-file-image', 'jpeg' => 'bxs-file-image', 'png' => 'bxs-file-image',
            'gif' => 'bxs-file-image', 'txt' => 'bxs-file-txt',
            'zip' => 'bxs-file-archive', 'rar' => 'bxs-file-archive',
            'mp4' => 'bxs-videos', 'avi' => 'bxs-videos', 'mp3' => 'bxs-music', 'wav' => 'bxs-music',
        ];

        return $map[$ext] ?? 'bxs-file';
    }

    private function isPreviewable(string $ext): bool
    {
        return in_array($ext, ['pdf', 'jpg', 'jpeg', 'png', 'gif', 'txt', 'mp4', 'webm', 'mp3'], true);
    }
}