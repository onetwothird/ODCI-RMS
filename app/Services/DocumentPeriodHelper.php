<?php

namespace App\Services;

use Illuminate\Support\Facades\DB;

/**
 * Laravel port of ODCI academic period and upload helpers.
 *
 * These are the single source of truth for converting between the academic
 * period representations used across the app, plus a resolver for the
 * relative `files.file_path` values.
 */
class DocumentPeriodHelper
{
    /**
     * Normalise any semester representation to the `files.semester` enum value.
     */
    public function semesterColumn(string $semester): string
    {
        $normalized = strtolower(trim($semester));
        $second = ['2', '2nd', 'second', '2nd semester', 'second semester', 'semester 2'];

        return in_array($normalized, $second, true) ? 'second' : 'first';
    }

    /** 'first' => 'First Semester' */
    public function semesterLabel(string $semester): string
    {
        return $this->semesterColumn($semester) === 'second' ? 'Second Semester' : 'First Semester';
    }

    /** 'first' => '1st Semester' (the format used by document_requirements) */
    public function semesterOrdinal(string $semester): string
    {
        return $this->semesterColumn($semester) === 'second' ? '2nd Semester' : '1st Semester';
    }

    /**
     * Reduce any stored academic year to its starting year.
     * Accepts '2026', 2026, '2026-2027' and returns 0 when nothing parses.
     */
    public function academicYearStart(mixed $value): int
    {
        if ($value === null || is_array($value)) {
            return 0;
        }

        $value = trim((string)$value);
        if ($value === '') {
            return 0;
        }

        if (preg_match('/^(\d{4})(?:\s*-\s*(\d{4}))?$/', $value, $matches)) {
            return (int)$matches[1];
        }

        return 0;
    }

    /** 2026 => '2026-2027' */
    public function academicYearRange(int $startYear): string
    {
        return $startYear . '-' . ($startYear + 1);
    }

    /** Build the GET/label value used by the tracker period filter. */
    public function periodKey(int $startYear, string $semester): string
    {
        return self::makePeriodLabel($startYear, $semester);
    }

    /**
     * Static convenience for blades: '1st Semester AY 2026-2027'.
     */
    public static function makePeriodLabel(int $startYear, string $semester): string
    {
        $helper = new self();

        return $helper->semesterOrdinal($semester).' AY '.$helper->academicYearRange($startYear);
    }

    /**
     * Parse a period filter value back into its parts.
     * Accepts '1st Semester AY 2026-2027', '2026-2027', '2026', 'first'.
     */
    public function periodParse(string $value): array
    {
        $value = trim($value);

        $semester = $value;
        $startYear = 0;

        if (preg_match('/^(.*?)\s+AY\s+(\d{4})(?:\s*-\s*\d{4})?$/i', $value, $matches)) {
            $semester = $matches[1];
            $startYear = (int)$matches[2];
        } else {
            $startYear = $this->academicYearStart($value);
        }

        return [
            'start_year' => $startYear,
            'semester' => $this->semesterColumn($semester),
            'label' => $startYear > 0 ? $this->periodKey($startYear, $semester) : '',
        ];
    }

    /** SQL expression projecting the starting year of a stored academic year. */
    public function academicYearExpr(string $column = 'f.academic_year'): string
    {
        return "CAST(LEFT(TRIM($column), 4) AS UNSIGNED)";
    }

    /**
     * SQL predicate matching `files.academic_year` regardless of whether the row
     * stores the bare starting year or the 'YYYY-YYYY' range.
     */
    public function academicYearSql(string $column = 'f.academic_year'): string
    {
        return $this->academicYearExpr($column) . ' = ?';
    }

    /** Folder category key => canonical document type label used by the tracker. */
    public function categoryToDocumentType(): array
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

    /** Canonical document type label => folder category key. */
    public function documentTypeToCategory(): array
    {
        return array_flip($this->categoryToDocumentType());
    }

