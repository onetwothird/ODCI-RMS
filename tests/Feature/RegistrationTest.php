<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Port of login/register.php + login/script/register_function.php.
 *
 * Runs against the real legacy `odci_db` with DatabaseTransactions (never
 * refresh/migrate). Every user/department/activity row created here is rolled
 * back when the test ends.
 */
class RegistrationTest extends TestCase
{
    use DatabaseTransactions, WithFaker;

    private function makeActiveDepartment(): Department
    {
        $department = new Department();
        $department->forceFill([
            'department_code' => 'AUTH_'.strtoupper($this->faker->unique()->bothify('??##')),
            'department_name' => 'Auth Test Department '.$this->faker->unique()->numerify('###'),
            'is_active' => 1,
        ])->save();

        return $department;
    }

    private function validPayload(int $departmentId, array $overrides = []): array
    {
        return array_merge([
            'username' => 'auth_'.$this->faker->unique()->bothify('???####'),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => 'Secret@123',
            'confirm_password' => 'Secret@123',
            'name' => 'Juan',
            'mi' => 'D',
            'surname' => 'Dela Cruz',
            'employee_id' => $this->faker->unique()->numerify('AUTH-#####'),
            'position' => 'Instructor',
            'department_id' => $departmentId,
            'date_of_birth' => '1995-05-10',
            'phone' => '+63 912 345 6789',
            'address' => 'Naic, Cavite',
        ], $overrides);
    }

    // -----------------------------------------------------------------
    // GET /register
    // -----------------------------------------------------------------

    public function test_register_page_renders_the_legacy_wizard_dom(): void
    {
        $response = $this->get('/register');

        $response->assertOk();
        $response->assertSee('id="registerForm"', false);
        $response->assertSee('class="wizard-progress"', false);
        $response->assertSee('Registration Portal', false);
        $response->assertSee('id="nextBtn"', false);
        $response->assertSee('id="prevBtn"', false);
        $response->assertSee('id="submitBtn"', false);
        $response->assertSee('css/register.css', false);
        $response->assertSee('css/cvsu-auth.css', false);
        $response->assertSee('js/auth-script.js', false);
    }

    public function test_register_page_lists_active_departments(): void
    {
        $department = $this->makeActiveDepartment();

        $this->get('/register')
            ->assertOk()
            ->assertSee($department->department_code.' - '.$department->department_name, false);
    }

    public function test_logged_in_user_is_sent_to_own_dashboard(): void
    {
        $user = User::query()->where('role', 'user')->where('is_approved', 1)->first() ?? $this->sampleApprovedUser();

        $this->actingAs($user)
            ->get('/register')
            ->assertRedirect(route('user.dashboard'));
    }

    // -----------------------------------------------------------------
    // POST /register - success
    // -----------------------------------------------------------------

    public function test_valid_registration_creates_pending_approved_user(): void
    {
        $department = $this->makeActiveDepartment();
        $payload = $this->validPayload($department->id);

        $this->post('/register', $payload)
            ->assertRedirect(route('register'))
            ->assertSessionHas('success');

        $user = User::query()->where('username', $payload['username'])->first();

        $this->assertNotNull($user);
        $this->assertSame('user', $user->role);                    // DB default
        $this->assertSame(0, (int) $user->is_approved);            // DB default: pending approval
        $this->assertTrue(Hash::check($payload['password'], $user->password));
        $this->assertSame($department->id, $user->department_id);
        $this->assertNotEmpty($user->email_verification_token);
        $this->assertNotNull($user->date_of_birth);
    }

    public function test_valid_registration_writes_register_activity(): void
    {
        $department = $this->makeActiveDepartment();
        $payload = $this->validPayload($department->id);

        $this->post('/register', $payload);

        $user = User::query()->where('username', $payload['username'])->first();

        $activity = ActivityLog::query()
            ->where('action', 'register')
            ->where('resource_type', 'user')
            ->where('resource_id', $user->id)
            ->latest('id')
            ->first();

        $this->assertNotNull($activity, 'register activity row must exist');
        $this->assertSame('User registered successfully', $activity->description);
    }

    // -----------------------------------------------------------------
    // POST /register - validation errors (legacy messages, verbatim)
    // -----------------------------------------------------------------

