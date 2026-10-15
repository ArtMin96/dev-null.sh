<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\PollDuration;
use App\Queries\PollBuilder;
use Carbon\CarbonInterface;
use Database\Factories\PollFactory;
use Illuminate\Database\Eloquent\Attributes\UseEloquentBuilder;
use Illuminate\Database\Eloquent\Attributes\Visible;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property-read string $id
 * @property-read string $commit_id
 * @property-read PollDuration $duration
 * @property-read CarbonInterface|null $closes_at
 * @property-read CarbonInterface $created_at
 * @property-read CarbonInterface $updated_at
 * @property-read string|null $voted_option_id
 * @property-read bool|null $is_own
 * @property-read Collection<int, PollOption> $options
 */
#[Visible([
    'closes_at',
    'voted_option_id',
    'options',
])]
#[UseEloquentBuilder(PollBuilder::class)]
final class Poll extends Model
{
    /** @use HasFactory<PollFactory> */
    use HasFactory;

    use HasUuids;

    /**
     * @return array<string, string>
     */
    public function casts(): array
    {
        return [
            'id' => 'string',
            'commit_id' => 'string',
            'duration' => PollDuration::class,
            'closes_at' => 'datetime',
            'voted_option_id' => 'string',
            'created_at' => 'datetime',
            'updated_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<PollOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(PollOption::class)->orderBy('position');
    }

    /**
     * A poll opens once its commit is approved, and takes votes until the moment it closes.
     */
    public function isOpen(): bool
    {
        return $this->closes_at?->isFuture() ?? false;
    }

    public function isClosed(): bool
    {
        return $this->closes_at !== null && ! $this->isOpen();
    }

    /**
     * The counts stay hidden until the viewer voted, so they never sway a vote: only the poll's author follows them
     * before, and everyone sees them once the poll closed. Who voted for what is never shown to anyone.
     *
     * @return array<mixed>
     */
    public function toArray(): array
    {
        $revealsResults = $this->voted_option_id !== null || $this->is_own === true || $this->isClosed();

        return [
            ...parent::toArray(),
            'is_closed' => $this->isClosed(),
            'options' => $this->options->map(fn (PollOption $option): array => [
                ...$option->toArray(),
                'votes_count' => $revealsResults ? $option->votes_count : null,
            ])->all(),
        ];
    }
}
