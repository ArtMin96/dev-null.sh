/**
 * When a stream ends, for the viewer's timezone and ticking with `useNow`: `until` like "18:00", `endsIn` like
 * "1h 08m", and `isOver` once `endsAt` has passed. Null during SSR and the first render, so server and client
 * markup match like `local-time.tsx`.
 */
export function useLiveUntil(
    endsAt: string,
): { until: string; endsIn: string; isOver: boolean } | null {
    return null;
}
