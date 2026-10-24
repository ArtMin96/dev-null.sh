import type { ReactNode } from 'react';
import type { Lane, User } from '@/types';

/**
 * The live look for anything round: a rotating conic ring in the lane's color with a gap before `children`. It
 * holds still when `spinning` is off and under reduced motion.
 */
export function LiveRing({
    lane,
    spinning = true,
    className,
    children,
}: {
    lane: Lane;
    spinning?: boolean;
    className?: string;
    children: ReactNode;
}) {
    return null;
}

/**
 * A user's avatar inside a `LiveRing` with the LIVE pill on the ring's bottom edge, at the avatar's `size` in px.
 * `active` marks the one whose stream card is open.
 */
export function LiveAvatar({
    user,
    size,
    active = false,
}: {
    user: Pick<User, 'name' | 'avatar_url' | 'lane'>;
    size: 28 | 36 | 56;
    active?: boolean;
}) {
    return null;
}
