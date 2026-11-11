<?php

namespace App\Http\Requests;

use App\Rules\LegacyPassword;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST ?action=create  ->  roles/superadmin/assets/script/users-script.php case 'create'
 *
 * Legacy validation order and messages are preserved:
 *   - all required fields present
 *   - valid email
 *   - valid password
 *   - username OR email already taken   ("Username or email already exists.")
 *   - employee_id already taken          ("Employee ID already exists.")
 */
class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Coarse access is enforced by the route's role:super_admin middleware;
        // requireSuperAdmin() in the legacy code did the same job.
        return $this->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'username' => [
                'required',
                'string',
                'max:255',
                Rule::unique('users', 'username'),
            ],
            'email' => [
                'required',
                'email',
                'max:255',
                Rule::unique('users', 'email'),
            ],
            'password' => ['required', 'string', new LegacyPassword],

            'name' => ['required', 'string', 'max:255'],
            'mi' => ['nullable', 'string', 'max:5'],
            'surname' => ['required', 'string', 'max:255'],

            'employee_id' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('users', 'employee_id'),
            ],
            'position' => ['nullable', 'string', 'max:255'],

            'department_id' => [
                'nullable',
                'integer',
                Rule::exists('departments', 'id'),
            ],
            'role' => ['required', Rule::in(['user', 'admin', 'super_admin'])],

            'phone' => ['nullable', 'string', 'max:20'],
            'address' => ['nullable', 'string'],
        ];
    }

    public function messages(): array
    {
        return [
            'username.required' => 'All required fields must be filled.',
            'email.required' => 'All required fields must be filled.',
            'password.required' => 'All required fields must be filled.',
            'name.required' => 'All required fields must be filled.',
            'surname.required' => 'All required fields must be filled.',

            'email.email' => 'Invalid email address.',

            // Match the legacy messages rather than Laravel's defaults.
            'username.unique' => 'Username or email already exists.',
            'email.unique' => 'Username or email already exists.',
            'employee_id.unique' => 'Employee ID already exists.',
        ];
    }
}