    /** The document types tracked when a period has no document_requirements rows. */
    public function defaultDocumentTypes(): array
    {
        $types = array_values($this->categoryToDocumentType());
        sort($types);

        return $types;
    }

    /**
     * Required document types for a period, from document_requirements when it has
     * rows for that period, otherwise the canonical category list.
     */
    public function periodRequiredDocumentTypes(int $startYear, string $semester): array
    {
        try {
            $types = DB::table('document_requirements')
                ->where('academic_year', (string)$startYear)
                ->where('semester', $this->semesterOrdinal($semester))
                ->where('is_required', 1)
                ->orderBy('document_type')
                ->distinct()
                ->pluck('document_type')
                ->all();

            return array_values(array_filter($types));
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * document_type => deadline for a period, scoped to the user's department.
     * Types with no deadline keep a null value so callers can distinguish
     * "no deadline set" from "deadline in the future".
     */
    public function periodDeadlines(int $startYear, string $semester, int $userId): array
    {
        try {
            $rows = DB::table('document_requirements')
                ->where('academic_year', (string)$startYear)
                ->where('semester', $this->semesterOrdinal($semester))
                ->where('is_required', 1)
                ->where(function ($q) use ($userId) {
                    $q->whereNull('department_id')
                        ->orWhere('department_id', function ($sub) use ($userId) {
                            $sub->select('department_id')
                                ->from('users')
                                ->where('id', $userId);
                        });
                })
                ->groupBy('document_type')
                ->select('document_type', DB::raw('MIN(deadline_date) as deadline_date'))
                ->get();

            $deadlines = [];
            foreach ($rows as $row) {
                $deadlines[$row->document_type] = $row->deadline_date;
            }

            return $deadlines;
        } catch (\Throwable $e) {
            return [];
        }
    }

    /**
     * The most recent period a user actually uploaded files in.
     * document_requirements still only covers AY 2025, so a calendar-derived period
     * reports 0% completion for everyone. Returns start_year => 0 when the user has
     * never uploaded, so callers can fall back.
     */
    public function latestSubmissionPeriod(int $userId): array
    {
        try {
            $row = DB::table('files as f')
                ->join('folders as fo', 'f.folder_id', '=', 'fo.id')
                ->select(
                    'f.academic_year as academic_year',
                    'f.semester as semester',
                    DB::raw('MAX(f.uploaded_at) as latest')
                )
                ->where('f.uploaded_by', $userId)
                ->where('f.is_deleted', 0)
                ->where('fo.is_deleted', 0)
                ->whereNotNull('fo.category')
                ->groupBy('f.academic_year', 'f.semester')
                ->orderByDesc('f.academic_year')
                ->orderByDesc('f.semester')
                ->orderByDesc('latest')
                ->first();

            if (!$row) {
                return ['start_year' => 0, 'semester' => null];
            }

            return [
                'start_year' => $this->academicYearStart($row->academic_year),
                'semester' => $this->semesterColumn($row->semester),
            ];
        } catch (\Throwable $e) {
            return ['start_year' => 0, 'semester' => null];
        }
    }

    /**
     * Length of the unbroken run of consecutive 'Y-m' values ending at the most
     * recent month present in $months. A gap of a single missing month ends the
     * streak - that is what a "streak" means, as opposed to "months with activity".
     */
    public function countMonthStreak(array $months): int
    {
        if (empty($months)) {
            return 0;
        }

        $sorted = array_values(array_unique($months));
        rsort($sorted); // 'Y-m' sorts chronologically as a string.

        $streak = 1;
        $previous = $sorted[0];

        for ($i = 1, $count = count($sorted); $i < $count; $i++) {
            $expected = date('Y-m', strtotime($previous . '-01 -1 month'));
            if ($sorted[$i] === $expected) {
                $streak++;
                $previous = $sorted[$i];
            } else {
                break;
            }
        }

        return $streak;
    }
}