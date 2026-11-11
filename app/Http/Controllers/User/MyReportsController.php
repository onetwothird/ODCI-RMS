<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * User reports module.
 *
 * Legacy equivalent: roles/user/my_reports.php
 *   GET /user/my-reports          -> filterable report tables
 *   GET /user/my-reports/export   -> CSV export of the active report
 */
class MyReportsController extends Controller
{
    private const ALLOWED_TYPES = ['file_uploads', 'document_submissions', 'activity_summary', 'storage_usage'];

    public function index(Request $request): \Illuminate\View\View
    {
        $user = $request->user();

        $type = (string) $request->string('type', 'file_uploads');
        if (! in_array($type, self::ALLOWED_TYPES, true)) {
            $type = 'file_uploads';
        }

        // Defaults to "All Years" so the report is never blank on first load.
        $academicYear = (string) $request->string('academic_year');
        $semester = (string) $request->string('semester');
        if (! in_array($semester, ['', 'first', 'second'], true)) {
            $semester = '';
        }

        $academicYears = DB::table('files')
            ->where('uploaded_by', $user->id)
            ->whereNotNull('academic_year')
            ->orderByDesc('academic_year')
            ->distinct()
            ->pluck('academic_year');

        if ($academicYears->isEmpty()) {
            $academicYears = collect([date('Y')]);
        }

        return view('user.my_reports', [
            'currentUser' => $user,
            'reportType' => $type,
            'academicYear' => $academicYear,
            'semester' => $semester,
            'academicYears' => $academicYears,
            'reportsData' => $this->reportData($user->id, $type, $academicYear, $semester),
            'stats' => $this->summaryStats($user->id),
        ]);
    }

