<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * People listings module.
 *
 * Legacy equivalents:
 *   roles/admin/view-faculty-staff.php  -> GET /admin/view-faculty-staff
 *   roles/user/colleagues.php           -> GET /user/colleagues
 *
 * IMPORTANT: DatabaseTransactions (NOT RefreshDatabase) is used deliberately.
 * These tests run against the real legacy `odci_db` schema and must never
 * create, drop, or migrate anything -- every change is rolled back.
 * All fixture usernames are prefixed with `people_` so they cannot collide with
 * the unique username / email indexes of real accounts.
 */
class PeopleListingTest extends TestCase
{
    use DatabaseTransactions, WithFaker;

    private ?int $deptId = null;

    /**
     * Id of a real department (read-only). Fixtures are placed inside it so the
     * admin page's department-scoped query can actually list them.
     */
    private function departmentId(): int
    {
        if ($this->deptId === null) {
            $this->deptId = (int) DB::table('departments')->value('id');
        }

        return $this->deptId;
    }

    /**
     * Create an approved account row (rolled back after the test).
     * The default name parts render to the full name "People X. Fixture".
     *
     * @param  array<string, mixed>  $overrides
     */
    private function makeUser(string $role, array $overrides = []): User
    {
        $user = (new User())->forceFill(array_merge([
            'username' => 'people_'.$this->faker->unique()->numerify('####'),
            'email' => 'people_'.$this->faker->unique()->safeEmail(),
            'password' => 'MigrationTest@123',
            'role' => $role,
            'is_approved' => 1,
            'is_restricted' => 0,
            'failed_login_attempts' => 0,
            'name' => 'People',
            'mi' => 'X',
            'surname' => 'Fixture',
            'employee_id' => $this->faker->unique()->numerify('PEOP-#####'),
            'position' => 'Instructor',
            'department_id' => $this->departmentId(),
        ], $overrides));

        $user->save();

        return $user;
    }

    /**
     * Every legacy page is built from the same DOM skeleton:
     *   <section id="sidebar"> + <section id="content"> > <nav> + <main>
     * and the shared CVSU theme loads last.
     */
    private function assertLegacySkeleton($response): void
    {
        $response->assertOk();
        $response->assertSee('id="sidebar"', false);
        $response->assertSee('<section id="content">', false);
        $response->assertSee('build/assets/app-', false);
        $response->assertSee('boxicons@2.1.4', false);
        $response->assertSee('js/cvsu-theme.js', false);
    }

    // -----------------------------------------------------------------
    // Access control
    // -----------------------------------------------------------------

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/admin/view-faculty-staff')->assertRedirect('/login');
        $this->get('/user/colleagues')->assertRedirect('/login');
    }

    public function test_pages_are_gated_by_role(): void
    {
        // A faculty account cannot open the admin listing; it is bounced to
        // its own dashboard (legacy preventCrossRoleAccess behaviour).
        $faculty = $this->makeUser('user');

        $this->actingAs($faculty)
            ->get('/admin/view-faculty-staff')
            ->assertRedirect(route('user.dashboard'));

        // An admin cannot open the user-facing colleagues page.
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)
            ->get('/user/colleagues')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_super_admin_can_open_the_admin_page(): void
    {
        $superAdmin = $this->makeUser('super_admin');

        $this->actingAs($superAdmin)
            ->get('/admin/view-faculty-staff')
            ->assertOk();
    }

    // -----------------------------------------------------------------
    // Admin listing (legacy roles/admin/view-faculty-staff.php)
    // -----------------------------------------------------------------

    public function test_admin_page_renders_legacy_design_and_lists_faculty(): void
    {
        $admin = $this->makeUser('admin', [
            'name' => 'Boss',
            'mi' => '',
            'surname' => 'Admin',
            'position' => 'Department Head',
        ]);
        // Same department as the admin => listed on the admin page.
        $faculty = $this->makeUser('user');

        $response = $this->actingAs($admin)->get('/admin/view-faculty-staff');

        $this->assertLegacySkeleton($response);
        $response->assertSee('Faculty Staff Management');
        $response->assertSee('css/admin/faculty-staff.css', false);
        $response->assertSee('id="facultySearch"', false);
        $response->assertSee('id="facultyList"', false);
        $response->assertSee('id="selectAll"', false);
        $response->assertSee('id="bulkActions"', false);
        $response->assertSee('id="messageModal"', false);
        $response->assertSee('id="statsModal"', false);
        $response->assertSee('class="faculty-card', false);
        $response->assertSee('People X. Fixture');
    }

    // -----------------------------------------------------------------
    // User colleagues list (legacy roles/user/colleagues.php)
    // -----------------------------------------------------------------

    public function test_user_colleagues_page_renders_legacy_design_and_lists_colleagues(): void
    {
        $viewer = $this->makeUser('user', [
            'name' => 'Actor',
            'mi' => '',
            'surname' => 'Viewer',
        ]);
        // Approved account (any department) => visible to the viewer.
        $colleague = $this->makeUser('user');

        $response = $this->actingAs($viewer)->get('/user/colleagues');

        $this->assertLegacySkeleton($response);
        $response->assertSee('Colleagues');
        $response->assertSee('css/user/colleagues.css', false);
        $response->assertSee('class="box-info"', false);
        $response->assertSee('class="filter-form"', false);
        $response->assertSee('class="colleagues-grid"', false);
        $response->assertSee('class="colleague-card"', false);
        $response->assertSee('People X. Fixture');
    }

    // -----------------------------------------------------------------
    // AJAX endpoint used by public/js/admin/view-staff.js
    // -----------------------------------------------------------------

    public function test_stats_endpoint_returns_json_for_a_faculty_member(): void
    {
        $admin = $this->makeUser('admin');
        $faculty = $this->makeUser('user');

        $response = $this->actingAs($admin)->post('/admin/view-faculty-staff/stats', [
            'action' => 'get_faculty_stats',
            'faculty_id' => $faculty->id,
        ]);

        $response->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure(['success', 'data' => ['completion_rate']]);
    }

    // -----------------------------------------------------------------
    // Ported assets
    // -----------------------------------------------------------------

    public function test_ported_assets_exist(): void
    {
        $assets = [
            'css/admin/faculty-staff.css',
            'css/user/colleagues.css',
            'js/admin/view-staff.js',
        ];

        foreach ($assets as $asset) {
            $this->assertFileExists(public_path($asset), "Missing ported asset: {$asset}");
        }
    }
}