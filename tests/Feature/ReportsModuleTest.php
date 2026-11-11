<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Reports module.
 *
 * Legacy equivalents:
 *   roles/user/my_reports.php      -> GET /user/my-reports (+ CSV export)
 *   roles/superadmin/reports.php   -> GET /superadmin/reports (+ CSV export)
 *
 * Uses DatabaseTransactions against the live odci_db; usernames are prefixed
 * `rp_` so they never collide with real accounts.
 */
class ReportsModuleTest extends TestCase
{
    use DatabaseTransactions, WithFaker;

    /** Rotates through document types to stay clear of unique_submission. */
    private const DOC_TYPES = ['Exam', 'Syllabus', 'Module', 'Accomplishment', 'Curriculum', 'Memorandum'];

    private int $docTypeSeq = 0;

    private ?int $folderId = null;

    private function departmentId(): int
    {
        return (int) DB::table('departments')->where('is_active', 1)->value('id') ?: 1;
    }

    private function folderId(int $createdBy): int
    {
        if ($this->folderId === null) {
            $this->folderId = (int) DB::table('folders')->insertGetId([
                'folder_name' => 'rp_test_folder',
                'description' => null,
                'created_by' => $createdBy,
                'parent_id' => null,
                'is_deleted' => 0,
                'department_id' => $this->departmentId(),
                'folder_type' => 'custom',
                'folder_status' => 'active',
                'file_count' => 0,
                'folder_size' => 0,
            ]);
        }

        return $this->folderId;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeUser(string $role, array $overrides = []): User
    {
        $user = (new User())->forceFill(array_merge([
            'username' => 'rp_'.$this->faker->unique()->numerify('####'),
            'email' => 'rp_'.$this->faker->unique()->safeEmail(),
            'password' => 'MigrationTest@123',
            'role' => $role,
            'is_approved' => 1,
            'is_restricted' => 0,
            'failed_login_attempts' => 0,
            'name' => 'Reports',
            'mi' => 'R',
            'surname' => 'Fixture',
            'employee_id' => $this->faker->unique()->numerify('RPL-#####'),
            'position' => 'Instructor',
            'department_id' => $this->departmentId(),
        ], $overrides));

        $user->save();

        return $user;
    }

    private function makeFileUpload(int $uploadedBy, array $overrides = []): void
    {
        DB::table('files')->insert(array_merge([
            'folder_id' => $this->folderId($uploadedBy),
            'file_name' => bin2hex(random_bytes(8)).'.pdf',
            'original_name' => $this->faker->word.'.pdf',
            'file_extension' => 'pdf',
            'file_size' => 2048,
            'file_path' => 'uploads/departments/'.$this->faker->unique()->numerify('####').'/'.bin2hex(random_bytes(6)).'.pdf',
            'uploaded_by' => $uploadedBy,
            'semester' => 'first',
            'academic_year' => '2024-2025',
            'is_deleted' => 0,
            'uploaded_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }

    private function makeSubmission(int $facultyId, array $overrides = []): void
    {
        DB::table('faculty_document_submissions')->insert(array_merge([
            'faculty_id' => $facultyId,
            'document_type' => self::DOC_TYPES[$this->docTypeSeq++ % count(self::DOC_TYPES)],
            'semester' => '1st Semester',
            'academic_year' => 2024,
            'submitted_at' => now(),
            'submitted_by' => $facultyId,
        ], $overrides));
    }

    /* ------------------------------------------------------------------
     | Gating
     |------------------------------------------------------------------ */

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/user/my-reports')->assertRedirect('/login');
        $this->get('/superadmin/reports')->assertRedirect('/login');
    }

    public function test_pages_are_gated_by_role(): void
    {
        $user = $this->makeUser('user');
        $super = $this->makeUser('super_admin');

        $this->actingAs($user)->get('/superadmin/reports')->assertRedirect('/user/dashboard');
        $this->actingAs($super)->get('/user/my-reports')->assertRedirect('/superadmin/dashboard');
    }

    /* ------------------------------------------------------------------
     | User reports
     |------------------------------------------------------------------ */

    public function test_user_my_reports_renders_upload_activity_tab(): void
    {
        $user = $this->makeUser('user');
        $this->makeFileUpload($user->id);

        $this->actingAs($user)->get('/user/my-reports')
            ->assertOk()
            ->assertSee('My Reports')
            ->assertSee('Upload Activity')
            ->assertSee('Document Submissions')
            ->assertSee('Storage Usage')
            ->assertSee('2024-2025');
    }

    public function test_user_my_reports_renders_all_four_types(): void
    {
        $user = $this->makeUser('user');
        $this->makeFileUpload($user->id);
        $this->makeSubmission($user->id);
        DB::table('activity_logs')->insert([
            'user_id' => $user->id,
            'action' => 'file_upload',
            'resource_type' => 'file',
            'description' => 'Uploaded a test file',
            'created_at' => now(),
        ]);

        foreach (['file_uploads', 'document_submissions', 'activity_summary', 'storage_usage'] as $type) {
            $this->actingAs($user)->get('/user/my-reports?type='.$type)->assertOk();
        }
    }

    public function test_user_my_reports_honors_academic_year_and_semester_filters(): void
    {
        $user = $this->makeUser('user');
        $this->makeFileUpload($user->id, ['academic_year' => '2024-2025', 'semester' => 'first']);
        $this->makeFileUpload($user->id, ['academic_year' => '2025-2026', 'semester' => 'second']);

        $this->actingAs($user)->get('/user/my-reports?type=file_uploads&academic_year=2025-2026&semester=second')
            ->assertOk()
            ->assertSee('2025-2026');
    }

    public function test_invalid_report_type_falls_back_to_file_uploads(): void
    {
        $user = $this->makeUser('user');

        $this->actingAs($user)->get('/user/my-reports?type=made_up')
            ->assertOk()
            ->assertSee('Upload Activity');
    }

    public function test_user_report_csv_export(): void
    {
        $user = $this->makeUser('user');
        $this->makeFileUpload($user->id);

        $this->actingAs($user)->get('/user/my-reports/export?type=file_uploads&academic_year=2024-2025')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('Content-Disposition', 'attachment; filename="file-uploads-report.csv"')
            ->assertSee('Folder', false)
            ->assertSee('2024-2025');
    }

    /* ------------------------------------------------------------------
     | Superadmin reports
     |------------------------------------------------------------------ */

    public function test_superadmin_reports_renders_overview(): void
    {
        $super = $this->makeUser('super_admin');
        $this->makeFileUpload($super->id);

        $this->actingAs($super)->get('/superadmin/reports')
            ->assertOk()
            ->assertSee('Reports')
            ->assertSee('Overview')
            ->assertSee('Date Range')
            ->assertSee('File Distribution');
    }

    public function test_superadmin_reports_renders_all_tabs(): void
    {
        $super = $this->makeUser('super_admin');
        $this->makeFileUpload($super->id);

        foreach (['overview', 'departments', 'users', 'activities'] as $tab) {
            $this->actingAs($super)->get('/superadmin/reports?type='.$tab)->assertOk();
        }
    }

    public function test_superadmin_reports_supports_custom_date_range(): void
    {
        $super = $this->makeUser('super_admin');

        $this->actingAs($super)->get('/superadmin/reports?type=overview&range=custom&start_date=2025-01-01&end_date=2025-12-31')
            ->assertOk()
            ->assertSee('Custom Range');
    }

    public function test_superadmin_report_csv_export(): void
    {
        $super = $this->makeUser('super_admin');

        $this->actingAs($super)->get('/superadmin/reports/export?type=departments&range=30')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('Content-Disposition', 'attachment; filename="departments-report.csv"')
            ->assertSee('Department', false);
    }
}