    public function test_duplicate_username_is_rejected(): void
    {
        $department = $this->makeActiveDepartment();
        $existing = User::query()->where('is_approved', 1)->first();

        $payload = $this->validPayload($department->id, ['username' => $existing->username]);

        $this->post('/register', $payload)
            ->assertSessionHasErrors(['username' => 'Username already exists.']);
    }

    public function test_duplicate_email_is_rejected(): void
    {
        $department = $this->makeActiveDepartment();
        $existing = User::query()->where('is_approved', 1)->first();

        $payload = $this->validPayload($department->id, ['email' => $existing->email]);

        $this->post('/register', $payload)
            ->assertSessionHasErrors(['email' => 'Email already registered.']);
    }

    public function test_invalid_username_characters_are_rejected(): void
    {
        $department = $this->makeActiveDepartment();

        $this->post('/register', $this->validPayload($department->id, ['username' => 'bad user!']))
            ->assertSessionHasErrors(['username' => 'Username can only contain letters, numbers, and underscores.']);
    }

    public function test_short_username_is_rejected(): void
    {
        $department = $this->makeActiveDepartment();

        $this->post('/register', $this->validPayload($department->id, ['username' => 'ab']))
            ->assertSessionHasErrors(['username' => 'Username must be at least 3 characters long.']);
    }

    public function test_short_password_is_rejected(): void
    {
        $department = $this->makeActiveDepartment();

        $this->post('/register', $this->validPayload($department->id, ['password' => 'abc', 'confirm_password' => 'abc']))
            ->assertSessionHasErrors(['password' => 'Password must be at least 6 characters long.']);
    }

    public function test_confirmed_password_mismatch_is_rejected(): void
    {
        $department = $this->makeActiveDepartment();

        $this->post('/register', $this->validPayload($department->id, ['confirm_password' => 'Different@1']))
            ->assertSessionHasErrors(['confirm_password' => 'Passwords do not match.']);
    }

    public function test_missing_department_is_rejected(): void
    {
        $department = $this->makeActiveDepartment();

        $this->post('/register', $this->validPayload($department->id, ['department_id' => 0]))
            ->assertSessionHasErrors(['department_id' => 'Please select a department.']);
    }

    public function test_underage_registration_is_rejected(): void
    {
        $department = $this->makeActiveDepartment();

        $this->post('/register', $this->validPayload($department->id, ['date_of_birth' => now()->subYears(12)->format('Y-m-d')]))
            ->assertSessionHasErrors(['date_of_birth' => 'You must be at least 18 years old.']);
    }

    public function test_missing_required_fields_listed(): void
    {
        $this->post('/register', [
            'username' => '',
            'email' => '',
            'password' => '',
            'confirm_password' => '',
            'name' => '',
            'surname' => '',
            'employee_id' => '',
            'position' => '',
            'department_id' => 0,
        ])->assertSessionHasErrors([
            'username' => 'Username must be at least 3 characters long.',
            'name' => 'First name is required.',
            'surname' => 'Last name is required.',
            'employee_id' => 'Employee ID is required.',
            'position' => 'Position is required.',
        ]);
    }

    public function test_duplicate_employee_id_is_rejected(): void
    {
        $department = $this->makeActiveDepartment();
        $existing = User::query()->where('is_approved', 1)->firstOrFail();

        $this->post('/register', $this->validPayload($department->id, ['employee_id' => $existing->employee_id]))
            ->assertSessionHasErrors(['employee_id' => 'Employee ID already exists.']);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    private function sampleApprovedUser(): User
    {
        $user = new User();
        $user->forceFill([
            'username' => 'auth_approved_'.$this->faker->unique()->numerify('####'),
            'email' => $this->faker->unique()->safeEmail(),
            'password' => 'MigrationTest@123',
            'role' => 'user',
            'is_approved' => 1,
            'is_restricted' => 0,
            'failed_login_attempts' => 0,
            'name' => 'Test',
            'surname' => 'User',
            'employee_id' => $this->faker->unique()->numerify('AUTH-#####'),
            'department_id' => Department::query()->where('is_active', 1)->value('id'),
        ])->save();

        return $user;
    }
}