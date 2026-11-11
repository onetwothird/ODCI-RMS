<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

/**
 * End-to-end verification that the migrated login flow behaves exactly like the
 * legacy login/script/login_function.php.
 *
 * IMPORTANT: DatabaseTransactions (NOT RefreshDatabase) is used deliberately.
 * These tests run against the real legacy `odci_db` schema and must never
 * create, drop, or migrate anything -- every change is rolled back.
 */
class AuthenticationTest extends TestCase
{
    use DatabaseTransactions, WithFaker;

    private const PASSWORD = 'MigrationTest@123';

    /**
     * `role` and `is_approved` are intentionally NOT mass-assignable on the
     * User model, so they must be set with forceFill() -- exactly like the rest
     * of the application has to do it. Using create() here would silently
     * drop them and produce unapproved 'user' accounts for every test.
     */
    private function makeUser(string $role, bool $approved = true): User
    {
        $user = new User();

        $user->forceFill([
            'username' => 'test_'.$role.'_'.$this->faker->unique()->numerify('####'),
            'email' => 'test_'.$role.'_'.$this->faker->unique()->safeEmail(),
            'password' => self::PASSWORD,
            'role' => $role,
            'is_approved' => $approved ? 1 : 0,
            'is_restricted' => 0,
            'failed_login_attempts' => 0,
            'name' => 'Test',
            'surname' => 'User',
            'employee_id' => $this->faker->unique()->numerify('EMP-#####'),
        ])->save();

        return $user;
    }

    /** Expected landing page per role, mirroring login_function.php. */
    private function dashboardRouteFor(string $role): string
    {
        return match ($role) {
            'super_admin' => 'superadmin.dashboard',
            'admin' => 'admin.dashboard',
            default => 'user.dashboard',
        };
    }

    // -----------------------------------------------------------------
    // Login screen
    // -----------------------------------------------------------------

    public function test_login_screen_renders_without_errors(): void
    {
        $response = $this->get('/login');

        $response->assertOk();
        $response->assertSee('Office of the Director');
        $response->assertSee('name="username"', false);
        $response->assertSee('name="password"', false);
        $response->assertSee('name="remember"', false);
        $response->assertSee('Sign In');
    }

    // -----------------------------------------------------------------
    // Successful login, per role
    // -----------------------------------------------------------------

    public function test_login_redirects_each_role_to_its_own_dashboard(): void
    {
        foreach (['super_admin', 'admin', 'user'] as $role) {
            $user = $this->makeUser($role);

            $response = $this->post('/login', [
                'username' => $user->username,
                'password' => self::PASSWORD,
            ]);

            $response->assertRedirect(route($this->dashboardRouteFor($role)));
            $this->assertAuthenticatedAs($user);
        }
    }

    public function test_login_also_accepts_email_as_the_identifier(): void
    {
        $user = $this->makeUser('admin');

        $response = $this->post('/login', [
            'username' => $user->email,
            'password' => self::PASSWORD,
        ]);

        $response->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($user);
    }

    public function test_login_sets_last_login_and_clears_failure_counters(): void
    {
        $user = $this->makeUser('user');
        $user->forceFill([
            'failed_login_attempts' => 2,
            'account_locked_until' => now()->subMinute(),
            'last_login' => null,
        ])->save();

        $this->post('/login', [
            'username' => $user->username,
            'password' => self::PASSWORD,
        ])->assertRedirect(route('user.dashboard'));

        $user->refresh();

        $this->assertSame(0, (int) $user->failed_login_attempts, 'Failure counter should reset.');
        $this->assertNull($user->account_locked_until, 'Stale lock should be cleared.');
        $this->assertNotNull($user->last_login, 'last_login should be stamped.');
    }

    public function test_successful_login_does_not_rehash_an_existing_bcrypt_password(): void
    {
        // Guards the data-integrity risk of the `hashed` cast: saving a model must
        // not silently upgrade the stored bcrypt cost of legacy accounts.
        $user = $this->makeUser('user');
        $original = $user->password;

        $user->forceFill(['last_login' => now()])->save();
        $user->refresh();

        $this->assertSame($original, $user->password);
    }

    // -----------------------------------------------------------------
    // Failed login / lockout, exactly as the legacy code did it
    // -----------------------------------------------------------------

    public function test_failed_login_reports_remaining_attempts(): void
    {
        $user = $this->makeUser('user');

        $response = $this->from('/login')->post('/login', [
            'username' => $user->username,
            'password' => 'definitely-wrong',
        ]);

        $response->assertRedirect('/login');
        $response->assertSessionHasErrors('username');

        $this->assertSame(
            'Invalid username/email or password. 4 attempts remaining.',
            session('errors')->first('username')
        );

        $this->assertGuest();
        $this->assertSame(1, (int) $user->refresh()->failed_login_attempts);
    }

    public function test_fifth_failed_attempt_locks_the_account_for_fifteen_minutes(): void
    {
        $user = $this->makeUser('user');

        for ($attempt = 1; $attempt <= 4; $attempt++) {
            $this->from('/login')->post('/login', [
                'username' => $user->username,
                'password' => 'wrong-'.$attempt,
            ]);
        }

        $response = $this->from('/login')->post('/login', [
            'username' => $user->username,
            'password' => 'wrong-5',
        ]);

        $this->assertSame(
            'Too many failed login attempts. Your account has been locked for 15 minutes.',
            session('errors')->first('username')
        );

        $user->refresh();

        $this->assertSame(5, (int) $user->failed_login_attempts);
        $this->assertNotNull($user->account_locked_until);
        $this->assertTrue($user->isLocked());
        $this->assertGuest();
    }

