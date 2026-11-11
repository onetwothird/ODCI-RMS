<?php

namespace App\Http\Requests;

use App\Models\Department;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST ?action=update  ->  departments-script.php case 'update'
 */
class UpdateDepartmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        /** @var Department $department */
        $department = $this->route('department');

        return [
            'department_code' => [
                'required',
                'string',
                'max:10',
                Rule::unique('departments', 'department_code')->ignore($department->id),
            ],
            'department_name' => ['required', 'string', 'max:100'],
            'description' => ['nullable', 'string'],

            'head_of_department' => ['nullable', 'string', 'max:100'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:20'],

            'is_active' => ['nullable', 'boolean'],
        ];
    }

    public function messages(): array
    {
        return [
            'department_code.required' => 'Department code and name are required.',
            'department_name.required' => 'Department code and name are required.',
            'department_code.max' => 'Department code must be 10 characters or less.',
            'contact_email.email' => 'Invalid email address.',
            'department_code.unique' => 'Department code already exists for another department.',
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