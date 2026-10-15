<?php

declare(strict_types=1);

namespace App\Queries;

use App\Enums\CommitStatus;
use App\Models\Commit;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * @extends Builder<Commit>
 */
final class CommitBuilder extends Builder
{
    /**
     * Approved commits are visible to everyone, while authors may also see their own pending or rejected commits.
     */
    public function visibleTo(User $user): self
    {
        return $this->where(fn (Builder $query): Builder => $query
            ->where($this->qualifyColumn('status'), CommitStatus::Approved)
            ->orWhere($this->qualifyColumn('user_id'), $user->id));
    }

    /**
     * Deleted commits stay in a thread as tombstones while they still have replies the given user can see.
     */
    public function visibleInThreadTo(User $user): self
    {
        return $this->withTrashed()
            ->visibleTo($user)
            ->where(fn (self $query): self => $query
                ->whereNull($query->getModel()->getQualifiedDeletedAtColumn())
                ->orWhereHas('replies', fn (self $replies): self => $replies->withTrashed()->visibleTo($user)));
    }

    public function topLevel(): self
    {
        return $this->whereNull('parent_id');
    }

    /**
     * Counts only the replies the given user is allowed to see.
     */
    public function withVisibleReplyCount(User $user): self
    {
        return $this->withCount([
            'replies' => fn (self $query): self => $query->visibleInThreadTo($user),
        ]);
    }

    public function approved(): self
    {
        return $this->where('status', CommitStatus::Approved);
    }

    public function withSavedState(User $user): self
    {
        return $this->withExists([
            'bookmarks as is_saved' => fn (Builder $query): Builder => $query->where('user_id', $user->id),
        ]);
    }

    public function pending(): self
    {
        return $this->where('status', CommitStatus::Pending);
    }

    /**
     * Eager loads everything a commit card renders: the author, the link preview, the images, and the data of every
     * commit type. Each type with data adds its relation here, scoped to the viewer when what they see depends on who they are.
     */
    public function withContentFor(User $viewer): self
    {
        return $this->with(['user', 'linkPreview', 'images', 'release']);
    }

    public function withStarsFor(User $viewer): self
    {
        return $this
            ->withCount('stars')
            ->withExists([
                'stars as starred' => fn (Builder $query): Builder => $query->whereKey($viewer->id),
            ]);
    }
}
