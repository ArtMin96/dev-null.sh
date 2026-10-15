<?php

declare(strict_types=1);

use App\Actions\CreateCommitPollVote;
use App\Models\Commit;
use App\Models\PollVote;
use App\Models\User;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Support\Facades\DB;

it('casts the vote of a user for an option', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->poll([], ['Tabs', 'Spaces'])->create();
    [, $spaces] = $commit->poll->options;

    resolve(CreateCommitPollVote::class)->handle($user, $spaces);

    expect(PollVote::query()->sole())
        ->user_id->toBe($user->id)
        ->poll_id->toBe($commit->poll->id)
        ->poll_option_id->toBe($spaces->id);
});

it('keeps the first vote of a user, since votes are final', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->poll([], ['Tabs', 'Spaces'])->create();
    [$tabs, $spaces] = $commit->poll->options;
    $action = resolve(CreateCommitPollVote::class);

    $action->handle($user, $tabs);
    $action->handle($user, $spaces);
    $action->handle($user, $tabs);

    expect(PollVote::query()->sole())
        ->user_id->toBe($user->id)
        ->poll_option_id->toBe($tabs->id);
});

it('counts the votes of different users', function (): void {
    $commit = Commit::factory()->poll([], ['Tabs', 'Spaces'])->create();
    [$tabs] = $commit->poll->options;
    $action = resolve(CreateCommitPollVote::class);

    $action->handle(User::factory()->create(), $tabs);
    $action->handle(User::factory()->create(), $tabs);

    expect($tabs->votes()->count())->toBe(2);
});

it('keeps a single vote when two votes of a user race', function (): void {
    $user = User::factory()->create();
    $commit = Commit::factory()->poll([], ['Tabs', 'Spaces'])->create();
    [$tabs, $spaces] = $commit->poll->options;

    $hasRaced = false;

    // The racing vote lands right after the action looked for an existing vote, before it inserts its own...
    DB::listen(function (QueryExecuted $query) use (&$hasRaced, $user, $commit, $spaces): void {
        if (! $hasRaced && str_starts_with($query->sql, 'select * from "poll_votes"')) {
            $hasRaced = true;

            DB::table('poll_votes')->insert([
                'poll_id' => $commit->poll?->id,
                'poll_option_id' => $spaces->id,
                'user_id' => $user->id,
            ]);
        }
    });

    $vote = resolve(CreateCommitPollVote::class)->handle($user, $tabs);

    expect($vote->poll_option_id)->toBe($spaces->id)
        ->and(PollVote::query()->sole()->poll_option_id)->toBe($spaces->id);
});
