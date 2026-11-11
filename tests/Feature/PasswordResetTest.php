<?php

namespace Tests\Feature;

use App\Mail\PasswordResetMail;
use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Port of login/forgot_password.php + login/reset_password.php.
 *
 * Runs against the real legacy `odci_db` with DatabaseTransactions.
 * Mail is faked so no SMTP is attempted; tokens are SHA-256 hashes with a
 * one-hour expiry, exactly like the legacy flow.
 */
class PasswordResetTest extends TestCase
{
    use DatabaseTransactions, WithFaker;

    private function makeApprovedUser(): User
    {
        $user = new User();
        $user->forceFill([
            'username' => 'reset_'.$this->faker->unique()->numerify('####'),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => 'MigrationTest@123',
            'role' => 'user',
            'is_approved' => 1,
            'is_restricted' => 0,
            'failed_login_attempts' => 0,
            'name' => 'Maria',
            'surname' => 'Santos',
            'employee_id' => $this->faker->unique()->numerify('RESET-#####'),
        ])->save();

        return $user;
    }

    private function makeUnapprovedUser(): User
    {
        $user = $this->makeApprovedUser();
        $user->forceFill(['is_approved' => 0])->save();

        return $user;
    }

    /** Returns the RAW token after storing its SHA-256 hash on the user. */
    private function issueToken(User $user, ?\DateTimeInterface $expiresAt = null): string
    {
        $raw = bin2hex(random_bytes(32));

        $user->forceFill([
            'password_reset_token' => hash('sha256', $raw),
            'password_reset_expires' => $expiresAt ?? now()->addHour(),
        ])->save();

        return $raw;
    }

    // -----------------------------------------------------------------
    // GET /forgot-password
    // -----------------------------------------------------------------

    public function test_forgot_password_page_renders_legacy_dom(): void
    {
        $this->get('/forgot-password')
            ->assertOk()
            ->assertSee('Forgot Your Password?', false)
            ->assertSee('id="forgotForm"', false)
            ->assertSee('Send Reset Instructions', false)
            ->assertSee('id="forgotBtn"', false)
            ->assertSee('css/login.css', false);
    }

    public function test_logged_in_user_visiting_forgot_password_is_redirected(): void
    {
        $user = $this->makeApprovedUser();

        $this->actingAs($user)->get('/forgot-password')
            ->assertRedirect(route('user.dashboard'));
    }

    // -----------------------------------------------------------------
    // POST /forgot-password
    // -----------------------------------------------------------------

    public function test_invalid_email_is_rejected(): void
    {
        $this->post('/forgot-password', ['email' => 'not-an-email'])
            ->assertRedirect()
            ->assertSessionHas('error', 'Please enter a valid email address.');
    }

    public function test_approved_user_receives_reset_token_and_email(): void
    {
        Mail::fake();
        $user = $this->makeApprovedUser();

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('success');

        Mail::assertSent(PasswordResetMail::class, function ($mail) use ($user) {
            return $mail->hasTo($user->email);
        });

        $fresh = $user->fresh();
        $this->assertNotEmpty($fresh->password_reset_token);
        $this->assertNotNull($fresh->password_reset_expires);

        $activity = ActivityLog::query()
            ->where('action', 'password_reset_request')
            ->where('resource_id', $user->id)
            ->latest('id')
            ->first();
        $this->assertNotNull($activity);
    }

    public function test_unapproved_account_gets_no_email_but_generic_success(): void
    {
        Mail::fake();
        $user = $this->makeUnapprovedUser();

        $this->post('/forgot-password', ['email' => $user->email])
            ->assertRedirect()
            ->assertSessionHas('success');

        Mail::assertNothingSent();
        $this->assertNull($user->fresh()->password_reset_token);
    }

    public function test_unknown_email_gets_generic_success_without_mail(): void
    {
        Mail::fake();

        $this->post('/forgot-password', ['email' => 'nobody@example.com'])
            ->assertRedirect()
            ->assertSessionHas('success');

        Mail::assertNothingSent();
    }

    // -----------------------------------------------------------------
    // GET /reset-password/{token}
    // -----------------------------------------------------------------

    public function test_reset_page_with_valid_token_shows_greeting(): void
    {
        $user = $this->makeApprovedUser();
        $raw = $this->issueToken($user);

        $this->get('/reset-password/'.$raw)
            ->assertOk()
            ->assertSee('Reset Your Password', false)
            ->assertSee('Hello '.$user->name, false)
            ->assertSee('id="resetForm"', false);
    }

    public function test_reset_page_without_token_errors(): void
    {
        $this->get('/reset-password')
            ->assertOk()
            ->assertSee('No password reset token provided.', false);
    }

    public function test_reset_page_with_invalid_token_errors(): void
    {
        $this->get('/reset-password/not-a-real-token')
            ->assertOk()
            ->assertSee('Invalid password reset link.', false);
    }

    public function test_reset_page_with_expired_token_errors(): void
    {
        $user = $this->makeApprovedUser();
        $raw = $this->issueToken($user, now()->subMinute());

        $this->get('/reset-password/'.$raw)
            ->assertOk()
            ->assertSee('Password reset link has expired. Please request a new one.', false);
    }

    // -----------------------------------------------------------------
    // POST /reset-password/{token}
    // -----------------------------------------------------------------

    public function test_invalid_token_cannot_reset_password(): void
    {
        $this->post('/reset-password/bogus', [
            'password' => 'NewSecret@123',
            'confirm_password' => 'NewSecret@123',
        ])->assertOk()->assertSee('Invalid password reset link.', false);
    }

    public function test_weak_password_is_rejected(): void
    {
        $user = $this->makeApprovedUser();
        $raw = $this->issueToken($user);

        $this->post('/reset-password/'.$raw, [
            'password' => 'weak',
            'confirm_password' => 'weak',
        ])->assertOk()
            ->assertSee('Password must be at least 8 characters with uppercase, lowercase, and number.', false);
    }

    public function test_mismatched_confirmation_is_rejected(): void
    {
        $user = $this->makeApprovedUser();
        $raw = $this->issueToken($user);

        $this->post('/reset-password/'.$raw, [
            'password' => 'NewSecret@123',
            'confirm_password' => 'Different@456',
        ])->assertOk()
            ->assertSee('Passwords do not match.', false);
    }

    public function test_valid_reset_updates_password_and_clears_token(): void
    {
        $user = $this->makeApprovedUser();
        $raw = $this->issueToken($user);

        $this->post('/reset-password/'.$raw, [
            'password' => 'NewSecret@123',
            'confirm_password' => 'NewSecret@123',
        ])->assertOk()
            ->assertSee('Password reset successfully! You can now log in with your new password.', false);

        $fresh = $user->fresh();
        $this->assertTrue(Hash::check('NewSecret@123', $fresh->password));
        $this->assertNull($fresh->password_reset_token);
        $this->assertNull($fresh->password_reset_expires);

        $activity = ActivityLog::query()
            ->where('action', 'password_reset')
            ->where('resource_id', $user->id)
            ->latest('id')
            ->first();
        $this->assertNotNull($activity);
    }

    public function test_used_token_is_single_use(): void
    {
        $user = $this->makeApprovedUser();
        $raw = $this->issueToken($user);

        $this->post('/reset-password/'.$raw, [
            'password' => 'NewSecret@123',
            'confirm_password' => 'NewSecret@123',
        ]);

        // Second attempt with the same (now cleared) token must fail.
        $this->post('/reset-password/'.$raw, [
            'password' => 'Another@789',
            'confirm_password' => 'Another@789',
        ])->assertOk()
            ->assertSee('Invalid password reset link.', false);
    }
}