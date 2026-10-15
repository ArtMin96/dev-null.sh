<?php

declare(strict_types=1);

namespace App\Queries;

use App\Models\Poll;
use App\Models\PollVote;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @extends Builder<Poll>
 */
final class PollBuilder extends Builder
{
    /**
     * Loads what a poll card renders for the given viewer: the options in order with their vote counts, and the option
     * the viewer voted for, if any.
     */
    public function withResultsFor(User $viewer): self
    {
        return $this
            ->addSelect(['voted_option_id' => PollVote::query()
                ->select('poll_option_id')
                ->whereColumn('poll_id', $this->qualifyColumn('id'))
                ->where('user_id', $viewer->id)
                ->limit(1)])
            ->with(['options' => fn (HasMany $options): HasMany => $options->withCount('votes')]);
    }
}
