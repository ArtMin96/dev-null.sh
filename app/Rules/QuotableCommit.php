<?php

declare(strict_types=1);

namespace App\Rules;

use App\Models\Commit;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * Only approved commits that still exist may be quoted, so a quote never points at something its readers cannot see.
 */
final readonly class QuotableCommit implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! Commit::query()->approved()->whereKey($value)->exists()) {
            $fail(__('That commit is no longer available to quote.'));
        }
    }
}
