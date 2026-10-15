<?php

declare(strict_types=1);

namespace App\Dto\CommitTypes;

use App\Contracts\Dto\CommitTypeData;
use App\Enums\CommitType;
use App\Models\Commit;
use App\Models\User;
use App\Rules\QuotableCommit;
use Illuminate\Support\ValidatedInput;

final readonly class QuoteData implements CommitTypeData
{
    public function __construct(public string $commitId)
    {
        //
    }

    /**
     * @return array<string, list<string|QuotableCommit>>
     */
    public static function rules(User $user): array
    {
        return [
            'quote' => ['required', 'array:commit_id'],
            'quote.commit_id' => ['required', 'uuid', new QuotableCommit()],
        ];
    }

    public static function allowsImages(): bool
    {
        return true;
    }

    public static function fromValidated(ValidatedInput $input): self
    {
        return new self($input->string('quote.commit_id')->value());
    }

    public function type(): CommitType
    {
        return CommitType::Quote;
    }

    /**
     * @return array{quoted_commit_id: string}
     */
    public function attributes(): array
    {
        return ['quoted_commit_id' => $this->commitId];
    }

    public function saveFor(Commit $commit): void
    {
        //
    }
}
