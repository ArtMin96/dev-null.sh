import { cn } from '@/lib/utils';

export function QuestionBadge({ isAnswered }: { isAnswered: boolean }) {
    return (
        <span
            className={cn(
                'shrink-0 self-center rounded-full px-2 py-0.5 font-mono text-xs',
                isAnswered
                    ? 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400'
                    : 'bg-primary-soft text-primary',
            )}
            title={
                isAnswered
                    ? 'The author accepted a reply as the answer.'
                    : 'This question has no accepted answer yet.'
            }
            data-test="question-status"
        >
            {isAnswered ? 'answered' : 'question'}
        </span>
    );
}
