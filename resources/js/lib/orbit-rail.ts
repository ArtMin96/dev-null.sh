import type { LiveStream } from '@/types';

// Only type imports from `@/` here: `tests/Unit/*.test.ts` load this file straight from node, which cannot resolve them.

/**
 * The people still live at `now`: drops anyone whose `live.ends_at` is at or before `now` and keeps the server's
 * order (soonest-ending first), so the rail empties itself between polls without a reload.
 */
export function visibleLive<T extends { live: Pick<LiveStream, 'ends_at'> }>(
    users: readonly T[],
    now: Date,
): T[] {
    throw new Error('not implemented');
}

/**
 * The roving tabindex target for a key pressed on the item at `current` out of `count` items: ArrowRight moves to
 * the next and ArrowLeft to the previous, both wrapping around the ends, Home goes to the first and End to the
 * last. Returns null for any other key, or when there are no items, so the caller leaves the event alone.
 */
export function nextIndex(
    current: number,
    key: string,
    count: number,
): number | null {
    throw new Error('not implemented');
}

/**
 * The rail's right-hand summary for how many people you follow are live, like "Live now · 6 you follow", and
 * "Live now · 1 you follow" for one. With nobody live it reads "Nobody you follow is live".
 */
export function railSummary(count: number): string {
    throw new Error('not implemented');
}
