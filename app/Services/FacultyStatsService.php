<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Port of getFacultySubmissionStats() from roles/admin/script/faculty-staff.php
 * plus the academic-period helpers it depends on (includes/document_period.php).
 *
 * The SQL and the shape of the returned array are identical to the legacy
 * implementation, so the admin summary cards and the "View Stats" JSON endpoint
 * render the same numbers the old page did.
 */
class FacultyStatsService
{
    /**
     * Submission statistics for one faculty member.
     *
     * @return array<string, mixed>
     */
    public function forUser(int $userId): array
    {
        $stats = [
            'total_required' => 0,
            'total_submitted' => 0,
            'completion_rate' => 0,
            'latest_submission' => null,
            'overdue_count' => 0,
            'pending_count' => 0,
            'on_time_submissions' => 0,
            'submission_streak' => 0,
            'total_files' => 0,
            'average_submission_time' => null,
            'academic_year' => null,
            'semester' => null,
            'document_types' => [],
        ];

        try {
            // Period being reported. Defaults to the newest period that actually
            // has uploads, because document_requirements is stale (last row is
            // AY 2025) and a calendar-derived period would report 0% for
            // everyone.
            $currentYear = (int) date('Y');
            $currentMonth = (int) date('n');
            $currentSemester = ($currentMonth >= 6 && $currentMonth <= 11) ? 'first' : 'second';

            $latest = $this->latestSubmissionPeriod($userId);
            $reportYear = $latest['start_year'] ?: $currentYear;
            $reportSemester = $latest['semester'] ?: $currentSemester;

            $stats['academic_year'] = $this->academicYearRange($reportYear);
            $stats['semester'] = $reportSemester;

            // Tracked document types for the period: prefer the requirement rows
            // that exist for it, otherwise fall back to the canonical category
            // list so an empty period does not divide by zero.
            $requirementTypes = $this->periodRequiredDocumentTypes($reportYear, $reportSemester);
            $stats['total_required'] = count($requirementTypes);
            if ($stats['total_required'] === 0) {
                $stats['total_required'] = count($this->defaultDocumentTypes());
            }

            // Real submissions: files joined to the folder that carries the
            // category.
            $categoryMap = $this->categoryToDocumentType();
            $rows = DB::select("
                SELECT fo.category AS category,
                       COUNT(f.id) AS file_count,
                       SUM(f.file_size) AS total_size,
                       MAX(f.uploaded_at) AS latest_upload,
                       MIN(f.uploaded_at) AS first_upload
                FROM files f
                INNER JOIN folders fo ON f.folder_id = fo.id
                WHERE f.uploaded_by = ?
                  AND ".$this->academicYearSql('f.academic_year')."
                  AND f.semester = ?
                  AND f.is_deleted = 0
                  AND fo.is_deleted = 0
                  AND fo.category IS NOT NULL
                GROUP BY fo.category
            ", [(int) $userId, $reportYear, $reportSemester]);

            $latestUpload = null;
            $totalFiles = 0;
            $totalSize = 0;
            $submittedTypes = [];

            foreach ($rows as $row) {
                $matchedType = $categoryMap[$row->category] ?? $row->category;
                $submittedTypes[$matchedType] = [
                    'files' => (int) $row->file_count,
                    'size' => (int) $row->total_size,
                    'latest' => $row->latest_upload,
                ];
                $totalFiles += (int) $row->file_count;
                $totalSize += (int) $row->total_size;
                if ($row->latest_upload && (! $latestUpload || $row->latest_upload > $latestUpload)) {
                    $latestUpload = $row->latest_upload;
                }
            }

            $stats['document_types'] = $submittedTypes;
            $stats['total_submitted'] = count($submittedTypes);
            $stats['total_files'] = $totalFiles;
            $stats['total_size'] = $totalSize;
            $stats['latest_submission'] = $latestUpload;

            if ($stats['total_required'] > 0) {
                $stats['completion_rate'] = round(($stats['total_submitted'] / $stats['total_required']) * 100, 1);
            }

            // Deadlines only exist on document_requirements; without one there is
            // nothing to be late against, so both counts stay at zero.
            $deadlineMap = $this->periodDeadlines($reportYear, $reportSemester, (int) $userId);
            if (! empty($deadlineMap)) {
                $overdue = 0;
                foreach ($deadlineMap as $type => $deadline) {
                    if ($deadline === null) {
                        continue;
                    }
                    if (! isset($submittedTypes[$type]) && strtotime($deadline) < strtotime(date('Y-m-d'))) {
                        $overdue++;
                    }
                }
                $stats['overdue_count'] = $overdue;
                $stats['pending_count'] = max(0, $stats['total_required'] - $stats['total_submitted'] - $overdue);

                $onTime = 0;
                foreach ($submittedTypes as $type => $info) {
                    if (isset($deadlineMap[$type]) && $deadlineMap[$type] !== null
                        && $info['latest'] && strtotime($info['latest']) <= strtotime($deadlineMap[$type])) {
                        $onTime++;
                    }
                }
                $stats['on_time_submissions'] = $onTime;
            } else {
                $stats['pending_count'] = max(0, $stats['total_required'] - $stats['total_submitted']);
            }

            // Consecutive months (most recent first) in which this user uploaded.
            $monthRows = DB::select("
                SELECT DISTINCT DATE_FORMAT(uploaded_at, '%Y-%m') AS ym
                FROM files
                WHERE uploaded_by = ?
                  AND is_deleted = 0
                  AND uploaded_at >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH)
                ORDER BY ym DESC
            ", [(int) $userId]);
            $months = array_column(array_map(fn ($row) => (array) $row, $monthRows), 'ym');
            $stats['submission_streak'] = $this->countMonthStreak($months);

            // Additional metrics
            $stats['submission_percentage'] = $stats['completion_rate'];
            $stats['status'] = $this->submissionStatus($stats['completion_rate']);
            $stats['performance_indicator'] = $this->performanceIndicator($stats);

        } catch (Throwable $e) {
            // Legacy behaviour: log and return default stats.
            report($e);
        }

        return $stats;
    }

    /* ------------------------------------------------------------------
     | Helpers ported from includes/document_period.php
     * ------------------------------------------------------------------ */

    /** @return array{start_year: int, semester: string|null} */
    private function latestSubmissionPeriod(int $userId): array
    {
        try {
            $row = DB::selectOne("
                SELECT f.academic_year AS academic_year, f.semester AS semester, MAX(f.uploaded_at) AS latest
                FROM files f
                INNER JOIN folders fo ON f.folder_id = fo.id
                WHERE f.uploaded_by = ?
                  AND f.is_deleted = 0
                  AND fo.is_deleted = 0
                  AND fo.category IS NOT NULL
                GROUP BY f.academic_year, f.semester
                ORDER BY f.academic_year DESC, f.semester DESC, latest DESC
                LIMIT 1
            ", [$userId]);

            if (! $row) {
                return ['start_year' => 0, 'semester' => null];
            }

            return [
                'start_year' => $this->academicYearStart($row->academic_year),
                'semester' => $this->semesterColumn($row->semester),
            ];
        } catch (Throwable $e) {
            return ['start_year' => 0, 'semester' => null];
        }
    }

    /** @return array<int, string> */
    private function periodRequiredDocumentTypes(int $startYear, $semester): array
    {
        try {
            $types = DB::select("
                SELECT DISTINCT document_type
                FROM document_requirements
                WHERE academic_year = ?
                  AND semester = ?
                  AND is_required = 1
                ORDER BY document_type
            ", [(string) $startYear, $this->semesterOrdinal($semester)]);

            return array_values(array_filter(array_map(fn ($row) => $row->document_type, $types)));
        } catch (Throwable $e) {
            return [];
        }
    }

    /** @return array<string, string|null> */
    private function periodDeadlines(int $startYear, $semester, int $userId): array
    {
        try {
            $rows = DB::select("
                SELECT dr.document_type, MIN(dr.deadline_date) AS deadline_date
                FROM document_requirements dr
                WHERE dr.academic_year = ?
                  AND dr.semester = ?
                  AND dr.is_required = 1
                  AND (dr.department_id IS NULL OR dr.department_id = (
                        SELECT department_id FROM users WHERE id = ?
                  ))
                GROUP BY dr.document_type
            ", [(string) $startYear, $this->semesterOrdinal($semester), $userId]);

            $deadlines = [];
            foreach ($rows as $row) {
                $deadlines[$row->document_type] = $row->deadline_date;
            }

            return $deadlines;
        } catch (Throwable $e) {
            return [];
        }
    }

    /** @return array<string, string> */
    private function categoryToDocumentType(): array
    {
        return [
            'ipcr_accomplishment' => 'IPCR Accomplishment',
            'ipcr_target' => 'IPCR Target',
            'workload' => 'Workload',
            'course_syllabus' => 'Course Syllabus',
            'syllabus_acceptance' => 'Course Syllabus Acceptance Form',
            'exam' => 'Exam',
            'tos' => 'TOS',
            'class_record' => 'Class Record',
            'grading_sheet' => 'Grading Sheets',
            'attendance_sheet' => 'Attendance Sheet',
            'stakeholder_feedback' => "Stakeholder's Feedback Form w/ Summary",
            'consultation' => 'Consultation',
            'lecture' => 'Lecture',
            'activities' => 'Activities',
            'exam_acknowledgement' => 'CEIT-QF-03 Discussion Form',
            'consultation_log' => 'Others',
        ];
    }

    /** @return array<int, string> */
    private function defaultDocumentTypes(): array
    {
        $types = array_values($this->categoryToDocumentType());
        sort($types);

        return $types;
    }

    /** SQL predicate matching `files.academic_year` range or bare year. */
    private function academicYearSql(string $column = 'f.academic_year'): string
    {
        return "CAST(LEFT(TRIM($column), 4) AS UNSIGNED) = ?";
    }

    private function academicYearRange(int $startYear): string
    {
        return $startYear.'-'.($startYear + 1);
    }

    private function academicYearStart($value): int
    {
        if ($value === null || is_array($value)) {
            return 0;
        }

        $value = trim((string) $value);
        if ($value === '') {
            return 0;
        }

        if (preg_match('/^(\d{4})(?:\s*-\s*(\d{4}))?$/', $value, $matches)) {
            return (int) $matches[1];
        }

        return 0;
    }

    private function semesterColumn($semester): string
    {
        $normalized = strtolower(trim((string) $semester));
        $second = ['2', '2nd', 'second', '2nd semester', 'second semester', 'semester 2'];

        return in_array($normalized, $second, true) ? 'second' : 'first';
    }

    private function semesterOrdinal($semester): string
    {
        return $this->semesterColumn($semester) === 'second' ? '2nd Semester' : '1st Semester';
    }

    /**
     * Length of the unbroken run of consecutive 'Y-m' values ending at the most
     * recent month present in $months.
     *
     * @param array<int, string> $months
     */
    private function countMonthStreak(array $months): int
    {
        if (empty($months)) {
            return 0;
        }

        $sorted = array_values(array_unique($months));
        rsort($sorted); // 'Y-m' sorts chronologically as a string.

        $streak = 1;
        $previous = $sorted[0];

        for ($i = 1, $count = count($sorted); $i < $count; $i++) {
            $expected = date('Y-m', strtotime($previous.'-01 -1 month'));
            if ($sorted[$i] === $expected) {
                $streak++;
                $previous = $sorted[$i];
            } else {
                break;
            }
        }

        return $streak;
    }

    private function submissionStatus(float $completionRate): string
    {
        if ($completionRate >= 90) {
            return 'excellent';
        }
        if ($completionRate >= 75) {
            return 'good';
        }
        if ($completionRate >= 50) {
            return 'fair';
        }

        return 'needs_attention';
    }

    /** @param array<string, mixed> $stats */
    private function performanceIndicator(array $stats): string
    {
        $score = 0;

        // Completion rate (40% weight)
        $score += ($stats['completion_rate'] / 100) * 40;

        // On-time submissions (30% weight)
        if ($stats['total_submitted'] > 0) {
            $score += ($stats['on_time_submissions'] / $stats['total_submitted']) * 30;
        }

        // Recent activity (20% weight)
        if ($stats['submission_streak'] >= 3) {
            $score += 20;
        } elseif ($stats['submission_streak'] >= 1) {
            $score += 10;
        }

        // No overdue items (10% weight)
        if ($stats['overdue_count'] == 0) {
            $score += 10;
        }

        if ($score >= 85) {
            return 'outstanding';
        }
        if ($score >= 70) {
            return 'proficient';
        }
        if ($score >= 50) {
            return 'developing';
        }

        return 'needs_improvement';
    }
}