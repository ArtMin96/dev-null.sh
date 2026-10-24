import { useSyncExternalStore } from 'react';
import { useNow } from '@/hooks/use-now';
import { formatEndsIn, formatLiveUntil, isStreaming } from '@/lib/live';
import { browserTimeZone } from '@/lib/utils';

const subscribe = () => () => {};

/**
 * When a stream ends, for the viewer's timezone and ticking with `useNow`: `until` like "18:00", `endsIn` like
 * "1h 08m", and `isOver` once `endsAt` has passed. Null during SSR and the first render, so server and client
 * markup match like `local-time.tsx`.
 */
export function useLiveUntil(
    endsAt: string,
): { until: string; endsIn: string; isOver: boolean } | null {
    const isClient = useSyncExternalStore(
        subscribe,
        () => true,
        () => false,
    );
    const now = useNow();

    if (!isClient) {
        return null;
    }

    return {
        until: formatLiveUntil(endsAt, browserTimeZone()),
        endsIn: formatEndsIn(endsAt, now),
        isOver: !isStreaming({ ends_at: endsAt }, now),
    };
}
