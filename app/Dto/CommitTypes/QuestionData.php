<?php

declare(strict_types=1);

namespace App\Dto\CommitTypes;

use App\Contracts\Dto\CommitTypeData;
use App\Enums\CommitType;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Support\ValidatedInput;

/**
 * A question is a commit of its own type with nothing next to its body: its author accepts one of its replies later.
 */
final readonly class QuestionData implements CommitTypeData
{
    /**
     * @return array{}
     */
    public static function rules(User $user): array
    {
        return [];
    }

    public static function allowsImages(): bool
    {
        return true;
    }

    public static function fromValidated(ValidatedInput $input): self
    {
        return new self();
    }

    public function type(): CommitType
    {
        return CommitType::Question;
    }

    /**
     * @return array{}
     */
    public function attributes(): array
    {
        return [];
    }

    public function saveFor(Commit $commit): void
    {
        //
    }
}
