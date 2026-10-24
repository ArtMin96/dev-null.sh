import type { LivePlatform, LiveStream } from '@/types';

// Only type imports from `@/` here: `tests/Unit/*.test.ts` load this file straight from node, which cannot resolve them.

/**
 * Whether the stream is still on at `now`, which is the moment `ends_at` has not passed yet.
 */
export function isStreaming(
    stream: Pick<LiveStream, 'ends_at'>,
    now: Date,
): boolean {
    throw new Error('not implemented');
}

/**
 * The clock time a stream ends at in a timezone, like "18:00" or "6:00 PM" in the browser's locale, the same format
 * as `formatZonedTime`.
 */
export function formatLiveUntil(endsAt: string, timeZone: string): string {
    throw new Error('not implemented');
}

/**
 * How long until the stream ends, in whole minutes rounded up: "2h 10m", "1h 08m", "45m", and "0m" once it is over.
 */
export function formatEndsIn(endsAt: string, now: Date): string {
    throw new Error('not implemented');
}

/**
 * The provider's display name: "YouTube" or "Twitch".
 */
export function platformName(platform: LivePlatform): string {
    throw new Error('not implemented');
}

/**
 * The link-out label in each provider's own words: "Watch on YouTube" or "Join on Twitch".
 */
export function watchLabel(platform: LivePlatform): string {
    throw new Error('not implemented');
}

/**
 * The thumbnail URL with a `t` param set to the current 5-minute bucket, so polling refreshes a stale frame while
 * every render inside the same bucket keeps the same URL (and the browser cache).
 */
export function thumbnailUrl(
    stream: Pick<LiveStream, 'thumbnail_url'>,
    now: Date,
): string {
    throw new Error('not implemented');
}
