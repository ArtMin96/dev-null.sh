<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CommitType;
use App\Models\Commit;
use App\Models\Release;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Release>
 */
final class ReleaseFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'commit_id' => Commit::factory()->state(['type' => CommitType::Release]),
            'project' => fake()->slug(2),
            'version' => 'v'.fake()->semver(),
            'url' => null,
            'notes' => null,
        ];
    }
}
