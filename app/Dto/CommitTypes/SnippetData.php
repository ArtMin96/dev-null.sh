<?php

declare(strict_types=1);

namespace App\Dto\CommitTypes;

use App\Contracts\Dto\CommitTypeData;
use App\Enums\CommitType;
use App\Enums\SnippetLanguage;
use App\Models\Commit;
use App\Models\User;
use App\Rules\Utf8;
use Closure;
use Illuminate\Support\Str;
use Illuminate\Support\ValidatedInput;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final readonly class SnippetData implements CommitTypeData
{
    public const int MAX_LENGTH = 4000;

    public const int MAX_LINES = 200;

    public function __construct(
        public SnippetLanguage $language,
        public string $code,
        public ?string $filename = null,
    ) {
        //
    }

    /**
     * @return array<string, list<string|Enum|Utf8|Closure>>
     */
    public static function rules(User $user): array
    {
        return [
            'snippet' => ['required', 'array:language,filename,code'],
            'snippet.language' => ['required', Rule::enum(SnippetLanguage::class)],
            'snippet.filename' => ['nullable', 'string', new Utf8(), 'max:100', 'regex:/^[\w.\-\/]+$/'],
            'snippet.code' => ['required', 'string', new Utf8(), function (string $attribute, mixed $code, Closure $fail): void {
                $code = is_string($code) ? self::normalize($code) : '';

                if (Str::trim($code) === '') {
                    $fail('The :attribute field is required.');
                } elseif (mb_strlen($code) > self::MAX_LENGTH) {
                    $fail('The :attribute field must not be greater than '.self::MAX_LENGTH.' characters.');
                } elseif (mb_substr_count($code, "\n") >= self::MAX_LINES) {
                    $fail('The :attribute field must not have more than '.self::MAX_LINES.' lines.');
                }
            }],
        ];
    }

    public static function allowsImages(): bool
    {
        return true;
    }

    /**
     * The code is kept exactly as typed, except for the newlines trailing it, and line breaks are always `\n`: browsers
     * send the ones typed in a form as `\r\n`.
     */
    public static function fromValidated(ValidatedInput $input): self
    {
        return new self(
            language: SnippetLanguage::from($input->string('snippet.language')->value()),
            code: self::normalize($input->string('snippet.code')->value()),
            filename: $input->filled('snippet.filename') ? $input->string('snippet.filename')->value() : null,
        );
    }

    public function type(): CommitType
    {
        return CommitType::Snippet;
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
        $commit->snippet()->create([
            'language' => $this->language,
            'filename' => $this->filename,
            'code' => $this->code,
        ]);
    }

    private static function normalize(string $code): string
    {
        return mb_rtrim(str_replace(["\r\n", "\r"], "\n", $code), "\n");
    }
}
