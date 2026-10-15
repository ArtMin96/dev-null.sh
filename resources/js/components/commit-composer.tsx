import { Form, usePage } from '@inertiajs/react';
import type { KeyboardEvent } from 'react';
import { useState } from 'react';
import CommitController from '@/actions/App/Http/Controllers/CommitController';
import {
    ImagePickerButton,
    ImagePickerPreviews,
    imageErrorFrom,
} from '@/components/commit-image-picker';
import { composerTypes } from '@/components/commit-types/composer-types';
import { GraphRail } from '@/components/graph-rail';
import InputError from '@/components/input-error';
import { MentionTextarea } from '@/components/mention-textarea';
import { StashLabel } from '@/components/stash-label';
import { Button } from '@/components/ui/button';
import { UserAvatar } from '@/components/user-avatar';
import { useImageAttachments } from '@/hooks/use-image-attachments';
import { moveCaretToEnd, useStash } from '@/hooks/use-stash';
import { cn, laneColor } from '@/lib/utils';
import type { CommitType } from '@/types';

const minBodyLength = 3;
const maxBodyLength = 280;

export function CommitComposer() {
    const { auth } = usePage().props;
    const { body, isRestored, updateBody, drop } = useStash(
        auth.user.id,
        'commit',
    );
    const attachments = useImageAttachments();
    const [type, setType] = useState<CommitType>('text');
    const [isTypeComplete, setIsTypeComplete] = useState(true);

    const activeType = composerTypes.find((entry) => entry.type === type);
    const allowsImages = activeType?.allowsImages ?? true;

    const remaining = maxBodyLength - body.length;
    const isCommittable = body.trim().length >= minBodyLength && isTypeComplete;

    const changeType = (next: CommitType) => {
        setType(next);
        setIsTypeComplete(
            !composerTypes.find((entry) => entry.type === next)
                ?.startsIncomplete,
        );
    };

    const submitOnModEnter = (event: KeyboardEvent<HTMLTextAreaElement>) => {
        if (
            event.key === 'Enter' &&
            (event.metaKey || event.ctrlKey) &&
            isCommittable
        ) {
            event.preventDefault();
            event.currentTarget.form?.requestSubmit();
        }
    };

    return (
        <Form
            {...CommitController.store.form()}
            transform={(data) =>
                attachments.images.length > 0
                    ? { ...data, images: attachments.images }
                    : data
            }
            options={{ preserveScroll: true }}
            onSuccess={(page) => {
                if (!page.flash.committed) {
                    return;
                }

                drop();
                attachments.clear();
                changeType('text');
                requestAnimationFrame(() =>
                    document.getElementById('body')?.focus(),
                );
            }}
            className="flex"
        >
            {({ processing, errors }) => (
                <>
                    <GraphRail
                        lane={laneColor(auth.user.lane)}
                        variant="staged"
                        isFilled={isCommittable}
                    />

                    <div className="flex min-w-0 flex-1 gap-3 border-b py-3 pr-4">
                        <UserAvatar
                            user={auth.user}
                            className="hidden size-10 sm:flex"
                        />

                        <div className="min-w-0 flex-1">
                            <input type="hidden" name="type" value={type} />
                            <label htmlFor="body" className="sr-only">
                                Body
                            </label>
                            <MentionTextarea
                                id="body"
                                name="body"
                                rows={1}
                                value={body}
                                onValueChange={updateBody}
                                onKeyDown={submitOnModEnter}
                                onPaste={
                                    allowsImages
                                        ? attachments.onPaste
                                        : undefined
                                }
                                onDragOver={
                                    allowsImages
                                        ? attachments.onDragOver
                                        : undefined
                                }
                                onDrop={
                                    allowsImages
                                        ? attachments.onDrop
                                        : undefined
                                }
                                onFocus={
                                    isRestored ? moveCaretToEnd : undefined
                                }
                                maxLength={maxBodyLength}
                                placeholder={
                                    activeType?.placeholder ??
                                    'What did you ship?'
                                }
                                autoComplete="off"
                                aria-invalid={errors.body ? true : undefined}
                                aria-describedby="body-count"
                                className="block field-sizing-content min-h-10 w-full resize-none bg-transparent py-1.5 text-[17px] leading-relaxed outline-none placeholder:text-muted-foreground focus-visible:outline-none"
                            />

                            <InputError message={errors.body} />

                            {activeType && (
                                <activeType.Fields
                                    onCompletenessChange={setIsTypeComplete}
                                />
                            )}
                            <InputError message={errors.type} />

                            <ImagePickerPreviews
                                images={attachments.images}
                                onRemove={attachments.remove}
                            />
                            <InputError
                                message={
                                    attachments.error ?? imageErrorFrom(errors)
                                }
                                className="mt-1"
                            />

                            <div className="mt-2 flex items-center justify-end gap-4">
                                <div className="mr-auto flex items-center gap-4">
                                    <div className="-ml-2 flex items-center">
                                        {allowsImages && (
                                            <ImagePickerButton
                                                count={
                                                    attachments.images.length
                                                }
                                                disabled={processing}
                                                onPick={attachments.attach}
                                            />
                                        )}
                                        <CommitTypePicker
                                            type={type}
                                            hasImages={
                                                attachments.images.length > 0
                                            }
                                            disabled={processing}
                                            onChange={changeType}
                                        />
                                    </div>
                                    {isRestored && (
                                        <StashLabel
                                            onDrop={() => {
                                                drop();
                                                document
                                                    .getElementById('body')
                                                    ?.focus();
                                            }}
                                            className="text-[13px]"
                                        />
                                    )}
                                </div>
                                <span
                                    id="body-count"
                                    aria-label={`${remaining} characters left`}
                                    className={cn(
                                        'font-mono text-[13px] text-muted-foreground',
                                        {
                                            'text-amber-700 dark:text-amber-400':
                                                remaining <= 20,
                                            'text-destructive': remaining === 0,
                                        },
                                    )}
                                >
                                    {remaining}
                                </span>
                                <Button
                                    type="submit"
                                    disabled={processing || !isCommittable}
                                    title="Commit (⌘/Ctrl + Enter)"
                                    data-test="create-commit-button"
                                >
                                    {processing ? 'Committing…' : 'Commit'}
                                </Button>
                            </div>
                        </div>
                    </div>
                </>
            )}
        </Form>
    );
}

