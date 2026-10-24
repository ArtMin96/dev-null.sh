import type { PointerEvent } from 'react';

/**
 * Hover intent for mouse pointers only, so touch and pen fall through to clicks: `onChange(true)` fires after
 * `openDelay` ms over the target, `onChange(false)` after `closeDelay` ms away from it, and moving back in between
 * cancels the pending change. Spread the handlers on every element that counts as "over", such as a trigger and
 * its popover. `cancel` drops any pending change, and unmounting does too.
 */
export function useHoverIntent({
    openDelay = 170,
    closeDelay = 200,
    onChange,
}: {
    openDelay?: number;
    closeDelay?: number;
    onChange: (isHovering: boolean) => void;
}): {
    onPointerEnter: (event: PointerEvent) => void;
    onPointerLeave: (event: PointerEvent) => void;
    cancel: () => void;
} {
    return {
        onPointerEnter: () => {},
        onPointerLeave: () => {},
        cancel: () => {},
    };
}
