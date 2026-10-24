import type { PointerEvent, Ref } from 'react';
import type { LiveFollowingUser } from '@/types';

/**
 * What every slot on the rail takes from `OrbitRail`, which owns the open card, the hover intent and the roving
 * tabindex (keys are handled on the rail, so slots only report focus).
 */
export type OrbitRailSlotProps = {
    ref?: Ref<HTMLButtonElement>;
    isActive: boolean;
    tabIndex: 0 | -1;
    onPointerEnter: (event: PointerEvent) => void;
    onPointerLeave: (event: PointerEvent) => void;
    onClick: () => void;
    onFocus: () => void;
};

/**
 * One person on the rail: a button with their `LiveAvatar` and @handle, labelled "{name} is live on {Platform}
 * until 18:00", with `aria-expanded` and `aria-haspopup="dialog"` for the card it opens.
 */
export function OrbitRailItem({
    user,
    ...slot
}: OrbitRailSlotProps & { user: LiveFollowingUser }) {
    return null;
}
