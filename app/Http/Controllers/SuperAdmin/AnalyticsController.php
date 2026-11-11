<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Reports & Analytics module (super admin).
 *
 * Legacy equivalent: roles/superadmin/reports.php
 *   GET /superadmin/reports -> overview / departments / users / activities tabs
 */
class AnalyticsController extends Controller
{
    public function index(Request $request): \Illuminate\View\View
    {
        $type = (string) $request->string('type', 'overview');
        if (! in_array($type, ['overview', 'departments', 'users', 'activities'], true)) {
            $type = 'overview';
        }

        $range = (string) $request->string('range', '30');
        if (! in_array($range, ['7', '30', '90', '365', 'custom'], true)) {
            $range = '30';
        }

        [$start, $end] = $this->dateRange($request, $range);

        return view('superadmin.reports', [
            'currentUser' => $request->user(),
            'reportType' => $type,
            'dateRange' => $range,
            'startDate' => $start,
            'endDate' => $end,
            'rangeLabel' => $this->rangeLabel($range, $start, $end),
            'data' => [
                'stats' => $this->stats($start),
                'activities' => $this->activities($start, $end),
                'departments' => $this->departments($start, $end),
                'topUsers' => $this->topUsers($start, $end),
                'fileDistribution' => $this->fileDistribution($start, $end),
                'departmentActivity' => $this->departmentActivity($start, $end),
            ],
        ]);
    }

    public function export(Request $request): \Symfony\Component\HttpFoundation\Response
    {
        $type = (string) $request->string('type', 'departments');
        if (! in_array($type, ['departments', 'users', 'activities', 'overview'], true)) {
            $type = 'departments';
        }

        $range = (string) $request->string('range', '30');
        if (! in_array($range, ['7', '30', '90', '365', 'custom'], true)) {
            $range = '30';
        }

        [$start, $end] = $this->dateRange($request, $range);
        $data = $this->dataForExport($type, $start, $end);

        $handle = fopen('php://temp', 'r+');
        foreach ($data as $row) {
            fputcsv($handle, $row);
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        return response("\xEF\xBB\xBF".$csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$type.'-report.csv"',
            'Cache-Control' => 'no-store',
        ]);
    }

    /* ----------------------------------------------------------------------
     | Data builders
     |---------------------------------------------------------------------- */

    private function stats(Carbon $start): array
    {
        $state = [
            'files' => [
                'total_files' => (int) DB::table('files')->where('is_deleted', 0)->count(),
                'new_files' => (int) DB::table('files')->where('is_deleted', 0)->where('uploaded_at', '>=', $start)->count(),
                'deleted_files' => (int) DB::table('files')->where('is_deleted', 1)->count(),
                'total_size' => (int) DB::table('files')->where('is_deleted', 0)->sum('file_size'),
            ],
            'folders' => [
                'total_folders' => (int) DB::table('folders')->where('is_deleted', 0)->count(),
                'new_folders' => (int) DB::table('folders')->where('is_deleted', 0)->where('created_at', '>=', $start)->count(),
            ],
            'users' => [
                'total_users' => (int) DB::table('users')->where('is_approved', 1)->count(),
                'new_users' => (int) DB::table('users')->where('is_approved', 1)->where('created_at', '>=', $start)->count(),
            ],
            'documents' => [
                'total_requests' => (int) DB::table('document_requests')->where('is_deleted', 0)->count(),
                'new_requests' => (int) DB::table('document_requests')->where('is_deleted', 0)->where('created_at', '>=', $start)->count(),
            ],
            'shares' => [
                'total_shares' => (int) DB::table('file_shares')->where('is_active', 1)->count(),
                'new_shares' => (int) DB::table('file_shares')->where('is_active', 1)->where('created_at', '>=', $start)->count(),
            ],
        ];

        return $state;
    }

