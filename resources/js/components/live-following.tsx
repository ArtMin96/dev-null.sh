import { usePage } from '@inertiajs/react';
import { ArrowUpRight } from 'lucide-react';
import { LiveAvatar } from '@/components/live/live-avatar';
import { useLiveUntil } from '@/hooks/use-live-until';
import type { LiveFollowingUser } from '@/types';

/**
 * The aside's "Live now" list, soonest-ending first as the server sends it. Log pages leave it out because the
 * Orbit Rail above the feed already shows the same people.
 */
export function LiveFollowing() {
    const { component, props } = usePage();
    const users = props.liveFollowing ?? [];

    if (component === 'log/show' || users.length === 0) return null;

    // Rows drop themselves once their stream ends, and the whole box goes with the last one.
    return (
        <section
            aria-labelledby="aside-live-following"
            className="-mt-4 rounded-lg border bg-card p-3 not-has-[li]:hidden"
            data-test="live-following"
        >
            <h2
                id="aside-live-following"
                className="mb-1.5 flex items-center justify-between text-sm font-semibold"
            >
                Live now
            </h2>
            <ul className="space-y-0.5">
                {users.map((user) => (
                    <LiveFollowingItem key={user.id} user={user} />
                ))}
            </ul>
        </section>
    );
}

function LiveFollowingItem({ user }: { user: LiveFollowingUser }) {
    const live = useLiveUntil(user.live.ends_at);

    if (live?.isOver) return null;

    return (
        <li>
            <a
                href={user.live.url}
                target="_blank"
                rel="noopener noreferrer"
                className="group -mx-2 flex items-center gap-2.5 rounded-lg px-2 py-2 transition-colors hover:bg-accent focus-visible:outline-2 focus-visible:outline-primary"
                aria-label={
                    live
                        ? `Watch ${user.name} live until ${live.until}`
                        : `Watch ${user.name} live`
                }
            >
                <span className="mb-1.5 flex shrink-0">
                    <LiveAvatar user={user} size={28} />
                </span>
                <span className="min-w-0 flex-1">
                    <span className="block truncate text-sm font-semibold">
                        {user.name}
                    </span>
                    <span className="block truncate font-mono text-xs text-muted-foreground tabular-nums">
                        @{user.username}
                        {live && ` · until ${live.until}`}
                    </span>
                </span>
                <ArrowUpRight
                    aria-hidden
                    className="size-4 shrink-0 text-muted-foreground transition-colors group-hover:text-primary"
                />
            </a>
        </li>
    );
}
