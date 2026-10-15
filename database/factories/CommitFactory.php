<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CommitStatus;
use App\Enums\CommitType;
use App\Models\Commit;
use App\Models\Release;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Commit>
 */
final class CommitFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'body' => fake()->realText(280),
            'type' => CommitType::Text,
            'status' => CommitStatus::Approved,
        ];
    }

    public function pending(): self
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CommitStatus::Pending,
        ]);
    }

    public function rejected(): self
    {
        return $this->state(fn (array $attributes): array => [
            'status' => CommitStatus::Rejected,
        ]);
    }

    /**
     * @param  array<string, mixed>  $release
     */
    public function release(array $release = []): self
    {
        return $this->state(fn (array $attributes): array => [
            'type' => CommitType::Release,
        ])->has(Release::factory()->state($release), 'release');
    }
}
