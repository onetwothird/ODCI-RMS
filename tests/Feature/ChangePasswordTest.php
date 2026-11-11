<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Port of /changepass.php.
 *
 * Legacy verified fields in a fixed order and rendered its message inline
 * (HTTP 200) on failure; on success it flashed the message through the
 * session, regenerated the session id, and redirected (PRG).
 */
class ChangePasswordTest extends TestCase
{
    use DatabaseTransactions, WithFaker;

    private const PASSWORD = 'MigrationTest@123';

    private function makeUser(string $role = 'user'): User
    {
        $user = new User();
        $user->forceFill([
            'username' => 'changepass_'.$this->faker->unique()->numerify('####'),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => self::PASSWORD,
            'role' => $role,
            'is_approved' => 1,
            'is_restricted' => 0,
            'failed_login_attempts' => 0,
            'name' => 'Test',
            'surname' => 'User',
            'employee_id' => $this->faker->unique()->numerify('CHANGE-#####'),
        ])->save();

        return $user;
    }

    // -----------------------------------------------------------------
    // GET /changepass
    // -----------------------------------------------------------------

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/changepass')->assertRedirect(route('login'));
    }

    public function test_page_renders_legacy_dom_for_each_role(): void
    {
        foreach (['user', 'admin', 'super_admin'] as $role) {
            $user = $this->makeUser($role);

            $this->actingAs($user)->get('/changepass')
                ->assertOk()
                ->assertSee('password-reset-page', false)
                ->assertSee('id="current-password"', false)
                ->assertSee('id="new-password"', false)
                ->assertSee('id="confirm-password"', false)
                ->assertSee('Update password', false)
                ->assertSee('Return to dashboard', false);
        }
    }

    // -----------------------------------------------------------------
    // POST /changepass - legacy messages, verbatim
    // -----------------------------------------------------------------

    public function test_empty_fields_message(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post('/changepass', [
            'current_password' => '',
            'new_password' => '',
            'confirm_password' => '',
        ])->assertOk()->assertSee('Complete all password fields.', false);
    }

    public function test_short_or_long_new_password_message(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post('/changepass', [
            'current_password' => self::PASSWORD,
            'new_password' => 'short',
            'confirm_password' => 'short',
        ])->assertOk()->assertSee('Use a new password between 8 and 72 bytes.', false);

        $this->actingAs($user)->post('/changepass', [
            'current_password' => self::PASSWORD,
            'new_password' => str_repeat('a', 73),
            'confirm_password' => str_repeat('a', 73),
        ])->assertOk()->assertSee('Use a new password between 8 and 72 bytes.', false);
    }

    public function test_mismatched_confirmation_message(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post('/changepass', [
            'current_password' => self::PASSWORD,
            'new_password' => 'BrandNew@123',
            'confirm_password' => 'BrandNew@456',
        ])->assertOk()->assertSee('The password confirmation does not match.', false);
    }

    public function test_wrong_current_password_message(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post('/changepass', [
            'current_password' => 'WrongPass@999',
            'new_password' => 'BrandNew@123',
            'confirm_password' => 'BrandNew@123',
        ])->assertOk()->assertSee('Your current password is incorrect.', false);
    }

    public function test_reusing_current_password_message(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post('/changepass', [
            'current_password' => self::PASSWORD,
            'new_password' => self::PASSWORD,
            'confirm_password' => self::PASSWORD,
        ])->assertOk()->assertSee('Choose a new password that differs from your current password.', false);
    }

    public function test_successful_change_updates_password_and_flashes(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post('/changepass', [
            'current_password' => self::PASSWORD,
            'new_password' => 'BrandNew@123',
            'confirm_password' => 'BrandNew@123',
        ])->assertRedirect(route('changepass'));

        $fresh = $user->fresh();
        $this->assertTrue(Hash::check('BrandNew@123', $fresh->password));

        $this->actingAs($user)->get('/changepass')
            ->assertSee('Your password was changed successfully.', false);

        $activity = ActivityLog::query()
            ->where('action', 'password_change')
            ->where('resource_id', $user->id)
            ->latest('id')
            ->first();
        $this->assertNotNull($activity, 'password_change activity must be logged');
    }

    public function test_new_password_works_for_next_login(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->post('/changepass', [
            'current_password' => self::PASSWORD,
            'new_password' => 'BrandNew@123',
            'confirm_password' => 'BrandNew@123',
        ]);

        $this->post('/logout');

        $this->post('/login', [
            'username' => $user->username,
            'password' => 'BrandNew@123',
        ])->assertRedirect(route('user.dashboard'));
    }
}