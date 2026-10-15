<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\CommitType;
use App\Enums\SnippetLanguage;
use App\Models\Commit;
use App\Models\Snippet;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Snippet>
 */
final class SnippetFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'commit_id' => Commit::factory()->state(['type' => CommitType::Snippet]),
            'language' => SnippetLanguage::Php,
            'filename' => null,
            'code' => "<?php\n\necho 'Hello, devnull!';",
        ];
    }
}
