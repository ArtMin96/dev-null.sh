<?php

declare(strict_types=1);

namespace App\Dto\CommitTypes;

use App\Contracts\Dto\CommitTypeData;
use App\Enums\CommitStatus;
use App\Enums\CommitType;
use App\Enums\PollDuration;
use App\Models\Commit;
use App\Models\User;
use App\Rules\Utf8;
use App\Services\EmojiShortcodes;
use Illuminate\Support\ValidatedInput;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Enum;

final readonly class PollData implements CommitTypeData
{
    /**
     * @param  list<string>  $options
     */
    public function __construct(
        public array $options,
        public PollDuration $duration,
    ) {
        //
    }

    /**
     * Options are compared once trimmed and ignoring case, so "Tabs" and "tabs " are the same option.
     *
     * @return array<string, list<string|Enum|Utf8>>
     */
    public static function rules(User $user): array
    {
        return [
            'poll' => ['required', 'array:options,duration'],
            'poll.options' => ['required', 'list', 'min:2', 'max:4'],
            'poll.options.*' => ['bail', 'required', 'string', new Utf8(), 'max:50', 'distinct:ignore_case'],
            'poll.duration' => ['required', Rule::enum(PollDuration::class)],
        ];
    }

    /**
     * A poll is about its options: images would only crowd them.
     */
    public static function allowsImages(): bool
    {
        return false;
    }

    public static function fromValidated(ValidatedInput $input): self
    {
        return new self(
            options: array_values(array_filter($input->array('poll.options'), is_string(...))),
            duration: PollDuration::from($input->integer('poll.duration')),
        );
    }

    public function type(): CommitType
    {
        return CommitType::Poll;
    }

    /**
     * @return array{}
     */
    public function attributes(): array
    {
        return [];
    }

    /**
     * The poll's clock starts once everyone can see it: right away for a commit approved at once, otherwise on approval.
     * Emoji shortcodes in its options become emoji, like the ones in the commit's body.
     */
    public function saveFor(Commit $commit): void
    {
        $emojiShortcodes = resolve(EmojiShortcodes::class);

        $poll = $commit->poll()->create([
            'duration' => $this->duration,
            'closes_at' => $commit->status === CommitStatus::Approved ? now()->addMinutes($this->duration->value) : null,
        ]);

        $poll->options()->createMany(array_map(
            fn (string $label, int $position): array => ['label' => $emojiShortcodes->replace($label), 'position' => $position],
            $this->options,
            array_keys($this->options),
        ));
    }
}
