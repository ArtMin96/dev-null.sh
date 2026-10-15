import { Form, usePage } from '@inertiajs/react';
import type { KeyboardEvent } from 'react';
import CommitController from '@/actions/App/Http/Controllers/CommitController';
import {
    ImagePickerButton,
    ImagePickerPreviews,
    imageErrorFrom,
} from '@/components/commit-image-picker';
import { QuotedCommitSummary } from '@/components/commit-types/quote-card';
import InputError from '@/components/input-error';
import {
    hasOpenMentionSuggestions,
    MentionTextarea,
} from '@/components/mention-textarea';
import { StashLabel } from '@/components/stash-label';
import { Button } from '@/components/ui/button';
import {
    DialogContent,
    DialogDescription,
    DialogTitle,
} from '@/components/ui/dialog';
import { UserAvatar } from '@/components/user-avatar';
import { useImageAttachments } from '@/hooks/use-image-attachments';
import { moveCaretToEnd, useStash } from '@/hooks/use-stash';
import { cn } from '@/lib/utils';
import type { Commit } from '@/types';

const minBodyLength = 3;
const maxBodyLength = 280;

export function QuoteDialogContent({
    commit,
    onClose,
}: {
    commit: Commit;
    onClose: () => void;
}) {
    return (
        <DialogContent
            onEscapeKeyDown={(event) => {
                if (hasOpenMentionSuggestions()) {
                    event.preventDefault();
                }
            }}
            className="gap-3 p-4 sm:max-w-xl sm:p-5"
            data-test="quote-dialog"
        >
            <DialogTitle className="text-base">
                Quote @{commit.user.username}
            </DialogTitle>
            <DialogDescription className="sr-only">
                Write a commit of your own. The quoted commit shows below it.
            </DialogDescription>

            <QuoteForm commit={commit} onClose={onClose} />
        </DialogContent>
    );
}

function QuoteForm({
    commit,
    onClose,
}: {
    commit: Commit;
    onClose: () => void;
}) {
    const { auth } = usePage().props;
    const { body, isRestored, updateBody, drop } = useStash(
        auth.user.id,
        `quote-${commit.id}`,
    );
    const attachments = useImageAttachments();

    const remaining = maxBodyLength - body.length;
    const isQuotable = body.trim().length >= minBodyLength;
    const bodyId = `quote-${commit.id}`;

    const submitOnModEnter = (event: KeyboardEvent<HTMLTextAreaElement>) => {
        if (
            event.key === 'Enter' &&
            (event.metaKey || event.ctrlKey) &&
            isQuotable
        ) {
            event.preventDefault();
            event.currentTarget.form?.requestSubmit();
        }
    };

    return (
        <Form
            {...CommitController.store.form()}
            errorBag="quote"
            transform={(data) =>
                attachments.images.length > 0
                    ? { ...data, images: attachments.images }
                    : data
            }
            options={{ preserveScroll: true }}
            onSuccess={(page) => {
                if (page.flash.committed) {
                    drop();
                    attachments.clear();
                    onClose();
                }
            }}
            className="flex min-w-0 gap-3"
        >
            {({ processing, errors }) => (
                <>
                    <UserAvatar
                        user={auth.user}
                        className="hidden size-10 shrink-0 sm:flex"
                    />

                    <div className="min-w-0 flex-1">
                        <input type="hidden" name="type" value="quote" />
                        <input
                            type="hidden"
                            name="quote[commit_id]"
                            value={commit.id}
                        />
                        <label htmlFor={bodyId} className="sr-only">
                            Your take
                        </label>
                        <MentionTextarea
                            id={bodyId}
                            name="body"
                            rows={1}
                            autoFocus
                            value={body}
                            onValueChange={updateBody}
                            onKeyDown={submitOnModEnter}
                            onPaste={attachments.onPaste}
                            onDragOver={attachments.onDragOver}
                            onDrop={attachments.onDrop}
                            onFocus={isRestored ? moveCaretToEnd : undefined}
                            maxLength={maxBodyLength}
                            placeholder="Add your take"
                            autoComplete="off"
                            aria-invalid={errors.body ? true : undefined}
                            aria-describedby={`${bodyId}-count`}
                            className="block field-sizing-content min-h-10 w-full resize-none bg-transparent py-1.5 text-[17px] leading-snug outline-none placeholder:text-muted-foreground focus-visible:outline-none"
                            data-test="quote-body-input"
                        />
                        <InputError message={errors.body} />

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

                        <div className="mt-3 rounded-lg border px-3 py-2.5">
                            <QuotedCommitSummary quoted={commit} />
                        </div>
                        <InputError
                            message={
                                errors['quote.commit_id'] ??
                                errors.quote ??
                                errors.type
                            }
                            className="mt-1"
                        />

                        <div className="mt-3 flex items-center justify-end gap-2">
                            <div className="mr-auto flex items-center gap-2">
                                <ImagePickerButton
                                    count={attachments.images.length}
                                    disabled={processing}
                                    onPick={attachments.attach}
                                    className="size-8"
                                />
                                {isRestored && (
                                    <StashLabel
                                        onDrop={() => {
                                            drop();
                                            document
                                                .getElementById(bodyId)
                                                ?.focus();
                                        }}
                                        className="text-[13px]"
                                    />
                                )}
                            </div>
                            <span
                                id={`${bodyId}-count`}
                                aria-label={`${remaining} characters left`}
                                className={cn(
                                    'mr-1 font-mono text-[13px] text-muted-foreground',
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
                                type="button"
                                variant="ghost"
                                onClick={onClose}
                            >
                                Cancel
                            </Button>
                            <Button
                                type="submit"
                                disabled={processing || !isQuotable}
                                title="Quote (⌘/Ctrl + Enter)"
                                data-test="submit-quote-button"
                            >
                                {processing ? 'Quoting…' : 'Quote'}
                            </Button>
                        </div>
                    </div>
                </>
            )}
        </Form>
    );
}
