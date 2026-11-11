<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Superadmin activity logs module.
 *
 * Legacy equivalent: roles/superadmin/activity_logs.php +
 * assets/script/activity_logs.php?ajax=get_logs|get_stats|export_logs|clear_old_logs
 *
 * Uses DatabaseTransactions against the live odci_db; usernames are prefixed
 * `al_` so they never collide with real accounts.
 */
class ActivityLogsModuleTest extends TestCase
{
    use DatabaseTransactions, WithFaker;

    private function makeUser(string $role, array $overrides = []): User
    {
        $user = (new User())->forceFill(array_merge([
            'username' => 'al_'.$this->faker->unique()->numerify('####'),
            'email' => 'al_'.$this->faker->unique()->safeEmail(),
            'password' => 'MigrationTest@123',
            'role' => $role,
            'is_approved' => 1,
            'is_restricted' => 0,
            'failed_login_attempts' => 0,
            'name' => 'Activity',
            'mi' => 'A',
            'surname' => 'Fixture',
            'employee_id' => $this->faker->unique()->numerify('ACT-#####'),
            'position' => 'Instructor',
            'department_id' => (int) DB::table('departments')->where('is_active', 1)->value('id') ?: 1,
        ], $overrides));

        $user->save();

        return $user;
    }

    private function makeLog(int $userId, array $overrides = []): void
    {
        DB::table('activity_logs')->insert(array_merge([
            'user_id' => $userId,
            'action' => 'file_upload',
            'resource_type' => 'file',
            'resource_id' => 1,
            'description' => 'Uploaded a document',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'phpunit',
            'created_at' => now(),
        ], $overrides));
    }

    /* ------------------------------------------------------------------
     | Gating
     |------------------------------------------------------------------ */

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/superadmin/activity-logs')->assertRedirect('/login');
    }

    public function test_page_is_gated_for_non_superadmin(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->get('/superadmin/activity-logs')->assertRedirect('/admin/dashboard');
        $this->actingAs($admin)->get('/superadmin/activity-logs/data')->assertRedirect('/admin/dashboard');
    }

    /* ------------------------------------------------------------------
     | Page + endpoints
     |------------------------------------------------------------------ */

    public function test_page_renders(): void
    {
        $super = $this->makeUser('super_admin');

        $this->actingAs($super)->get('/superadmin/activity-logs')
            ->assertOk()
            ->assertSee('Activity Logs')
            ->assertSee('Clear Old Logs')
            ->assertSee('Log Entries');
    }

    public function test_logs_endpoint_returns_paginated_json(): void
    {
        $super = $this->makeUser('super_admin');
        $before = (int) DB::table('activity_logs')->count();
        $this->makeLog($super->id, ['action' => 'file_upload', 'description' => 'Uploaded syllabus.pdf']);

        $this->actingAs($super)->getJson('/superadmin/activity-logs/data?page=1&limit=25')
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonFragment(['description' => 'Uploaded syllabus.pdf'])
            ->assertJsonPath('pagination.total_records', $before + 1)
            ->assertJsonPath('pagination.current_page', 1);
    }

    public function test_logs_endpoint_respects_filters(): void
    {
        $super = $this->makeUser('super_admin');
        $user = $this->makeUser('user');
        $this->makeLog($super->id, ['action' => 'file_upload', 'description' => 'Super upload']);

        // user_id filter is exact: a brand-new user has exactly one log.
        $this->actingAs($super)->getJson('/superadmin/activity-logs/data?user_id='.$user->id)
            ->assertOk()
            ->assertJsonPath('pagination.total_records', 0);

        $this->makeLog($user->id, ['action' => 'user_login', 'description' => 'User login']);

        $this->actingAs($super)->getJson('/superadmin/activity-logs/data?user_id='.$user->id)
            ->assertOk()
            ->assertJsonPath('pagination.total_records', 1)
            ->assertJsonFragment(['description' => 'User login']);

        // resource_type filter counts against live data, so derive the expectation.
        $beforeFile = (int) DB::table('activity_logs')->where('resource_type', 'file')->count();
        $this->makeLog($super->id, ['action' => 'file_upload', 'description' => 'Second upload']);
        $this->makeLog($user->id, ['action' => 'user_login', 'description' => 'Another login']);

        $this->actingAs($super)->getJson('/superadmin/activity-logs/data?resource_type=file')
            ->assertOk()
            ->assertJsonPath('pagination.total_records', $beforeFile + 2)
            ->assertJsonFragment(['action' => 'file_upload']);
    }

    public function test_stats_endpoint_returns_counts(): void
    {
        $super = $this->makeUser('super_admin');
        $this->makeLog($super->id);

        $this->actingAs($super)->getJson('/superadmin/activity-logs/stats')
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonStructure([
                'stats' => ['today_count', 'week_count', 'month_count', 'top_users', 'resource_breakdown'],
            ]);
    }

    public function test_export_endpoint_returns_csv(): void
    {
        $super = $this->makeUser('super_admin');
        $this->makeLog($super->id, ['description' => 'Uploaded report.csv']);

        $this->actingAs($super)->get('/superadmin/activity-logs/export')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8')
            ->assertHeader('Content-Disposition', 'attachment; filename="activity-logs.csv"')
            ->assertSee('Uploaded report.csv');
    }

    public function test_clear_old_logs_removes_expired_logs_only(): void
    {
        $super = $this->makeUser('super_admin');
        $this->makeLog($super->id, ['created_at' => now()->subDays(60)]);
        $this->makeLog($super->id, ['created_at' => now()->subDays(5)]);

        // The live table already holds expired rows, so only assert success and
        // that OUR old entry was purged while the recent one survived. Everything
        // is rolled back by DatabaseTransactions afterwards.
        $this->actingAs($super)->postJson('/superadmin/activity-logs/clear', ['days' => 30])
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('message', fn ($m) => str_contains($m, 'older than 30'));

        $remaining = DB::table('activity_logs')
            ->where('user_id', $super->id)
            ->get();

        // The 60-day-old entry is purged; the recent entry survives (and the
        // controller's own audit() entry is recent). None may predate the cutoff.
        $this->assertFalse($remaining->contains(fn ($l) => strtotime($l->created_at) < now()->subDays(30)->timestamp));
        $this->assertNotEmpty($remaining->firstWhere('action', 'file_upload'));
        $this->assertNotEmpty($remaining->firstWhere('action', 'clear_old_logs'));
    }
}