import { Link } from '@inertiajs/react';
import CommitController from '@/actions/App/Http/Controllers/CommitController';
import { UserAvatar } from '@/components/user-avatar';
import { useNow } from '@/hooks/use-now';
import { emojify } from '@/lib/emoji';
import { cn, formatRelativeTime, isDeleted } from '@/lib/utils';
import type { Commit, EmbeddedCommit } from '@/types';

/**
 * The commit a quote quotes, as one link to its page. A quoted commit that was deleted, or erased with its author's
 * account, leaves a `[deleted]` tombstone; one the viewer can no longer see reads `[unavailable]`.
 */
export function QuoteCard({ quote }: { quote: Commit }) {
    const quoted = quote.quoted_commit ?? null;

    if (quoted === null || isDeleted(quoted)) {
        return (
            <QuoteTombstone
                wasDeleted={quoted !== null || quote.quoted_commit_id === null}
            />
        );
    }

    return (
        <Link
            href={CommitController.show({ user: quoted.user, commit: quoted })}
            className="mt-3 block rounded-lg border px-3 py-2.5 transition-colors hover:bg-accent/50 focus-visible:bg-accent/50"
            data-test="quote-card"
        >
            <QuotedCommitSummary quoted={quoted} />
        </Link>
    );
}

function QuoteTombstone({ wasDeleted }: { wasDeleted: boolean }) {
    return (
        <p
            className="mt-3 flex items-center gap-1.5 rounded-lg border border-dashed px-3 py-2.5 text-sm text-muted-foreground"
            data-test="quote-tombstone"
        >
            <span
                aria-hidden
                className="size-5 shrink-0 rounded-full border-2 border-dashed border-rail"
            />
            <span className="font-mono text-xs">
                {wasDeleted ? '[deleted]' : '[unavailable]'}
            </span>
            {wasDeleted
                ? 'This commit was deleted.'
                : "This commit isn't available."}
        </p>
    );
}

/**
 * The compact look of a quoted commit: its author, its age, its status when not approved (to its author and to
 * moderators), its type and the start of its body, never its type's content.
 */
export function QuotedCommitSummary({ quoted }: { quoted: EmbeddedCommit }) {
    const now = useNow();

    return (
        <>
            <div className="flex min-w-0 items-center gap-1.5 text-sm">
                <UserAvatar user={quoted.user} className="size-5 shrink-0" />
                <span className="truncate font-semibold">
                    {quoted.user.name}
                </span>
                <span className="truncate font-mono text-xs text-muted-foreground">
                    @{quoted.user.username}
                </span>
                <span aria-hidden className="text-muted-foreground">
                    ·
                </span>
                <time
                    dateTime={quoted.created_at}
                    title={new Date(quoted.created_at).toLocaleString()}
                    className="shrink-0 font-mono text-xs text-muted-foreground"
                >
                    {formatRelativeTime(quoted.created_at, now)}
                </time>
                {quoted.status !== 'approved' && (
                    <span
                        className={cn(
                            'shrink-0 rounded-full px-1.5 font-mono text-[11px]',
                            quoted.status === 'pending'
                                ? 'bg-amber-500/10 text-amber-700 dark:text-amber-400'
                                : 'bg-destructive/10 text-destructive',
                        )}
                        data-test="quote-status"
                    >
                        {quoted.status}
                    </span>
                )}
                {quoted.type !== 'text' && (
                    <span
                        className="ml-auto shrink-0 rounded-full bg-muted px-2 py-0.5 font-mono text-[11px] text-muted-foreground capitalize"
                        data-test="quote-type"
                    >
                        {quoted.type}
                    </span>
                )}
            </div>
            <p
                className="mt-1 line-clamp-4 text-sm leading-relaxed [overflow-wrap:anywhere] break-words whitespace-pre-wrap"
                data-test="quote-body"
            >
                {emojify(quoted.body)}
            </p>
        </>
    );
}
