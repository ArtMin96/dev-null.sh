import { useFormContext } from '@inertiajs/react';
import { Plus, X } from 'lucide-react';
import type { ChangeEvent } from 'react';
import { useState } from 'react';
import type { ComposerFieldsProps } from '@/components/commit-types/composer-types';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { emojifyInput } from '@/lib/emoji';

const minOptions = 2;
const maxOptions = 4;

/**
 * Keep in sync with app/Enums/PollDuration.php: how long a poll stays open once approved, in minutes.
 */
const durations = [
    { minutes: '60', label: '1 hour' },
    { minutes: '1440', label: '1 day' },
    { minutes: '4320', label: '3 days' },
    { minutes: '10080', label: '7 days' },
];

type PollForm = {
    poll: { options: string[]; duration: string };
};

type OptionDraft = {
    key: number;
    label: string;
};

const focusOption = (key: number) =>
    requestAnimationFrame(() =>
        document.getElementById(`poll-option-${key}`)?.focus(),
    );

export function PollFields({ onCompletenessChange }: ComposerFieldsProps) {
    const errors = useFormContext<PollForm>()?.errors ?? {};
    const [options, setOptions] = useState<OptionDraft[]>([
        { key: 0, label: '' },
        { key: 1, label: '' },
    ]);

    const update = (next: OptionDraft[]) => {
        setOptions(next);
        onCompletenessChange(
            next.every((option) => option.label.trim() !== ''),
        );
    };

    const addOption = () => {
        const key = Math.max(...options.map((option) => option.key)) + 1;

        update([...options, { key, label: '' }]);
        focusOption(key);
    };

    const removeOption = (index: number) => {
        update(options.filter((_, other) => other !== index));
        focusOption(options[index - 1].key);
    };

    const changeOption = (
        index: number,
        event: ChangeEvent<HTMLInputElement>,
    ) => {
        const input = event.currentTarget;
        const { text, caret } = emojifyInput(
            input.value,
            input.selectionStart ?? input.value.length,
        );

        update(
            options.map((option, other) =>
                other === index ? { ...option, label: text } : option,
            ),
        );

        if (text !== input.value) {
            requestAnimationFrame(() => input.setSelectionRange(caret, caret));
        }
    };

    return (
        <div className="mt-2 space-y-2" data-test="poll-fields">
            {options.map((option, index) => {
                const error = errors[`poll.options.${index}`];

                return (
                    <div key={option.key}>
                        <div className="flex items-center gap-1">
                            <label
                                htmlFor={`poll-option-${option.key}`}
                                className="sr-only"
                            >
                                Option {index + 1}
                            </label>
                            <Input
                                id={`poll-option-${option.key}`}
                                name="poll[options][]"
                                value={option.label}
                                onChange={(event) => changeOption(index, event)}
                                required
                                maxLength={50}
                                placeholder={`Option ${index + 1}`}
                                autoComplete="off"
                                aria-invalid={error ? true : undefined}
                                data-test="poll-option-input"
                            />
                            {index >= minOptions && (
                                <Button
                                    type="button"
                                    variant="ghost"
                                    size="icon"
                                    onClick={() => removeOption(index)}
                                    aria-label={`Remove option ${index + 1}`}
                                    title="Remove option"
                                    className="shrink-0 text-muted-foreground"
                                    data-test="poll-remove-option-button"
                                >
                                    <X />
                                </Button>
                            )}
                        </div>
                        <InputError message={error} className="mt-1" />
                    </div>
                );
            })}
            <InputError message={errors['poll.options'] ?? errors.poll} />

            <div className="flex items-center gap-2">
                {options.length < maxOptions && (
                    <Button
                        type="button"
                        variant="ghost"
                        size="sm"
                        onClick={addOption}
                        className="-ml-2.5 text-primary hover:text-primary"
                        data-test="poll-add-option-button"
                    >
                        <Plus />
                        Add option
                    </Button>
                )}

                <div className="ml-auto flex items-center gap-2">
                    <label
                        htmlFor="poll-duration"
                        className="text-sm text-muted-foreground"
                    >
                        Closes in
                    </label>
                    <Select name="poll[duration]" defaultValue="1440">
                        <SelectTrigger
                            id="poll-duration"
                            size="sm"
                            aria-invalid={
                                errors['poll.duration'] ? true : undefined
                            }
                            data-test="poll-duration-select"
                        >
                            <SelectValue />
                        </SelectTrigger>
                        <SelectContent>
                            {durations.map((duration) => (
                                <SelectItem
                                    key={duration.minutes}
                                    value={duration.minutes}
                                >
                                    {duration.label}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                </div>
            </div>
            <InputError message={errors['poll.duration']} />
        </div>
    );
}
