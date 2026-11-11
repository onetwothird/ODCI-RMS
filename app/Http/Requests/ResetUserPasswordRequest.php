<?php

namespace App\Http\Requests;

use App\Rules\LegacyPassword;
use Illuminate\Foundation\Http\FormRequest;

/**
 * POST ?action=reset_password  ->  users-script.php case 'reset_password'
 */
class ResetUserPasswordRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->isSuperAdmin() ?? false;
    }

    public function rules(): array
    {
        return [
            'password' => ['required', 'string', new LegacyPassword],
            'password_confirmation' => ['required', 'same:password'],
        ];
    }

    public function messages(): array
    {
        return [
            'password_confirmation.same' => 'Passwords do not match.',
        ];
    }
}