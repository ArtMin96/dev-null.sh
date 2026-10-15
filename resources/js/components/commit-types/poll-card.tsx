import { router, useHttp, usePage } from '@inertiajs/react';
import { Check } from 'lucide-react';
import type { CSSProperties } from 'react';
import { useId, useRef, useState } from 'react';
import { toast } from 'sonner';
import CommitPollVoteController from '@/actions/App/Http/Controllers/CommitPollVoteController';
import { Button } from '@/components/ui/button';
import { useNow } from '@/hooks/use-now';
import { cn } from '@/lib/utils';
import type { Commit, Poll, PollOption } from '@/types';

const failedVoteMessage = 'Could not cast your vote. Try again.';

/**
 * Voting takes two steps, picking an option and pressing "Vote", since a vote is final. The results only show once the
 * server sends them: to voters, to the poll's author, and to everyone once the poll closed.
 */
export function PollCard({ commit, poll }: { commit: Commit; poll: Poll }) {
    const { auth } = usePage().props;
    const now = useNow();
    const name = useId();
    const pollRef = useRef<HTMLDivElement>(null);
    const [choice, setChoice] = useState<string | null>(null);
    const vote = useHttp<{ option_id: string | null }, Poll>({
        option_id: null,
    });

    const current = withVote(vote.response ?? poll, vote.data.option_id);
    const isApproved = commit.status === 'approved';
    const closesAt =
        current.closes_at === null ? null : new Date(current.closes_at);
    const isOpen = closesAt !== null && closesAt > now && !current.is_closed;
    const canVote =
        isApproved &&
        isOpen &&
        current.voted_option_id === null &&
        commit.user_id !== auth.user.id;
    const counts = countsOf(current.options);
    const percentages = counts === null ? null : percentagesOf(counts);
    const summary = summaryOf({
        closesAt,
        isApproved,
        isOpen,
        counts,
        hasVoted: current.voted_option_id !== null,
        now,
    });

    const castVote = async () => {
        if (choice === null || vote.processing) {
            return;
        }

        // The options and the button make way for the results: focus stays on the poll instead of getting lost.
        pollRef.current?.focus();

        try {
            await vote
                .optimistic(() => ({ option_id: choice }))
                .submit(CommitPollVoteController(commit), {
                    onSuccess: () => router.flushAll(),
                    onError: (errors) =>
                        toast.error(errors.option_id ?? failedVoteMessage),
                });
        } catch {
            toast.error(failedVoteMessage);
        }
    };

    return (
        <div
            ref={pollRef}
            tabIndex={-1}
            className="mt-3 rounded-md outline-none focus-visible:ring-[3px] focus-visible:ring-ring/50"
            data-test="poll-card"
        >
            {canVote ? (
                <fieldset>
                    <legend className="sr-only">Poll options</legend>
                    <div className="space-y-1.5">
                        {current.options.map((option) => (
                            <label
                                key={option.id}
                                className="flex cursor-pointer items-center gap-2.5 rounded-md border px-3 py-1.5 text-sm transition-colors hover:bg-accent has-checked:border-primary/40 has-checked:bg-primary-soft has-focus-visible:ring-[3px] has-focus-visible:ring-ring/50"
                                data-test="poll-option"
                            >
                                <input
                                    type="radio"
                                    name={name}
                                    value={option.id}
                                    checked={choice === option.id}
                                    onChange={() => setChoice(option.id)}
                                    className="size-4 shrink-0 accent-primary"
                                    data-test="poll-option-radio"
                                />
                                <span className="min-w-0 [overflow-wrap:anywhere] break-words">
                                    {option.label}
                                </span>
                            </label>
                        ))}
                    </div>
                </fieldset>
            ) : (
                <ul
                    aria-label={percentages ? 'Poll results' : 'Poll options'}
                    className="space-y-1.5"
                >
                    {current.options.map((option, index) => (
                        <li key={option.id}>
                            {percentages && counts && isApproved ? (
                                <PollResult
                                    option={option}
                                    votes={counts[index]}
                                    percentage={percentages[index]}
                                    isChoice={
                                        option.id === current.voted_option_id
                                    }
                                />
                            ) : (
                                <PollOptionLabel
                                    option={option}
                                    isChoice={
                                        option.id === current.voted_option_id
                                    }
                                />
                            )}
                        </li>
                    ))}
                </ul>
            )}

            <div className="mt-2 flex min-h-8 items-center justify-between gap-3">
                <p
                    role="status"
                    className="font-mono text-[13px] text-muted-foreground"
                    data-test="poll-summary"
                >
                    {summary}
                </p>
                {canVote && (
                    <Button
                        type="button"
                        size="sm"
                        onClick={castVote}
                        disabled={choice === null}
                        data-test="poll-vote-button"
                    >
                        Vote
                    </Button>
                )}
            </div>
        </div>
    );
}

