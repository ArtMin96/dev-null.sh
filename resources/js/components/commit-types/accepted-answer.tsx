import { router, useHttp } from '@inertiajs/react';
import { BadgeCheck } from 'lucide-react';
import { createContext, useContext } from 'react';
import { toast } from 'sonner';
import CommitAnswerAcceptanceController from '@/actions/App/Http/Controllers/CommitAnswerAcceptanceController';
import CommitAnswerRevocationController from '@/actions/App/Http/Controllers/CommitAnswerRevocationController';
import type { Commit } from '@/types';

export type AcceptedAnswer = {
    question: Commit;
    answerId: string | null;
    isUpdating: boolean;
    accept: (answer: Commit) => void;
    unaccept: () => void;
};

/**
 * The accepted answer of the question a thread belongs to, for its replies.
 */
export const AcceptedAnswerContext = createContext<AcceptedAnswer | null>(null);

/**
 * The accepted answer of a question, shared by its card and its thread. Accepting or removing it shows at once, and
 * rolls back when the server refuses.
 */
export function useAcceptedAnswer(question: Commit): AcceptedAnswer {
    const request = useHttp<
        { answer_id: string | null },
        { accepted_answer_id: string | null }
    >({
        answer_id: question.is_answered
            ? (question.accepted_answer_id ?? null)
            : null,
    });

    const fail = () =>
        toast.error('Could not update the accepted answer. Try again.');

    const update = (answerId: string | null) => {
        if (request.processing) {
            return;
        }

        request
            .optimistic(() => ({ answer_id: answerId }))
            .submit(
                answerId === null
                    ? CommitAnswerRevocationController(question)
                    : CommitAnswerAcceptanceController(question),
                { onSuccess: () => router.flushAll(), onError: fail },
            )
            .catch(fail);
    };

    return {
        question,
        answerId: request.data.answer_id,
        isUpdating: request.processing,
        accept: (answer) => update(answer.id),
        unaccept: () => update(null),
    };
}

export function AcceptedAnswerBadge({ reply }: { reply: Commit }) {
    const acceptedAnswer = useContext(AcceptedAnswerContext);

    if (acceptedAnswer?.answerId !== reply.id) {
        return null;
    }

    return (
        <span
            className="flex shrink-0 items-center gap-1 self-center rounded-full bg-emerald-500/10 px-1.5 font-mono text-[11px] text-emerald-700 dark:text-emerald-400"
            data-test="accepted-answer"
        >
            <BadgeCheck aria-hidden className="size-3" />
            Accepted answer
        </span>
    );
}
