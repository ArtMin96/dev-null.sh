<?php

declare(strict_types=1);

namespace App\Actions;

use App\Models\Commit;
use App\Models\Poll;
use App\Models\PollOption;
use App\Models\PollVote;
use App\Models\Release;
use App\Models\Snippet;
use App\Models\User;
use App\Queries\PollBuilder;
use Generator;

/**
 * @phpstan-type ExportedCommit array{id: string, parent_id: string|null, body: string, status: string, type: string, release: array{project: string, version: string, url: string|null, notes: string|null}|null, poll: array{options: list<string>, duration_minutes: int, closes_at: string|null}|null, snippet: array{language: string, filename: string|null, code: string}|null, quoted_commit_id: string|null, accepted_answer_id: string|null, created_at: string, updated_at: string}
 */
final readonly class CreateExport
{
    /**
     * @return array{schema_version: int, exported_at: string, profile: array<string, int|string|null>, commits: Generator<int, ExportedCommit>, poll_votes: Generator<int, array{commit_id: string, option: string, created_at: string}>, following: Generator<int, array{username: string}>}
     */
    public function handle(User $user): array
    {
        return [
            'schema_version' => 1,
            'exported_at' => now()->toIso8601String(),
            'profile' => [
                'username' => $user->username,
                'name' => $user->name,
                'email' => $user->email,
                'avatar_url' => $user->avatar_url,
                'bio' => $user->bio,
                'location' => $user->location,
                'website' => $user->website,
                'company' => $user->company,
                'preferred_lane' => $user->preferred_lane,
                'created_at' => $user->created_at->toIso8601String(),
                'updated_at' => $user->updated_at->toIso8601String(),
            ],
            'commits' => $this->commits($user),
            'poll_votes' => $this->pollVotes($user),
            'following' => $this->following($user),
        ];
    }

    /**
     * Every commit comes with its type, and the data of that type: the other types' keys stay null.
     *
     * @return Generator<int, ExportedCommit>
     */
    private function commits(User $user): Generator
    {
        $commits = $user->commits()
            ->select(['id', 'parent_id', 'body', 'status', 'type', 'quoted_commit_id', 'accepted_answer_id', 'created_at', 'updated_at'])
            ->with(['release', 'poll.options', 'snippet']);

        foreach ($commits->lazyById(500) as $commit) {
            yield [
                'id' => $commit->id,
                'parent_id' => $commit->parent_id,
                'body' => $commit->body,
                'status' => $commit->status->value,
                'type' => $commit->type->value,
                'release' => $commit->release instanceof Release ? [
                    'project' => $commit->release->project,
                    'version' => $commit->release->version,
                    'url' => $commit->release->url,
                    'notes' => $commit->release->notes,
                ] : null,
                'poll' => $commit->poll instanceof Poll ? [
                    'options' => array_values($commit->poll->options->map(fn (PollOption $option): string => $option->label)->all()),
                    'duration_minutes' => $commit->poll->duration->value,
                    'closes_at' => $commit->poll->closes_at?->toIso8601String(),
                ] : null,
                'snippet' => $commit->snippet instanceof Snippet ? [
                    'language' => $commit->snippet->language->value,
                    'filename' => $commit->snippet->filename,
                    'code' => $commit->snippet->code,
                ] : null,
                'quoted_commit_id' => $commit->quoted_commit_id,
                'accepted_answer_id' => $commit->accepted_answer_id,
                'created_at' => $commit->created_at->toIso8601String(),
                'updated_at' => $commit->updated_at->toIso8601String(),
            ];
        }
    }

    /**
     * The user's votes, on the polls everyone can still see: a deleted or rejected poll is someone else's content.
     *
     * @return Generator<int, array{commit_id: string, option: string, created_at: string}>
     */
    private function pollVotes(User $user): Generator
    {
        $votes = PollVote::query()
            ->where('user_id', $user->id)
            ->whereHas('poll', fn (PollBuilder $poll): PollBuilder => $poll->whereIn('commit_id', Commit::query()->approved()->select('id')))
            ->with(['poll:id,commit_id', 'option:id,label'])
            ->select(['id', 'poll_id', 'poll_option_id', 'created_at']);

        foreach ($votes->lazyById(500) as $vote) {
            yield [
                'commit_id' => $vote->poll->commit_id,
                'option' => $vote->option->label,
                'created_at' => $vote->created_at->toIso8601String(),
            ];
        }
    }

    /**
     * @return Generator<int, array{username: string}>
     */
    private function following(User $user): Generator
    {
        foreach ($user->following()->select(['users.id', 'users.username'])->lazyById(500, 'users.id', 'id') as $followed) {
            yield ['username' => $followed->username];
        }
    }
}
