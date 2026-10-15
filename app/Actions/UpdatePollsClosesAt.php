<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;
use App\Models\Poll;

final readonly class UpdatePollsClosesAt
{
    /**
     * Starts the clock of the approved commits' polls, so moderation never eats into how long they stay open. A poll
     * that was opened before, and approved again, keeps its closing time.
     */
    public function handle(Commit ...$commits): void
    {
        $unopened = Poll::query()
            ->whereIn('commit_id', array_map(fn (Commit $commit): string => $commit->id, $commits))
            ->whereNull('closes_at')
            ->get(['id', 'duration']);

        foreach ($unopened->groupBy(fn (Poll $poll): int => $poll->duration->value) as $minutes => $polls) {
            Poll::query()->whereKey($polls->modelKeys())->update(['closes_at' => now()->addMinutes($minutes)]);
        }
    }
}
