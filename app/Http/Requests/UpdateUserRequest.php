<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST ?action=update  ->  roles/superadmin/assets/script/users-script.php case 'update'
 *
 * Note: the legacy UPDATE deliberately does NOT touch username or email, so
 * those are absent here too.
 */
class UpdateUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        /** @var User $target */
        $target = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'mi' => ['nullable', 'string', 'max:5'],
            'surname' => ['required', 'string', 'max:255'],

            'employee_id' => [
                'nullable',
                'string',
                'max:255',
                Rule::unique('users', 'employee_id')->ignore($target->id),
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

            // Legacy used `isset($_POST['is_approved']) ? 1 : 0`, i.e. an
            // unchecked box means 0. `boolean` reproduces that.
            'is_approved' => ['nullable', 'boolean'],
            'is_restricted' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'employee_id.unique' => 'Employee ID already exists for another user.',
        ];
    }
}