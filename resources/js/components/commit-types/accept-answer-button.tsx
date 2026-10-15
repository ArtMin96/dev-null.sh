import { usePage } from '@inertiajs/react';
import { BadgeCheck } from 'lucide-react';
import { useContext } from 'react';
import { AcceptedAnswerContext } from '@/components/commit-types/accepted-answer';
import type { Commit } from '@/types';

/**
 * Lets the author of a question accept an approved direct reply as its answer, or take that back.
 */
export function AcceptAnswerButton({ reply }: { reply: Commit }) {
    const { auth } = usePage().props;
    const acceptedAnswer = useContext(AcceptedAnswerContext);

    if (
        !acceptedAnswer ||
        acceptedAnswer.question.type !== 'question' ||
        acceptedAnswer.question.user_id !== auth.user.id ||
        reply.parent_id !== acceptedAnswer.question.id ||
        reply.status !== 'approved'
    ) {
        return null;
    }

    const isAccepted = acceptedAnswer.answerId === reply.id;

    return (
        <button
            type="button"
            onClick={() =>
                isAccepted
                    ? acceptedAnswer.unaccept()
                    : acceptedAnswer.accept(reply)
            }
            aria-disabled={acceptedAnswer.isUpdating}
            aria-pressed={isAccepted}
            title={
                isAccepted
                    ? 'Remove as the accepted answer'
                    : 'Accept as the answer to your question'
            }
            className="flex items-center gap-1.5 rounded-md px-2 py-0.5 text-muted-foreground transition-colors hover:bg-accent hover:text-foreground aria-disabled:pointer-events-none aria-disabled:opacity-50 aria-pressed:text-emerald-700 dark:aria-pressed:text-emerald-400"
            data-test="accept-answer-button"
        >
            <BadgeCheck aria-hidden className="size-4" />
            {isAccepted ? 'Unaccept' : 'Accept answer'}
        </button>
    );
}
