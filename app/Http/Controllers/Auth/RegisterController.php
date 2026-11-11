<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\User;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

/**
 * Port of login/register.php + login/script/register_function.php.
 *
 * The legacy page was a 3-step wizard (Account -> Personal -> Employment)
 * posting to itself. Validation, sanitisation and the database INSERT are
 * reproduced field-for-field, including the legacy error messages and the
 * "pending approval" outcome.
 */
class RegisterController extends Controller
{
    public function __construct(private readonly ActivityLogger $activityLogger) {}

    /**
     * GET /register
     */
    public function show(): View|RedirectResponse
    {
        // Legacy: if already logged in, leave the registration page.
        if (auth()->check()) {
            return redirect()->to(getDashboardUrl(auth()->user()->role));
        }

        return view('auth.register', [
            'departments' => Department::query()
                ->where('is_active', 1)
                ->orderBy('department_name')
                ->get(['id', 'department_code', 'department_name']),
        ]);
    }

    /**
     * POST /register
     */
    public function store(Request $request): RedirectResponse
    {
        if (auth()->check()) {
            return redirect()->to(getDashboardUrl(auth()->user()->role));
        }

        // Legacy ran every string field through sanitizeInput() BEFORE validating.
        $data = $request->only([
            'username', 'email', 'name', 'mi', 'surname',
            'employee_id', 'position', 'phone', 'address',
        ]);
        foreach ($data as $field => $value) {
            $data[$field] = sanitizeInput((string) $value);
        }
        $data['password'] = (string) $request->input('password');
        $data['confirm_password'] = (string) $request->input('confirm_password');
        $data['department_id'] = (int) $request->input('department_id');
        $data['date_of_birth'] = (string) $request->input('date_of_birth');

        $validator = Validator::make($data, [
            'username' => ['required', 'string', 'min:3', 'regex:/^[a-zA-Z0-9_]+$/', 'unique:users,username'],
            'email' => ['required', 'string', 'email', 'unique:users,email'],
            'password' => ['required', 'string', 'min:6'],
            'name' => ['required', 'string'],
            'surname' => ['required', 'string'],
            'employee_id' => ['required', 'string', 'unique:users,employee_id'],
            'position' => ['required', 'string'],
            'department_id' => ['required', 'integer', 'gt:0', 'exists:departments,id'],
            'date_of_birth' => ['nullable', 'date'],
        ], [
            // Legacy messages, verbatim.
            'username.required' => 'Username must be at least 3 characters long.',
            'username.min' => 'Username must be at least 3 characters long.',
            'username.regex' => 'Username can only contain letters, numbers, and underscores.',
            'username.unique' => 'Username already exists.',
            'email.required' => 'Please enter a valid email address.',
            'email.email' => 'Please enter a valid email address.',
            'email.unique' => 'Email already registered.',
            'password.required' => 'Password is required.',
            'password.min' => 'Password must be at least 6 characters long.',
            'name.required' => 'First name is required.',
            'surname.required' => 'Last name is required.',
            'employee_id.required' => 'Employee ID is required.',
            'employee_id.unique' => 'Employee ID already exists.',
            'position.required' => 'Position is required.',
            'department_id.required' => 'Please select a department.',
            'department_id.integer' => 'Please select a department.',
            'department_id.gt' => 'Please select a department.',
            'department_id.exists' => 'Please select a department.',
        ]);

        $validator->after(function ($validator) use ($data) {
            // Legacy: $password !== $confirm_password -> 'Passwords do not match.'
            if ($data['password'] !== $data['confirm_password']) {
                $validator->errors()->add('confirm_password', 'Passwords do not match.');
            }

            // Legacy: only checked when date_of_birth was supplied.
            if ($data['date_of_birth'] !== '' && ! $validator->errors()->has('date_of_birth')) {
                try {
                    if (\Illuminate\Support\Carbon::parse($data['date_of_birth'])->age < 18) {
                        $validator->errors()->add('date_of_birth', 'You must be at least 18 years old.');
                    }
                } catch (Throwable) {
                    // The `date` rule already reported an invalid value.
                }
            }
        });

        if ($validator->fails()) {
            // Legacy rendered the same page with $error = 'Please correct the errors below.'
            // Laravel's ValidationException flashes the errors + old input for us.
            throw ValidationException::withMessages($validator->errors()->toArray());
        }

        try {
            // Legacy INSERT listed only these columns; role / is_approved / etc.
            // fall back to the table defaults exactly as before. The model's
            // 'hashed' cast replaces password_hash($password, PASSWORD_DEFAULT).
            $user = User::create([
                'username' => $data['username'],
                'email' => $data['email'],
                'password' => $data['password'],
                'name' => $data['name'],
                'mi' => $data['mi'],
                'surname' => $data['surname'],
                'employee_id' => $data['employee_id'],
                'position' => $data['position'],
                'department_id' => $data['department_id'],
                'phone' => $data['phone'],
                'address' => $data['address'],
                'date_of_birth' => $data['date_of_birth'] ?: null,
            ]);

            // Not mass-assignable: generateToken() equivalent (bin2hex(random_bytes(32))).
            $user->forceFill([
                'email_verification_token' => bin2hex(random_bytes(32)),
            ])->save();

            // Legacy: logActivity(..., 'register', 'user', $userId, 'User registered successfully', [...])
            $this->activityLogger->logRequest(
                $user->id,
                'register',
                'user',
                $user->id,
                'User registered successfully',
                [
                    'username' => $data['username'],
                    'email' => $data['email'],
                    'department_id' => $data['department_id'],
                ],
            );

            return redirect()->route('register')->with(
                'success',
                'Registration successful! Your account is pending approval. You will receive an email once approved.',
            );
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('register')
                ->withInput($request->except('password', 'confirm_password'))
                ->with('error', 'Registration failed. Please try again.');
        }
    }

    /**
     * GET /verify-email/{token}
     *
     * Port of the `?verify=1&token=...` block in login/script/login_function.php.
     * No UI generates these links today - the token is stored at registration,
     * and this endpoint is what consumes it when an admin sends one manually.
     */
    public function verifyEmail(string $token): RedirectResponse
    {
        if ($token === '') {
            return redirect()->route('login')->with('error', 'Invalid or expired verification token.');
        }

        try {
            $user = User::query()->where('email_verification_token', $token)->first();

            if ($user) {
                $user->forceFill([
                    'email_verified' => 1,
                    'email_verification_token' => null,
                ])->save();

                $this->activityLogger->logRequest(
                    $user->id,
                    'email_verified',
                    'user',
                    $user->id,
                    'Email address verified successfully',
                );

                return redirect()->route('login')
                    ->with('success', 'Email verified successfully! You can now log in once your account is approved.');
            }

            return redirect()->route('login')->with('error', 'Invalid or expired verification token.');
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('login')
                ->with('error', 'Email verification failed. Please try again or contact support.');
        }
    }
}
