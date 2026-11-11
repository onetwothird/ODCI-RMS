<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Profile / account settings module.
 *
 * Legacy equivalents:
 *   roles/user/settings.php       -> GET /user/settings  (user)
 *   roles/admin/profile.php       -> GET /admin/profile  (admin)
 *   roles/superadmin/settings.php -> GET /superadmin/settings (super_admin)
 *
 * Uses DatabaseTransactions against the live odci_db; usernames are prefixed
 * `pr_` so they never collide with real accounts. Physical profile images
 * written to storage are removed in tearDown().
 */
class ProfileModuleTest extends TestCase
{
    use DatabaseTransactions, WithFaker;

    /** Physical files created by image-upload tests. */
    private array $createdUploads = [];

    protected function tearDown(): void
    {
        foreach ($this->createdUploads as $absolutePath) {
            if (is_file($absolutePath)) {
                @unlink($absolutePath);
            }
            $dir = dirname($absolutePath);
            while (str_contains(dirname($dir), 'uploads')) {
                @rmdir($dir);
                $dir = dirname($dir);
                if ($dir === dirname($dir)) {
                    break;
                }
            }
        }

        parent::tearDown();
    }

    private function departmentId(): int
    {
        return (int) DB::table('departments')->where('is_active', 1)->value('id') ?: 1;
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function makeUser(string $role, array $overrides = []): User
    {
        $user = (new User())->forceFill(array_merge([
            'username' => 'pr_'.$this->faker->unique()->numerify('####'),
            'email' => 'pr_'.$this->faker->unique()->safeEmail(),
            'password' => 'MigrationTest@123',
            'role' => $role,
            'is_approved' => 1,
            'is_restricted' => 0,
            'failed_login_attempts' => 0,
            'name' => 'Profile',
            'mi' => 'P',
            'surname' => 'Fixture',
            'employee_id' => $this->faker->unique()->numerify('PRF-#####'),
            'position' => 'Instructor',
            'department_id' => $this->departmentId(),
        ], $overrides));

        $user->save();

        return $user;
    }

    /* ------------------------------------------------------------------
     | Gating
     |------------------------------------------------------------------ */

    public function test_guests_are_redirected_to_login(): void
    {
        $this->get('/user/settings')->assertRedirect('/login');
        $this->get('/admin/profile')->assertRedirect('/login');
        $this->get('/superadmin/settings')->assertRedirect('/login');
    }

    public function test_pages_are_gated_by_role(): void
    {
        $user = $this->makeUser('user');
        $admin = $this->makeUser('admin');
        $super = $this->makeUser('super_admin');

        $this->actingAs($user)->get('/admin/profile')->assertRedirect('/user/dashboard');
        $this->actingAs($user)->get('/superadmin/settings')->assertRedirect('/user/dashboard');
        $this->actingAs($admin)->get('/user/settings')->assertRedirect('/admin/dashboard');
        $this->actingAs($admin)->get('/superadmin/settings')->assertRedirect('/admin/dashboard');
        $this->actingAs($super)->get('/user/settings')->assertRedirect('/superadmin/dashboard');

        // /admin/profile is role:admin,super_admin — the super admin is allowed.
        $this->actingAs($super)->get('/admin/profile')->assertOk();

        $this->actingAs($user)->get('/user/settings')->assertOk();
        $this->actingAs($admin)->get('/admin/profile')->assertOk();
        $this->actingAs($super)->get('/superadmin/settings')->assertOk();
    }

    /* ------------------------------------------------------------------
     | Rendering
     |------------------------------------------------------------------ */

    public function test_user_settings_page_renders(): void
    {
        $user = $this->makeUser('user');

        $this->actingAs($user)->get('/user/settings')
            ->assertOk()
            ->assertSee('Account Settings')
            ->assertSee($user->email)
            ->assertSee('Profile Photo')
            ->assertSee('Change Password');
    }

    public function test_admin_profile_page_renders_hire_date_field(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->get('/admin/profile')
            ->assertOk()
            ->assertSee('My Profile')
            ->assertSee('Hire Date')
            ->assertSee('Account Info');
    }

    public function test_superadmin_settings_page_renders_all_tabs(): void
    {
        $super = $this->makeUser('super_admin');

        $this->actingAs($super)->get('/superadmin/settings')
            ->assertOk()
            ->assertSee('System Settings')
            ->assertSee('Maintenance Mode')
            ->assertSee('Session Timeout')
            ->assertSee('System Statistics')
            ->assertSee('panel-statistics');
    }

    /* ------------------------------------------------------------------
     | Profile actions
     |------------------------------------------------------------------ */

    public function test_user_can_update_profile(): void
    {
        $user = $this->makeUser('user');

        $this->actingAs($user)->post('/profile/update', [
            'action' => 'update_profile',
            'name' => 'Updated',
            'mi' => 'X',
            'surname' => 'Name',
            'email' => $user->email,
            'phone' => '09170000000',
            'position' => 'Professor',
            'department_id' => $user->department_id,
            'date_of_birth' => '1990-01-15',
        ])->assertRedirect(route('user.settings'));

        $this->assertSame('Updated', $user->fresh()->name);
        $this->assertSame('Professor', $user->fresh()->position);
        $this->assertSame('1990-01-15', $user->fresh()->date_of_birth->toDateString());
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'profile_update',
        ]);
    }

    public function test_user_cannot_use_email_of_another_user(): void
    {
        $user = $this->makeUser('user');
        $other = $this->makeUser('user');

        $this->actingAs($user)->post('/profile/update', [
            'action' => 'update_profile',
            'name' => 'Updated',
            'mi' => '',
            'surname' => 'Name',
            'email' => $other->email,
        ])->assertRedirect(route('user.settings'))
            ->assertSessionHas('error_message');

        $this->assertSame($user->email, $user->fresh()->email);
    }

    public function test_user_can_change_password(): void
    {
        $user = $this->makeUser('user');

        $this->actingAs($user)->post('/profile/update', [
            'action' => 'change_password',
            'current_password' => 'MigrationTest@123',
            'new_password' => 'NewPassword@456',
            'confirm_password' => 'NewPassword@456',
        ])->assertRedirect(route('user.settings'));

        $this->assertTrue(password_verify('NewPassword@456', $user->fresh()->password));
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $user->id,
            'action' => 'password_change',
        ]);
    }

    public function test_wrong_current_password_is_rejected(): void
    {
        $user = $this->makeUser('user');

        $this->actingAs($user)->post('/profile/update', [
            'action' => 'change_password',
            'current_password' => 'wrong-password',
            'new_password' => 'NewPassword@456',
            'confirm_password' => 'NewPassword@456',
        ])->assertRedirect(route('user.settings'))
            ->assertSessionHas('error_message');

        $this->assertTrue(password_verify('MigrationTest@123', $user->fresh()->password));
    }

    public function test_user_can_upload_profile_image(): void
    {
        $user = $this->makeUser('user');
        $storedDir = storage_path('app/private/uploads/profiles');

        $response = $this->actingAs($user)->post('/profile/update', [
            'action' => 'upload_image',
            'profile_image' => UploadedFile::fake()->image('avatar.png', 100, 100),
        ]);

        $response->assertRedirect(route('user.settings'));
        $fresh = $user->fresh();
        $this->assertNotNull($fresh->profile_image);
        $this->assertStringStartsWith('uploads/profiles/profile_', $fresh->profile_image);

        $absolute = $storedDir.DIRECTORY_SEPARATOR.basename($fresh->profile_image);
        $this->assertFileExists($absolute);
        $this->createdUploads[] = $absolute;

        // The new image resolves through the profile.image route.
        $this->get(route('profile.image', ['path' => $fresh->profile_image]))->assertOk();
    }

    public function test_user_can_remove_profile_image(): void
    {
        $user = $this->makeUser('user', ['profile_image' => 'uploads/profiles/profile_stored.png']);

        $this->actingAs($user)->post('/profile/update', ['action' => 'remove_image'])
            ->assertRedirect(route('user.settings'));

        $this->assertNull($user->fresh()->profile_image);
    }

    public function test_admin_can_clear_login_attempts(): void
    {
        $admin = $this->makeUser('admin', ['failed_login_attempts' => 5, 'account_locked_until' => now()]);

        $this->actingAs($admin)->post('/profile/update', ['action' => 'clear_login_attempts'])
            ->assertRedirect(route('admin.profile'));

        $fresh = $admin->fresh();
        $this->assertSame(0, (int) $fresh->failed_login_attempts);
        $this->assertNull($fresh->account_locked_until);
    }

    /* ------------------------------------------------------------------
     | System settings (super admin)
     |------------------------------------------------------------------ */

    public function test_superadmin_can_update_system_settings(): void
    {
        $super = $this->makeUser('super_admin');

        $this->actingAs($super)->post('/superadmin/settings', [
            'system_settings' => [
                'maintenance_mode' => '1',
                'max_file_size' => '50',
                'session_timeout' => '45',
                'email_notifications' => '1',
                'user_registration' => '0',
                'auto_approve_users' => '1',
                'backup_retention_days' => '14',
            ],
        ])->assertRedirect(route('superadmin.settings'))
            ->assertSessionHas('success_message');

        $this->assertSame('1', DB::table('system_settings')->where('setting_key', 'maintenance_mode')->value('setting_value'));
        $this->assertSame((string) (50 * 1048576), DB::table('system_settings')->where('setting_key', 'max_file_size')->value('setting_value'));
        $this->assertSame((string) (45 * 60), DB::table('system_settings')->where('setting_key', 'session_timeout')->value('setting_value'));
        $this->assertSame('0', DB::table('system_settings')->where('setting_key', 'user_registration')->value('setting_value'));
        $this->assertSame((string) 14, DB::table('system_settings')->where('setting_key', 'backup_retention_days')->value('setting_value'));
        $this->assertDatabaseHas('activity_logs', [
            'user_id' => $super->id,
            'action' => 'system_settings_update',
        ]);
    }

    public function test_non_superadmin_cannot_update_system_settings(): void
    {
        $admin = $this->makeUser('admin');

        $this->actingAs($admin)->post('/superadmin/settings', [
            'system_settings' => ['maintenance_mode' => '1'],
        ])->assertRedirect('/admin/dashboard');
    }
}