    private function activities(Carbon $start, Carbon $end): array
    {
        return DB::table('activity_logs as al')
            ->leftJoin('users as u', 'al.user_id', '=', 'u.id')
            ->leftJoin('departments as d', 'u.department_id', '=', 'd.id')
            ->whereBetween('al.created_at', [$start, $end])
            ->selectRaw('al.*, u.username, CONCAT(COALESCE(u.name, ""), " ", COALESCE(u.surname, "")) as full_name,
                         COALESCE(d.department_name, "") as department_name')
            ->orderByDesc('al.created_at')
            ->limit(200)
            ->get()
            ->map(function ($row) {
                $row->full_name = trim($row->full_name) ?: ($row->username ?? 'System');

                return $row;
            })
            ->values()
            ->all();
    }

    private function departments(Carbon $start, Carbon $end): array
    {
        return DB::table('departments as d')
            ->selectRaw('d.id, d.department_name, d.department_code')
            ->orderBy('d.department_name')
            ->get()
            ->map(function ($dept) use ($start, $end) {
                $dept->user_count = (int) DB::table('users')
                    ->where('department_id', $dept->id)->where('is_approved', 1)->count();
                $dept->folder_count = (int) DB::table('folders')
                    ->where('department_id', $dept->id)->where('is_deleted', 0)->count();
                $dept->file_count = (int) DB::table('files as f')
                    ->join('folders as fo', 'f.folder_id', '=', 'fo.id')
                    ->where('fo.department_id', $dept->id)->where('f.is_deleted', 0)->count();
                $dept->total_size = (int) DB::table('files as f')
                    ->join('folders as fo', 'f.folder_id', '=', 'fo.id')
                    ->where('fo.department_id', $dept->id)->where('f.is_deleted', 0)->sum('f.file_size');

                return $dept;
            })
            ->values()
            ->all();
    }

    private function topUsers(Carbon $start, Carbon $end): array
    {
        return DB::table('users as u')
            ->leftJoin('departments as d', 'u.department_id', '=', 'd.id')
            ->where('u.is_approved', 1)
            ->selectRaw('u.id, u.name, u.surname, u.username, CONCAT(COALESCE(u.name, ""), " ", COALESCE(u.surname, "")) as full_name,
                         COALESCE(d.department_name, "") as department_name')
            ->orderBy('u.name')->orderBy('u.surname')
            ->get()
            ->map(function ($user) use ($start, $end) {
                $user->files_uploaded = (int) DB::table('files')
                    ->where('uploaded_by', $user->id)->where('is_deleted', 0)
                    ->where('uploaded_at', '>=', $start)->count();
                $user->total_uploaded_size = (int) DB::table('files')
                    ->where('uploaded_by', $user->id)->where('is_deleted', 0)->sum('file_size');
                $user->activities = (int) DB::table('activity_logs')
                    ->where('user_id', $user->id)->whereBetween('created_at', [$start, $end])->count();

                return $user;
            })
            ->filter(fn ($user) => $user->files_uploaded > 0 || $user->activities > 0)
            ->sortByDesc(fn ($user) => ($user->files_uploaded * 2) + $user->activities)
            ->take(15)
            ->values()
            ->all();
    }

    private function fileDistribution(Carbon $start, Carbon $end): array
    {
        $files = DB::table('files')->whereBetween('uploaded_at', [$start, $end]);

        return [
            'files' => (int) (clone $files)->where('is_deleted', 0)->count(),
            'deleted' => (int) (clone $files)->where('is_deleted', 1)->count(),
            'size' => (int) (clone $files)->where('is_deleted', 0)->sum('file_size'),
        ];
    }

    private function departmentActivity(Carbon $start, Carbon $end): \Illuminate\Support\Collection
    {
        return DB::table('activity_logs as al')
            ->join('users as u', 'al.user_id', '=', 'u.id')
            ->join('departments as d', 'u.department_id', '=', 'd.id')
            ->whereBetween('al.created_at', [$start, $end])
            ->selectRaw('d.department_name, COUNT(*) as activity_count')
            ->groupBy('d.id', 'd.department_name')
            ->orderByDesc('activity_count')
            ->limit(10)
            ->get();
    }

    private function dataForExport(string $type, Carbon $start, Carbon $end): array
    {
        if ($type === 'users') {
            $rows = [['User', 'Username', 'Department', 'Files Uploaded', 'Total Size', 'Activities', 'Activity Score']];
            foreach ($this->topUsers($start, $end) as $u) {
                $rows[] = [$u->full_name, $u->username, $u->department_name,
                    $u->files_uploaded, $u->total_uploaded_size, $u->activities,
                    ($u->files_uploaded * 2) + $u->activities];
            }

            return $rows;
        }

        if ($type === 'activities') {
            $rows = [['Date/Time', 'User', 'Action', 'Resource Type', 'Description', 'IP Address']];
            foreach ($this->activities($start, $end) as $a) {
                $rows[] = [$a->created_at, $a->full_name, $a->action, $a->resource_type, $a->description, $a->ip_address];
            }

            return $rows;
        }

        // Departments (and overview fallback).
        $rows = [['Department', 'Code', 'Users', 'Folders', 'Files', 'Storage Used']];
        foreach ($this->departments($start, $end) as $d) {
            $rows[] = [$d->department_name, $d->department_code, $d->user_count,
                $d->folder_count, $d->file_count, $d->total_size];
        }

        return $rows;
    }

    /* ----------------------------------------------------------------------
     | Range helpers
     |---------------------------------------------------------------------- */

    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    private function dateRange(Request $request, string $range): array
    {
        if ($range === 'custom') {
            $start = Carbon::parse($request->string('start_date', now()->subDays(30)->toDateString())->value())->startOfDay();
            $end = Carbon::parse($request->string('end_date', now()->toDateString())->value())->endOfDay();

            return [$start, $end];
        }

        $end = now()->endOfDay();
        $start = now()->subDays((int) $range)->startOfDay();

        return [$start, $end];
    }

    private function rangeLabel(string $range, Carbon $start, Carbon $end): string
    {
        return match ($range) {
            '7' => 'Last 7 Days',
            '30' => 'Last 30 Days',
            '90' => 'Last 90 Days',
            '365' => 'Last Year',
            default => 'Custom Range ('.$start->format('M j, Y').' - '.$end->format('M j, Y').')',
        };
    }
}