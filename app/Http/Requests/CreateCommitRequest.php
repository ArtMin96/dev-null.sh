<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Actions\CreateCommitImages;
use App\Contracts\Dto\CommitTypeData;
use App\Enums\CommitType;
use App\Models\Commit;
use App\Models\User;
use App\Rules\VisibleCommit;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;
use Illuminate\Validation\Rules\File;

final class CreateCommitRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Images are checked by their contents, not their extension, and SVG is never accepted since it can carry scripts.
     * Some types of commits take no images at all.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $user = $this->user();
        assert($user instanceof User);

        $data = $this->commitType()->data();

        return [
            'body' => ['required', 'string', 'min:3', 'max:280'],
            'parent_id' => ['nullable', 'uuid', new VisibleCommit($user)],
            'images' => [
                Rule::prohibitedIf($data !== null && ! $data::allowsImages()),
                'nullable',
                'list',
                'max:'.CreateCommitImages::MAX_IMAGES,
            ],
            'images.*' => [
                'required',
                'image',
                File::types(['image/jpeg', 'image/png', 'image/gif', 'image/webp'])->max(CreateCommitImages::MAX_UPLOAD_SIZE),
                Rule::dimensions()
                    ->maxWidth(CreateCommitImages::MAX_SOURCE_DIMENSION)
                    ->maxHeight(CreateCommitImages::MAX_SOURCE_DIMENSION),
            ],
            'type' => ['nullable', Rule::enum(CommitType::class)->when(
                $this->filled('parent_id'),
                fn (Enum $rule): Enum => $rule->only(CommitType::Text),
            )],
            ...($data === null ? [] : $data::rules($user)),
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'images.prohibited' => __("A :type can't have images.", ['type' => $this->commitType()->value]),
        ];
    }

    /**
     * @return list<UploadedFile>
     */
    public function images(): array
    {
        $images = $this->file('images');

        return is_array($images) ? array_values($images) : [];
    }

    public function parent(): ?Commit
    {
        $user = $this->user();
        assert($user instanceof User);

        return $this->filled('parent_id')
            ? Commit::query()->visibleTo($user)->find($this->string('parent_id')->value())
            : null;
    }

    /**
     * The requested type, falling back to text: an invalid one never gets past validation.
     */
    public function commitType(): CommitType
    {
        $type = $this->input('type');

        return (is_string($type) ? CommitType::tryFrom($type) : null) ?? CommitType::Text;
    }

    /**
     * The validated data of the commit's type, or null for a plain text commit.
     */
    public function typeData(): ?CommitTypeData
    {
        $data = $this->commitType()->data();

        return $data === null ? null : $data::fromValidated($this->safe());
    }
}
