<?php

namespace App\Http\Requests;

use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST ?action=create  ->  roles/superadmin/assets/script/departments-script.php case 'create'
 *
 * Legacy forced the code to UPPERCASE before storing it; that is preserved.
 */
class StoreDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'department_code' => [
                'required',
                'string',
                'max:10',
                Rule::unique('departments', 'department_code'),
            ],
            'department_name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],

            'head_of_department' => ['nullable', 'string', 'max:100'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'department_code.required' => 'Department code and name are required.',
            'department_name.required' => 'Department code and name are required.',
            'department_code.max' => 'Department code must be 10 characters or less.',
            'contact_email.email' => 'Invalid email address.',
            'department_code.unique' => 'Department code already exists.',
        ];
    }

    protected function prepareForValidation(): void
    {
        if ($this->has('department_code') && is_string($this->input('department_code'))) {
            $this->merge([
                'department_code' => strtoupper(trim($this->input('department_code'))),
            ]);
        }
    }
}