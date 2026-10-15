<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Text is stored and shown as UTF-8, so text that is not valid UTF-8 is refused rather than mangled.
 */
final readonly class Utf8 implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (is_string($value) && ! mb_check_encoding($value, 'UTF-8')) {
            $fail(__('The :attribute field must be valid UTF-8 text.'));
        }
    }
}
