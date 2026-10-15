<?php

declare(strict_types=1);

namespace App\Rules;

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\Poll;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * A poll takes votes from the moment its commit is approved until it closes, from everyone but its author, who follows
 * the results instead. Each refusal tells the voter why.
 */
final readonly class VotablePoll implements ValidationRule
{
    public function __construct(private Commit $commit, private User $voter)
    {
        //
    }

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $poll = $this->commit->poll;
        assert($poll instanceof Poll);

        if ($this->commit->status !== CommitStatus::Approved || $poll->closes_at === null) {
            $fail(__('This poll is not open for votes yet.'));
        } elseif ($this->commit->user_id === $this->voter->id) {
            $fail(__("You can't vote on your own poll."));
        } elseif ($poll->isClosed()) {
            $fail(__('This poll has closed.'));
        }
    }
}
