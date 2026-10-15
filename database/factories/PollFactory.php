<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CommitStatus;
use App\Enums\CommitType;
use App\Enums\PollDuration;
use App\Models\Commit;
use App\Models\Poll;
use App\Models\PollOption;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Database\Eloquent\Factories\Sequence;

/**
 * @extends Factory<Poll>
 */
final class PollFactory extends Factory
{
    /**
     * Like a real poll, it is open once its commit is approved, and not before.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'commit_id' => Commit::factory()->state(['type' => CommitType::Poll]),
            'duration' => PollDuration::OneDay,
            'closes_at' => fn (array $attributes): ?CarbonInterface => Commit::query()->whereKey($attributes['commit_id'])->sole()->status === CommitStatus::Approved
                ? now()->addMinutes($attributes['duration'] instanceof PollDuration ? $attributes['duration']->value : PollDuration::OneDay->value)
                : null,
        ];
    }

    public function closed(): self
    {
        return $this->state(fn (array $attributes): array => [
            'closes_at' => now()->subMinute(),
        ]);
    }

    /**
     * @param  list<string>  $labels
     */
    public function withOptions(array $labels): self
    {
        return $this->has(
            PollOption::factory()
                ->count(count($labels))
                ->state(new Sequence(...array_map(
                    fn (string $label, int $position): array => ['label' => $label, 'position' => $position],
                    $labels,
                    array_keys($labels),
                ))),
            'options',
        );
    }
}
