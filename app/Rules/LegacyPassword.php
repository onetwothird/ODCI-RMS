<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Exact port of validatePassword() from includes/config.php:
 *
 *   preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{8,}$/', $password)
 *
 * i.e. at least 8 characters, containing a lowercase letter, an uppercase
 * letter and a digit, drawn from the restricted character class
 * [a-zA-Z\d@$!%*?&]. The restricted class is intentional and is preserved:
 * a password containing, say, a space or an underscore would be REJECTED by
 * the legacy system, so it is rejected here too.
 */
class LegacyPassword implements ValidationRule
{
    public const PATTERN = '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)[a-zA-Z\d@$!%*?&]{8,}$/';

    public const ERROR_MESSAGE = 'Password must be at least 8 characters with uppercase, lowercase, and number.';

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match(self::PATTERN, $value)) {
            $fail(self::ERROR_MESSAGE);
        }
    }
}