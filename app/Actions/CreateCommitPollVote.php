<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\PollOption;
use App\Models\PollVote;
use App\Models\User;

final readonly class CreateCommitPollVote
{
    /**
     * A user votes once per poll, and their vote is final: a second vote, even a racing one, keeps the first.
     */
    public function handle(User $user, PollOption $option): PollVote
    {
        return PollVote::query()->firstOrCreate(
            ['poll_id' => $option->poll_id, 'user_id' => $user->id],
            ['poll_option_id' => $option->id],
        );
    }
}
