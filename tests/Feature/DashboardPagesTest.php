<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

/**
 * Verifies the three role dashboards render the legacy ODCI design.
 *
 * IMPORTANT: DatabaseTransactions (NOT RefreshDatabase) is used deliberately.
 * These tests run against the real legacy `odci_db` schema and must never
 * create, drop, or migrate anything -- every change is rolled back.
 */
class DashboardPagesTest extends TestCase
{
    use DatabaseTransactions, WithFaker;

    private function makeUser(string $role): User
    {
        $user = new User();

        $user->forceFill([
            'username' => 'test_dash_'.$role.'_'.$this->faker->unique()->numerify('####'),
            'email' => 'test_dash_'.$role.'_'.$this->faker->unique()->safeEmail(),
            'password' => 'MigrationTest@123',
            'role' => $role,
            'is_approved' => 1,
            'is_restricted' => 0,
            'failed_login_attempts' => 0,
            'name' => 'Designer',
            'surname' => 'Tester',
            'employee_id' => $this->faker->unique()->numerify('EMP-#####'),
        ])->save();

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
    // Super admin dashboard
    // -----------------------------------------------------------------

    public function test_superadmin_dashboard_renders_legacy_design(): void
    {
        $user = $this->makeUser('super_admin');

        $response = $this->actingAs($user)->get('/superadmin/dashboard');

        $this->assertLegacySkeleton($response);
        $response->assertSee('Super Admin Dashboard');
        $response->assertSee('stats-grid');
        $response->assertSee('Recent System Activities');
        $response->assertSee('Quick Actions');
        $response->assertSee('Department Statistics');
        $response->assertSee('System Information');
        $response->assertSee('css/superadmin/dashboard.css', false);
    }

    // -----------------------------------------------------------------
    // Admin dashboard
    // -----------------------------------------------------------------

    public function test_admin_dashboard_renders_legacy_design(): void
    {
        $user = $this->makeUser('admin');

        $response = $this->actingAs($user)->get('/admin/dashboard');

        $this->assertLegacySkeleton($response);
        $response->assertSee('Welcome Back, Designer!');
        $response->assertSee('box-info');
        $response->assertSee('Storage Analytics');
        $response->assertSee('Account Information');
        $response->assertSee('Recent Notifications');
        $response->assertSee('css/admin/pages/dashboard/stats_card.css', false);
        $response->assertSee('css/admin/components/sidebar.css', false);
    }

    // -----------------------------------------------------------------
    // User dashboard
    // -----------------------------------------------------------------

    public function test_user_dashboard_renders_legacy_design(): void
    {
        $user = $this->makeUser('user');

        $response = $this->actingAs($user)->get('/user/dashboard');

        $this->assertLegacySkeleton($response);
        $response->assertSee('Welcome, Designer Tester!');
        $response->assertSee('box-info');
        $response->assertSee('Recent Files');
        $response->assertSee('Quick Actions');
        $response->assertSee('Account Information');
        $response->assertSee('System Status');
        $response->assertSee('css/user/pages/dashboard/file_table.css', false);
        $response->assertSee('css/user/components/sidebar.css', false);
    }

    // -----------------------------------------------------------------
    // Role gates still hold with the new layouts
    // -----------------------------------------------------------------

    public function test_dashboards_are_gated_by_role(): void
    {
        // RoleMiddleware mirrors legacy preventCrossRoleAccess(): it redirects
        // the visitor to their OWN dashboard with error=access_denied.
        $faculty = $this->makeUser('user');
        $admin = $this->makeUser('admin');

        $this->actingAs($faculty)->get('/admin/dashboard')
            ->assertRedirect(route('user.dashboard'));
        $this->actingAs($faculty)->get('/superadmin/dashboard')
            ->assertRedirect(route('user.dashboard'));
        $this->actingAs($admin)->get('/superadmin/dashboard')
            ->assertRedirect(route('admin.dashboard'));
        $this->actingAs($admin)->get('/user/dashboard')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/superadmin/dashboard')->assertRedirect('/login');
        $this->get('/admin/dashboard')->assertRedirect('/login');
        $this->get('/user/dashboard')->assertRedirect('/login');
    }

    // -----------------------------------------------------------------
    // Legacy assets that every layout depends on
    // -----------------------------------------------------------------

    public function test_vite_bundle_is_built_with_tailwind_and_design_system(): void
    {
        $manifest = json_decode((string) file_get_contents(public_path('build/manifest.json')), true);

        $this->assertArrayHasKey('resources/css/app.css', $manifest, 'Run `npm run build`.');
        $css = file_get_contents(public_path('build/'.$manifest['resources/css/app.css']['file']));
        $this->assertStringContainsString('--cvsu-green-600', $css, 'shared design system is bundled');
        $this->assertStringContainsString('.ui-btn-primary', $css, 'Tailwind component layer is bundled');
    }

    public function test_legacy_stylesheet_assets_exist(): void
    {
        $assets = [
            'js/cvsu-theme.js',
            'css/superadmin/base.css',
            'css/superadmin/sidebar.css',
            'css/superadmin/navbar.css',
            'css/superadmin/dashboard.css',
            'css/superadmin/users.css',
            'css/superadmin/departments-page.css',
            'css/admin/base.css',
            'css/admin/components/sidebar.css',
            'css/admin/components/navbar.css',
            'css/admin/pages/dashboard/stats_card.css',
            'css/user/components/sidebar.css',
            'css/user/components/navbar.css',
            'css/user/pages/dashboard/stats_card.css',
            'img/cvsu-logo.png',
        ];

        foreach ($assets as $asset) {
            $this->assertFileExists(public_path($asset), "Missing legacy asset: {$asset}");
        }
    }
}
