import { usePage } from '@inertiajs/react';
import { Quote } from 'lucide-react';
import { useState } from 'react';
import { QuoteDialogContent } from '@/components/commit-types/quote-dialog';
import { Dialog, DialogTrigger } from '@/components/ui/dialog';
import { useHasStash } from '@/hooks/use-stash';
import type { Commit } from '@/types';

/**
 * Opens the quote dialog of an approved commit, the only kind that can be quoted. Hints at a quote of it waiting in
 * the stash, the way "Reply" does.
 */
export function QuoteButton({
    commit,
    keyShortcut,
}: {
    commit: Commit;
    keyShortcut?: string;
}) {
    const { auth } = usePage().props;
    const [isOpen, setIsOpen] = useState(false);
    const hasStash = useHasStash(auth.user.id, `quote-${commit.id}`);

    if (commit.status !== 'approved') {
        return null;
    }

    return (
        <Dialog open={isOpen} onOpenChange={setIsOpen}>
            <DialogTrigger asChild>
                <button
                    type="button"
                    aria-keyshortcuts={keyShortcut}
                    title={keyShortcut ? `Quote (${keyShortcut})` : undefined}
                    className="flex items-center gap-1 rounded-md px-1.5 py-0.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground sm:gap-1.5 sm:px-2"
                    data-test="quote-button"
                >
                    <Quote aria-hidden className="size-4" />
                    <span className="sr-only sm:not-sr-only">Quote</span>
                    {hasStash && !isOpen && (
                        <span
                            title="You have an unsent quote here"
                            className="font-mono text-[11px] opacity-70"
                            data-test="quote-stash-hint"
                        >
                            {'stash@{0}'}
                        </span>
                    )}
                </button>
            </DialogTrigger>
            <QuoteDialogContent
                commit={commit}
                onClose={() => setIsOpen(false)}
            />
        </Dialog>
    );
}