function PollOptionLabel({
    option,
    isChoice,
}: {
    option: PollOption;
    isChoice: boolean;
}) {
    return (
        <p
            className={cn(
                'flex items-center gap-2 rounded-md border px-3 py-1.5 text-sm [overflow-wrap:anywhere] break-words text-muted-foreground',
                {
                    'border-primary/40 bg-primary-soft text-foreground':
                        isChoice,
                },
            )}
            data-test="poll-option"
        >
            <span className="min-w-0">{option.label}</span>
            {isChoice && <YourVote />}
        </p>
    );
}

function PollResult({
    option,
    votes,
    percentage,
    isChoice,
}: {
    option: PollOption;
    votes: number;
    percentage: number;
    isChoice: boolean;
}) {
    return (
        <div
            className="relative overflow-hidden rounded-md"
            data-test="poll-result"
        >
            <div
                aria-hidden
                className={cn(
                    'absolute inset-y-0 left-0 w-(--share) rounded-md bg-muted transition-[width] duration-500 ease-out motion-reduce:transition-none starting:w-0',
                    { 'bg-primary-soft': isChoice },
                )}
                style={{ '--share': `${percentage}%` } as CSSProperties}
            />
            <div className="relative flex items-center gap-2 px-3 py-1.5 text-sm">
                <span
                    className={cn(
                        'min-w-0 [overflow-wrap:anywhere] break-words',
                        { 'font-medium': isChoice },
                    )}
                >
                    {option.label}
                </span>
                {isChoice && <YourVote />}
                <span className="ml-auto shrink-0 font-mono text-[13px] text-muted-foreground tabular-nums">
                    <span aria-hidden>
                        {percentage}% · {votes}
                    </span>
                    <span className="sr-only">
                        {`: ${percentage} percent, ${votesLabel(votes)}`}
                    </span>
                </span>
            </div>
        </div>
    );
}

function YourVote() {
    return (
        <span className="shrink-0 text-primary" data-test="poll-choice">
            <Check aria-hidden className="size-4" />
            <span className="sr-only">, your vote</span>
        </span>
    );
}

/**
 * The votes of each option, or null while the server keeps them from the viewer.
 */
function countsOf(options: PollOption[]): number[] | null {
    const counts = options.map((option) => option.votes_count);

    return counts.every((count): count is number => count !== null)
        ? counts
        : null;
}

/**
 * Each option's share of the votes in whole percents, adding up to exactly 100 once anyone voted: the shares are
 * rounded down, and the points left go to the largest remainders (rounding each share alone can total 99 or 101).
 */
function percentagesOf(counts: number[]): number[] {
    const total = counts.reduce((sum, count) => sum + count, 0);

    if (total === 0) {
        return counts.map(() => 0);
    }

    const shares = counts.map((count) => (count * 100) / total);
    const percentages = shares.map((share) => Math.floor(share));
    const pointsLeft =
        100 - percentages.reduce((sum, percentage) => sum + percentage, 0);

    shares
        .map((share, index) => ({
            index,
            remainder: share - Math.floor(share),
        }))
        .sort((a, b) => b.remainder - a.remainder)
        .slice(0, pointsLeft)
        .forEach(({ index }) => {
            percentages[index] += 1;
        });

    return percentages;
}

/**
 * The poll with the viewer's choice marked as their vote, while the server has yet to confirm it and send the counts.
 */
function withVote(poll: Poll, optionId: string | null): Poll {
    if (optionId === null || poll.voted_option_id !== null) {
        return poll;
    }

    return { ...poll, voted_option_id: optionId };
}

/**
 * A poll's clock only starts once its commit is approved, so its author and moderators see it waiting until then.
 */
function summaryOf({
    closesAt,
    isApproved,
    isOpen,
    counts,
    hasVoted,
    now,
}: {
    closesAt: Date | null;
    isApproved: boolean;
    isOpen: boolean;
    counts: number[] | null;
    hasVoted: boolean;
    now: Date;
}): string | null {
    if (closesAt === null) {
        return 'Opens once approved';
    }

    if (!isApproved) {
        return null;
    }

    const status = isOpen ? timeLeft(closesAt, now) : 'Final results';

    if (counts === null) {
        if (hasVoted) {
            return `Vote cast · ${status}`;
        }

        return isOpen ? status : 'Poll closed';
    }

    const total = counts.reduce((sum, count) => sum + count, 0);

    return `${votesLabel(total)} · ${status}`;
}

function votesLabel(count: number): string {
    return `${count} ${count === 1 ? 'vote' : 'votes'}`;
}

function timeLeft(closesAt: Date, now: Date): string {
    const minutes = Math.max(
        1,
        Math.ceil((closesAt.getTime() - now.getTime()) / 60_000),
    );

    if (minutes < 60) {
        return `${minutes} ${minutes === 1 ? 'minute' : 'minutes'} left`;
    }

    const hours = Math.floor(minutes / 60);

    if (hours < 24) {
        return `${hours} ${hours === 1 ? 'hour' : 'hours'} left`;
    }

    const days = Math.floor(hours / 24);

    return `${days} ${days === 1 ? 'day' : 'days'} left`;
}