/**
 * A type that takes no images can't be picked while images are attached: they would be refused.
 */
function CommitTypePicker({
    type,
    hasImages,
    disabled,
    onChange,
}: {
    type: CommitType;
    hasImages: boolean;
    disabled?: boolean;
    onChange: (type: CommitType) => void;
}) {
    return (
        <div
            role="group"
            aria-label="Commit type"
            className="flex items-center"
        >
            {composerTypes.map((entry) => {
                const isActive = entry.type === type;
                const isBlocked = hasImages && entry.allowsImages === false;

                return (
                    <Button
                        key={entry.type}
                        type="button"
                        variant="ghost"
                        size="icon"
                        disabled={disabled}
                        onClick={() => {
                            if (!isBlocked) {
                                onChange(isActive ? 'text' : entry.type);
                            }
                        }}
                        aria-label={entry.label}
                        aria-pressed={isActive}
                        aria-disabled={isBlocked || undefined}
                        title={
                            isBlocked
                                ? `Remove the images to make a ${entry.label.toLowerCase()}`
                                : entry.label
                        }
                        className="text-muted-foreground aria-disabled:cursor-not-allowed aria-disabled:opacity-50 aria-pressed:bg-primary-soft aria-pressed:text-primary"
                        data-test={`composer-type-${entry.type}`}
                    >
                        <entry.icon className="size-5" />
                    </Button>
                );
            })}
        </div>
    );
}