    public function test_correct_password_is_still_refused_while_the_account_is_locked(): void
    {
        $user = $this->makeUser('user');
        $user->forceFill([
            'failed_login_attempts' => 5,
            'account_locked_until' => now()->addMinutes(15),
        ])->save();

        $this->from('/login')->post('/login', [
            'username' => $user->username,
            'password' => self::PASSWORD,
        ]);

        $this->assertGuest();
        $this->assertStringContainsString(
            'temporarily locked',
            (string) session('errors')->first('username')
        );
    }

    public function test_unapproved_account_cannot_sign_in(): void
    {
        $user = $this->makeUser('user', approved: false);

        $response = $this->from('/login')->post('/login', [
            'username' => $user->username,
            'password' => self::PASSWORD,
        ]);

        $response->assertRedirect('/login');
        $this->assertGuest();
        $this->assertSame(
            'Invalid username/email or password.',
            session('errors')->first('username')
        );
    }

    public function test_login_requires_both_fields(): void
    {
        $this->from('/login')
            ->post('/login', ['username' => '', 'password' => ''])
            ->assertSessionHasErrors(['username', 'password']);

        $this->assertGuest();
    }

    // -----------------------------------------------------------------
    // Logout
    // -----------------------------------------------------------------

    public function test_logout_clears_the_session(): void
    {
        $user = $this->makeUser('admin');

        $this->actingAs($user)->post('/logout')->assertRedirect(route('login'));

        $this->assertGuest();
    }

    public function test_guest_cannot_logout(): void
    {
        $this->post('/logout')->assertRedirect(route('login'));
    }

    // -----------------------------------------------------------------
    // Role gate
    // -----------------------------------------------------------------

    public function test_role_gate_blocks_a_user_from_the_admin_dashboard(): void
    {
        $user = $this->makeUser('user');

        $response = $this->actingAs($user)->get('/admin/dashboard');

        // Legacy behaviour: send the user to their OWN dashboard, not to /login.
        $response->assertRedirect(route('user.dashboard'));
        $response->assertSessionHas('error', 'access_denied');
    }

    public function test_role_gate_allows_admin_to_reach_the_admin_dashboard(): void
    {
        $this->actingAs($this->makeUser('admin'))
            ->get('/admin/dashboard')
            ->assertOk();
    }

    public function test_super_admin_may_reach_the_admin_dashboard(): void
    {
        $this->actingAs($this->makeUser('super_admin'))
            ->get('/admin/dashboard')
            ->assertOk();
    }

    public function test_role_gate_blocks_admin_from_the_superadmin_dashboard(): void
    {
        $this->actingAs($this->makeUser('admin'))
            ->get('/superadmin/dashboard')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_each_role_reaches_only_its_own_dashboard(): void
    {
        $cases = [
            'super_admin' => ['/superadmin/dashboard', true],
            'super_admin' => ['/user/dashboard', false],
            'admin' => ['/admin/dashboard', true],
            'admin' => ['/superadmin/dashboard', false],
            'user' => ['/user/dashboard', true],
            'user' => ['/superadmin/dashboard', false],
        ];

        $users = [
            'super_admin' => $this->makeUser('super_admin'),
            'admin' => $this->makeUser('admin'),
            'user' => $this->makeUser('user'),
        ];

        foreach ($cases as $role => list($url, $allowed)) {
            $response = $this->actingAs($users[$role])->get($url);

            if ($allowed) {
                $response->assertOk();
            } else {
                $response->assertRedirect(route($this->dashboardRouteFor($role)));
            }
        }
    }

    // -----------------------------------------------------------------
    // Guests
    // -----------------------------------------------------------------

    public function test_guests_are_sent_to_login_from_protected_dashboards(): void
    {
        foreach (['/user/dashboard', '/admin/dashboard', '/superadmin/dashboard'] as $url) {
            $this->get($url)->assertRedirect(route('login'));
        }
    }

    public function test_authenticated_users_are_bounced_off_the_login_page(): void
    {
        $user = $this->makeUser('admin');

        $this->actingAs($user)->get('/login')->assertRedirect(route('admin.dashboard'));
    }

    // -----------------------------------------------------------------
    // requireAuth() parity: de-approval revokes access immediately
    // -----------------------------------------------------------------

    public function test_deactivating_an_account_revokes_access_on_the_next_request(): void
    {
        $user = $this->makeUser('user');

        $this->actingAs($user)->get('/user/dashboard')->assertOk();

        $user->forceFill(['is_approved' => 0])->save();

        $response = $this->actingAs($user->fresh())->get('/user/dashboard');

        $response->assertRedirect(route('login'));
        $this->assertGuest();
    }

    // -----------------------------------------------------------------
    // Static assets referenced by the login page
    // -----------------------------------------------------------------

    /**
     * The login page links to these files. Laravel's test HTTP kernel does not
     * serve files from public/, so existence is asserted on disk instead.
     */
    public function test_login_page_assets_exist_in_public(): void
    {
        foreach ([
            'css/login.css',
            'css/cvsu-auth.css',
            'css/app.css',
            'img/cvsu-logo.png',
            'img/cvsu-naic-bg.jpg',
            'js/auth-script.js',
        ] as $asset) {
            $path = public_path($asset);

            $this->assertFileExists($path, "Missing public asset: {$asset}");
            $this->assertGreaterThan(0, filesize($path), "Empty public asset: {$asset}");
        }
    }

    // -----------------------------------------------------------------
    // Placeholder pages linked from the login form
    // -----------------------------------------------------------------

    public function test_login_page_links_resolve_instead_of_erroring(): void
    {
        foreach (['/register', '/forgot-password', '/reset-password/abc123'] as $url) {
            $this->get($url)->assertOk();
        }
    }
}