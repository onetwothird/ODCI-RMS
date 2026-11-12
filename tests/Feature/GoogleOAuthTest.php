<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Port of login/script/google_oauth.php + google_callback.php and the
 * `?verify=1&token=...` handler in login/script/login_function.php.
 *
 * Google credentials are faked via Http::fake() - real credentials come from
 * the environment and are NOT required for the suites to run.
 */
class GoogleOAuthTest extends TestCase
{
    use DatabaseTransactions, WithFaker;

    private function fakeGoogle(array $userInfo, int $tokenStatus = 200): void
    {
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response(
                $tokenStatus === 200 ? ['access_token' => 'fake-access-token', 'expires_in' => 3600] : ['error' => 'invalid_grant'],
                $tokenStatus
            ),
            'https://www.googleapis.com/oauth2/v2/userinfo' => Http::response($userInfo, 200),
        ]);
    }

    private function makeApprovedUser(string $email): User
    {
        $user = new User();
        $user->forceFill([
            'username' => 'oauth_'.$this->faker->unique()->numerify('####'),
            'email' => $email,
            'password' => 'MigrationTest@123',
            'role' => 'user',
            'is_approved' => 1,
            'is_restricted' => 0,
            'failed_login_attempts' => 0,
            'name' => 'Test',
            'surname' => 'User',
            'employee_id' => $this->faker->unique()->numerify('OAUTH-#####'),
        ])->save();

        return $user;
    }

    // -----------------------------------------------------------------
    // GET /login/google
    // -----------------------------------------------------------------

    public function test_redirect_endpoint_without_client_id_errors(): void
    {
        config(['services.google.client_id' => '']);

        $this->get('/login/google')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Google sign-in is not configured.');
    }

    public function test_redirect_endpoint_bounces_to_google_consent(): void
    {
        config([
            'services.google.client_id' => 'abc.apps.googleusercontent.com',
            'services.google.client_secret' => 'secret',
            'services.google.redirect' => 'http://localhost/odci-laravel/login/google/callback',
        ]);

        $this->get('/login/google')
            ->assertRedirect('https://accounts.google.com/o/oauth2/auth?'
                .http_build_query([
                    'client_id' => 'abc.apps.googleusercontent.com',
                    'redirect_uri' => 'http://localhost/odci-laravel/login/google/callback',
                    'scope' => 'openid email profile',
                    'response_type' => 'code',
                    'access_type' => 'offline',
                    'prompt' => 'consent',
                ]));
    }

    // -----------------------------------------------------------------
    // GET /login/google/callback - error paths
    // -----------------------------------------------------------------

    public function test_callback_without_code_errors(): void
    {
        $this->get('/login/google/callback')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'No authorization code received from Google.');
    }

    public function test_callback_with_access_denied_goes_to_cancelled_page(): void
    {
        config([
            'services.google.client_id' => 'abc',
            'services.google.client_secret' => 'secret',
        ]);

        $this->get('/login/google/callback?error=access_denied&error_description=User+cancelled')
            ->assertRedirect(route('oauth.google.cancelled'));
    }

    public function test_cancelled_page_renders_legacy_dom(): void
    {
        $this->get('/login/google/cancelled')
            ->assertOk()
            ->assertSee('Authentication Cancelled', false)
            ->assertSee('cancelled.css', false)
            ->assertSee('Go to Login Now', false);
    }

    public function test_callback_with_failed_token_exchange_errors(): void
    {
        $this->fakeGoogle(['name' => 'Juan Dela Cruz', 'email' => 'juan@example.com', 'id' => 'g1'], 400);

        $this->get('/login/google/callback?code=bad')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Failed to get access token from Google.');
    }

    public function test_callback_without_email_in_profile_errors(): void
    {
        $this->fakeGoogle(['id' => 'g2', 'name' => 'No Email']);

        $this->get('/login/google/callback?code=ok')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Failed to get user information from Google.');
    }

    // -----------------------------------------------------------------
    // GET /login/google/callback - account flows
    // -----------------------------------------------------------------

    public function test_existing_approved_user_is_logged_in(): void
    {
        config([
            'services.google.client_id' => 'abc',
            'services.google.client_secret' => 'secret',
        ]);

        $email = $this->faker->unique()->safeEmail();
        $user = $this->makeApprovedUser($email);
        $this->fakeGoogle(['id' => 'g3', 'email' => $email, 'name' => 'Test User']);

        $this->get('/login/google/callback?code=ok')
            ->assertRedirect(route('user.dashboard'));

        $this->assertAuthenticatedAs($user);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'login_google',
            'resource_type' => 'user',
            'resource_id' => $user->id,
        ]);
    }

    public function test_existing_unapproved_user_is_turned_away(): void
    {
        config([
            'services.google.client_id' => 'abc',
            'services.google.client_secret' => 'secret',
        ]);

        $email = $this->faker->unique()->safeEmail();
        $user = $this->makeApprovedUser($email);
        $user->forceFill([
            'is_approved' => 0,
            'employee_id' => 'EMP-'.$this->faker->unique()->numerify('#####'),
            'department_id' => \App\Models\Department::query()->value('id'),
        ])->save();

        $this->fakeGoogle(['id' => 'g4', 'email' => $email, 'name' => 'Test User']);

        $this->get('/login/google/callback?code=ok')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Your account is pending approval. Please contact the administrator.');

        $this->assertGuest();
    }

    public function test_new_google_user_gets_pending_account(): void
    {
        config([
            'services.google.client_id' => 'abc',
            'services.google.client_secret' => 'secret',
        ]);

        $email = $this->faker->unique()->safeEmail();
        $this->fakeGoogle(['id' => 'g5', 'email' => $email, 'name' => 'Juan Dela Cruz']);

        $this->get('/login/google/callback?code=ok')
            ->assertRedirect(route('oauth.google.complete'));

        $user = User::query()->where('email', $email)->first();

        $this->assertNotNull($user);
        $this->assertSame('juancruz', $user->username);
        $this->assertSame('user', $user->role);
        $this->assertSame(0, (int) $user->is_approved);
        $this->assertSame(1, (int) $user->email_verified);

        $activity = ActivityLog::query()
            ->where('action', 'register_google')
            ->where('resource_id', $user->id)
            ->latest('id')
            ->first();
        $this->assertNotNull($activity);
    }

    public function test_new_google_user_can_finish_registration(): void
    {
        config(['services.google.client_id' => 'abc', 'services.google.client_secret' => 'secret']);

        $email = $this->faker->unique()->safeEmail();
        $this->fakeGoogle(['id' => 'g6', 'email' => $email, 'name' => 'Maria Santos']);
        $this->get('/login/google/callback?code=ok');

        $this->get(route('oauth.google.complete'))->assertOk()->assertSee($email);

        $deptId = \App\Models\Department::query()->value('id');

        $this->post(route('oauth.google.complete.store'), [
            'name' => 'Maria', 'surname' => 'Santos', 'employee_id' => 'GOOG-'.$this->faker->unique()->numerify('#####'),
            'position' => 'Instructor', 'department_id' => $deptId,
        ])->assertRedirect(route('login'))
          ->assertSessionHas('success');

        $user = User::query()->where('email', $email)->first();
        $this->assertSame('Instructor', $user->position);
        $this->assertSame((int) $deptId, (int) $user->department_id);
        $this->assertSame(0, (int) $user->is_approved);
    }

    public function test_finish_registration_requires_department_and_employee_id(): void
    {
        config(['services.google.client_id' => 'abc', 'services.google.client_secret' => 'secret']);

        $this->fakeGoogle(['id' => 'g7', 'email' => $this->faker->unique()->safeEmail(), 'name' => 'Pedro Cruz']);
        $this->get('/login/google/callback?code=ok');

        $this->post(route('oauth.google.complete.store'), ['name' => 'Pedro', 'surname' => 'Cruz', 'position' => 'Staff'])
            ->assertSessionHasErrors(['employee_id', 'department_id']);
    }

    public function test_finish_registration_page_needs_a_google_signup(): void
    {
        $this->get(route('oauth.google.complete'))->assertRedirect(route('login'));
    }

    // -----------------------------------------------------------------
    // Email verification (login.php ?verify=1&token=...)
    // -----------------------------------------------------------------

    public function test_verify_email_marks_account_verified(): void
    {
        $user = $this->makeApprovedUser($this->faker->unique()->safeEmail());
        $token = bin2hex(random_bytes(32));
        $user->forceFill(['email_verified' => 0, 'email_verification_token' => $token])->save();

        $this->get('/verify-email/'.$token)
            ->assertRedirect(route('login'))
            ->assertSessionHas('success', 'Email verified successfully! You can now log in once your account is approved.');

        $fresh = $user->fresh();
        $this->assertSame(1, (int) $fresh->email_verified);
        $this->assertNull($fresh->email_verification_token);

        $this->assertDatabaseHas('activity_logs', [
            'action' => 'email_verified',
            'resource_type' => 'user',
            'resource_id' => $user->id,
        ]);
    }

    public function test_verify_email_with_bad_token_errors(): void
    {
        $this->get('/verify-email/not-a-real-token')
            ->assertRedirect(route('login'))
            ->assertSessionHas('error', 'Invalid or expired verification token.');
    }
}