    public function export(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $user = $request->user();

        $type = (string) $request->string('type', 'file_uploads');
        if (! in_array($type, self::ALLOWED_TYPES, true)) {
            $type = 'file_uploads';
        }

        $academicYear = (string) $request->string('academic_year');
        $semester = (string) $request->string('semester');
        if (! in_array($semester, ['', 'first', 'second'], true)) {
            $semester = '';
        }

        $data = $this->reportData($user->id, $type, $academicYear, $semester);

        [$headers, $rows] = $this->csvStructure($type, $data);

        $handle = fopen('php://temp', 'r+');
        fputcsv($handle, $headers);

        foreach ($rows as $row) {
            fputcsv($handle, $row);
        }

        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response("\xEF\xBB\xBF".$csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.str_replace('_', '-', $type).'-report.csv"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /* ----------------------------------------------------------------------
     | Report queries (mirror roles/user/my_reports.php)
     |---------------------------------------------------------------------- */

    private function reportData(int $userId, string $type, string $academicYear, string $semester): array
    {
        return match ($type) {
            'document_submissions' => $this->documentSubmissions($userId, $academicYear, $semester),
            'activity_summary' => $this->activitySummary($userId, $academicYear),
            'storage_usage' => $this->storageUsage($userId, $academicYear),
            default => $this->fileUploads($userId, $academicYear, $semester),
        };
    }

    private function fileUploads(int $userId, string $academicYear, string $semester): array
    {
        $query = DB::table('files as f')
            ->leftJoin('folders as fo', 'f.folder_id', '=', 'fo.id')
            ->where('f.uploaded_by', $userId)
            ->where('f.is_deleted', 0)
            ->selectRaw('f.folder_id, fo.folder_name, f.semester, f.academic_year,
                         COUNT(*) as file_count, COALESCE(SUM(f.file_size), 0) as total_size,
                         MIN(f.uploaded_at) as first_upload, MAX(f.uploaded_at) as latest_upload')
            ->groupBy('f.folder_id', 'fo.folder_name', 'f.semester', 'f.academic_year')
            ->orderByDesc('latest_upload');

        if ($academicYear !== '') {
            $query->where('f.academic_year', $academicYear);
        }
        if ($semester !== '') {
            $query->where('f.semester', $semester);
        }

        return $query->get()->all();
    }

    private function documentSubmissions(int $userId, string $academicYear, string $semester): array
    {
        $fileCounts = DB::table('document_files')
            ->selectRaw('submission_id, COUNT(*) as file_count, COALESCE(SUM(file_size), 0) as total_size')
            ->groupBy('submission_id');

        $query = DB::table('faculty_document_submissions as ds')
            ->leftJoinSub($fileCounts, 'df', 'df.submission_id', '=', 'ds.id')
            ->leftJoin('document_requirements as dr', function ($join) {
                $join->on('dr.document_type', '=', 'ds.document_type')
                    ->on('dr.semester', '=', 'ds.semester')
                    ->on('dr.academic_year', '=', 'ds.academic_year');
            })
            ->where('ds.faculty_id', $userId)
            ->selectRaw('ds.id, ds.document_type, ds.semester, ds.academic_year, ds.submitted_at,
                         MAX(dr.deadline_date) as deadline_date, MAX(dr.is_required) as is_required,
                         COALESCE(df.file_count, 0) as file_count, COALESCE(df.total_size, 0) as total_size')
            ->groupBy('ds.id', 'ds.document_type', 'ds.semester', 'ds.academic_year', 'ds.submitted_at',
                'df.file_count', 'df.total_size')
            ->orderByDesc('ds.submitted_at');

        if ($academicYear !== '') {
            $query->where('ds.academic_year', $academicYear);
        }
        if ($semester !== '') {
            // files.semester stores 'first'/'second'; submissions store '1st/2nd Semester'.
            $query->where('ds.semester', $semester === 'first' ? '1st Semester' : '2nd Semester');
        }

        return $query->get()->all();
    }

    private function activitySummary(int $userId, string $academicYear): array
    {
        $query = DB::table('activity_logs')
            ->where('user_id', $userId)
            ->selectRaw('DATE(created_at) as activity_date, action, resource_type,
                         COUNT(*) as count,
                         GROUP_CONCAT(DISTINCT description SEPARATOR "; ") as descriptions')
            ->groupBy('activity_date', 'action', 'resource_type')
            ->orderByDesc('activity_date')
            ->limit(50);

        if ($academicYear !== '') {
            $query->whereYear('created_at', $academicYear);
        }

        return $query->get()->all();
    }

    private function storageUsage(int $userId, string $academicYear): array
    {
        $query = DB::table('files as f')
            ->leftJoin('folders as fo', 'f.folder_id', '=', 'fo.id')
            ->where('f.uploaded_by', $userId)
            ->where('f.is_deleted', 0)
            ->selectRaw('fo.id as folder_id, fo.folder_name, f.file_extension,
                         COUNT(*) as file_count, COALESCE(SUM(f.file_size), 0) as total_size,
                         COALESCE(AVG(f.file_size), 0) as avg_size, MAX(f.uploaded_at) as latest_upload')
            ->groupBy('fo.id', 'fo.folder_name', 'f.file_extension')
            ->orderByDesc('total_size');

        if ($academicYear !== '') {
            $query->where('f.academic_year', $academicYear);
        }

        return $query->get()->all();
    }

    private function summaryStats(int $userId): array
    {
        return [
            'total_files' => (int) DB::table('files')->where('uploaded_by', $userId)->where('is_deleted', 0)->count(),
            'total_storage' => (int) DB::table('files')->where('uploaded_by', $userId)->where('is_deleted', 0)->sum('file_size'),
            'total_submissions' => (int) DB::table('faculty_document_submissions')->where('faculty_id', $userId)->count(),
            'recent_activities' => (int) DB::table('activity_logs')
                ->where('user_id', $userId)
                ->where('created_at', '>=', now()->subDays(7))
                ->count(),
        ];
    }

    /**
     * @return array{0: array<int, string>, 1: array<int, array<int, string|int|float|null>>}
     */
    private function csvStructure(string $type, array $data): array
    {
        if ($type === 'document_submissions') {
            $headers = ['Document Type', 'Academic Year', 'Semester', 'Deadline', 'Submitted Date', 'File Count', 'Total Size (KB)', 'Status'];
            $rows = array_map(fn ($r) => [
                $r->document_type, $r->academic_year, $r->semester,
                $r->deadline_date ? date('M d, Y', strtotime($r->deadline_date)) : 'No deadline',
                date('M d, Y', strtotime($r->submitted_at)),
                $r->file_count, number_format(($r->total_size ?: 0) / 1024, 2),
                ($r->deadline_date && strtotime($r->submitted_at) > strtotime($r->deadline_date)) ? 'Late' : 'On Time',
            ], $data);
        } elseif ($type === 'activity_summary') {
            $headers = ['Date', 'Action', 'Resource Type', 'Count', 'Description'];
            $rows = array_map(fn ($r) => [
                date('M d, Y', strtotime($r->activity_date)),
                ucwords(str_replace('_', ' ', $r->action)),
                ucwords((string) $r->resource_type),
                $r->count, $r->descriptions,
            ], $data);
        } elseif ($type === 'storage_usage') {
            $headers = ['Folder', 'File Extension', 'File Count', 'Total Size (MB)', 'Average Size (KB)', 'Latest Upload'];
            $rows = array_map(fn ($r) => [
                $r->folder_name ?: 'No Folder', '.'.($r->file_extension ?: 'unknown'),
                $r->file_count, number_format($r->total_size / (1024 * 1024), 2),
                number_format($r->avg_size / 1024, 2), date('M d, Y', strtotime($r->latest_upload)),
            ], $data);
        } else {
            $headers = ['Folder', 'Academic Year', 'Semester', 'File Count', 'Total Size (KB)', 'First Upload', 'Latest Upload'];
            $rows = array_map(fn ($r) => [
                $r->folder_name ?: 'No Folder', $r->academic_year, ucfirst((string) $r->semester),
                $r->file_count, number_format($r->total_size / 1024, 2),
                date('M d, Y', strtotime($r->first_upload)), date('M d, Y', strtotime($r->latest_upload)),
            ], $data);
        }

        return [$headers, $rows];
    }
}