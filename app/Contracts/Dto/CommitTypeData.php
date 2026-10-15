<?php

declare(strict_types=1);

namespace App\Contracts\Dto;

use App\Enums\CommitType;
use App\Models\Commit;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\ValidatedInput;
use Stringable;

/**
 * The data a commit carries next to its body, depending on its type: a release, a poll, a snippet...
 */
interface CommitTypeData
{
    /**
     * The validation rules of the type's fields, keyed by their name in the request: `<type>.<field>`.
     *
     * @return array<string, list<string|Stringable|ValidationRule|Closure>>
     */
    public static function rules(User $user): array;

    /**
     * Whether a commit of this type may come with images.
     */
    public static function allowsImages(): bool;

    public static function fromValidated(ValidatedInput $input): self;

    public function type(): CommitType;

    /**
     * The columns of the commit itself the type sets, stored by the same insert that creates the commit.
     *
     * @return array<string, mixed>
     */
    public function attributes(): array;

    /**
     * Saves the data of the freshly created commit, within the transaction that creates it.
     */
    public function saveFor(Commit $commit): void;
}
