<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Document Tracker module.
 *
 * Legacy equivalents:
 *   roles/admin/document-tracker.php      -> GET /admin/document-tracker
 *   roles/superadmin/document-tracker.php -> GET /superadmin/document-tracker
 *
 * IMPORTANT: DatabaseTransactions (NOT RefreshDatabase) is used deliberately.
 * These tests run against the real legacy `odci_db` schema and must never
 * create, drop, or migrate anything -- every change is rolled back.
 * All fixture usernames are prefixed with `dtrack_` so they cannot collide
 * with the unique username / email indexes of real accounts.
 */
class DocumentTrackerTest extends TestCase
{
    use DatabaseTransactions, WithFaker;

    private ?int $deptId = null;

    private function departmentId(): int
    {
        if ($this->deptId === null) {
            $this->deptId = (int) DB::table('departments')->where('is_active', 1)->value('id');
        }

        return $this->deptId;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeUser(string $role, array $overrides = []): User
    {
        $user = (new User())->forceFill(array_merge([
            'username' => 'dtrack_'.$this->faker->unique()->numerify('####'),
            'email' => 'dtrack_'.$this->faker->unique()->safeEmail(),
            'password' => 'MigrationTest@123',
            'role' => $role,
            'is_approved' => 1,
            'is_restricted' => 0,
            'failed_login_attempts' => 0,
            'name' => 'Dotrack',
            'mi' => 'T',
            'surname' => 'Fixture',
            'employee_id' => $this->faker->unique()->numerify('DTR-#####'),
            'position' => 'Instructor',
            'department_id' => $this->departmentId(),
        ], $overrides));

        $user->save();

        return $user;
    }

    private function assertLegacySkeleton($response): void
    {
        $response->assertOk();
        $response->assertSee('id="sidebar"', false);
        $response->assertSee('<section id="content">', false);
        $response->assertSee('css/cvsu-theme.css', false);
        $response->assertSee('boxicons@2.0.9', false);
    }

    // -----------------------------------------------------------------
    // Access control
    // -----------------------------------------------------------------

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/admin/document-tracker')->assertRedirect('/login');
        $this->get('/superadmin/document-tracker')->assertRedirect('/login');
        $this->get('/document-tracker/file-details')->assertRedirect('/login');
        $this->get('/document-tracker/all-submissions')->assertRedirect('/login');
    }

    public function test_pages_are_gated_by_role(): void
    {
        $faculty = $this->makeUser('user');

        $this->actingAs($faculty)
            ->get('/admin/document-tracker')
            ->assertRedirect('/user/dashboard');

        $this->actingAs($faculty)
            ->get('/superadmin/document-tracker')
            ->assertRedirect('/user/dashboard');

        $admin = $this->makeUser('admin');
        $this->actingAs($admin)
            ->get('/superadmin/document-tracker')
            ->assertRedirect('/admin/dashboard');
    }

    // -----------------------------------------------------------------
    // Admin tracker
    // -----------------------------------------------------------------

    public function test_admin_page_renders_tracker_markers(): void
    {
        $admin = $this->makeUser('admin');
        $this->makeUser('user');

        $response = $this->actingAs($admin)->get('/admin/document-tracker');

        $this->assertLegacySkeleton($response);
        $response->assertSee('Document Submission Tracker', false);
        $response->assertSee('Faculty Document Tracker', false);
        $response->assertSee('css/tailwind.css', false);
        $response->assertSee('FACULTY STAFF', false);
        $response->assertSee('CAVITE STATE UNIVERSITY - NAIC CAMPUS', false);
    }

    public function test_admin_page_honours_department_scope(): void
    {
        $admin = $this->makeUser('admin');
        $this->makeUser('user');

        $response = $this->actingAs($admin)->get('/admin/document-tracker');

        $this->assertLegacySkeleton($response);
        $response->assertDontSee('No Faculty Members Found', false);
    }

    public function test_admin_without_department_sees_guidance_page(): void
    {
        $admin = $this->makeUser('admin', ['department_id' => null]);

        $this->actingAs($admin)
            ->get('/admin/document-tracker')
            ->assertOk()
            ->assertSee('No Department Assigned', false);
    }

    // -----------------------------------------------------------------
    // Superadmin tracker
    // -----------------------------------------------------------------

    public function test_superadmin_sees_department_selector_first(): void
    {
        $super = $this->makeUser('super_admin');

        $response = $this->actingAs($super)->get('/superadmin/document-tracker');

        $this->assertLegacySkeleton($response);
        $response->assertSee('Select a Department', false);

        $deptName = DB::table('departments')->where('id', $this->departmentId())->value('department_name');
        $response->assertSee($deptName, false);
    }

    public function test_superadmin_tracker_for_department_renders(): void
    {
        $super = $this->makeUser('super_admin');
        $this->makeUser('user');

        $response = $this->actingAs($super)
            ->get('/superadmin/document-tracker?department='.$this->departmentId());

        $this->assertLegacySkeleton($response);
        $response->assertSee('Document Submission Tracker', false);
        $response->assertSee('FACULTY STAFF', false);
        $response->assertSee('Overall Completion', false);
    }

    // -----------------------------------------------------------------
    // AJAX endpoints
    // -----------------------------------------------------------------

    public function test_file_details_requires_faculty_and_type(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)
            ->getJson('/document-tracker/file-details')
            ->assertStatus(400)
            ->assertJson(['success' => false]);
    }

    public function test_file_details_returns_json_for_faculty(): void
    {
        $admin = $this->makeUser('admin');
        $faculty = $this->makeUser('user');

        $response = $this->actingAs($admin)->getJson(
            '/document-tracker/file-details?faculty_id='.$faculty->id.'&document_type=Exam&semester=1st%20Semester%20AY%202026-2027'
        );

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure(['files']);
    }

    public function test_send_reminder_creates_notification(): void
    {
        $super = $this->makeUser('super_admin');
        $faculty = $this->makeUser('user');

        $response = $this->actingAs($super)
            ->post('/document-tracker/send-reminder', [
                'faculty_id' => $faculty->id,
                'document_type' => 'Exam',
            ]);

        $response->assertOk()
            ->assertJson(['success' => true]);

        $this->assertDatabaseHas('notifications', [
            'user_id' => $faculty->id,
            'type' => 'warning',
        ]);
    }

    public function test_all_submissions_returns_files(): void
    {
        $super = $this->makeUser('super_admin');
        $faculty = $this->makeUser('user');

        $this->actingAs($super)
            ->getJson("/document-tracker/all-submissions?faculty_id={$faculty->id}&academic_year=2026&semester=2nd%20Semester")
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure(['files']);
    }
}