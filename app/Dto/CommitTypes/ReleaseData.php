<?php

declare(strict_types=1);

namespace App\Dto\CommitTypes;

use App\Contracts\Dto\CommitTypeData;
use App\Enums\CommitType;
use App\Models\Commit;
use App\Models\User;
use App\Rules\Utf8;
use Illuminate\Support\ValidatedInput;

final readonly class ReleaseData implements CommitTypeData
{
    public function __construct(
        public string $project,
        public string $version,
        public ?string $url = null,
        public ?string $notes = null,
    ) {
        //
    }

    /**
     * @return array<string, list<string|Utf8>>
     */
    public static function rules(User $user): array
    {
        return [
            'release' => ['required', 'array:project,version,url,notes'],
            'release.project' => ['required', 'string', new Utf8(), 'max:50'],
            'release.version' => ['required', 'string', new Utf8(), 'max:30', 'not_regex:/\s/'],
            'release.url' => ['nullable', 'string', new Utf8(), 'url:http,https', 'max:255'],
            'release.notes' => ['nullable', 'string', new Utf8(), 'max:1000'],
        ];
    }

    public static function allowsImages(): bool
    {
        return true;
    }

    public static function fromValidated(ValidatedInput $input): self
    {
        return new self(
            project: $input->string('release.project')->value(),
            version: $input->string('release.version')->value(),
            url: $input->filled('release.url') ? $input->string('release.url')->value() : null,
            notes: $input->filled('release.notes') ? $input->string('release.notes')->value() : null,
        );
    }

    public function type(): CommitType
    {
        return CommitType::Release;
    }

    /**
     * @return array{}
     */
    public function attributes(): array
    {
        return [];
    }

    public function saveFor(Commit $commit): void
    {
        $commit->release()->create([
            'project' => $this->project,
            'version' => $this->version,
            'url' => $this->url,
            'notes' => $this->notes,
        ]);
    }
}
