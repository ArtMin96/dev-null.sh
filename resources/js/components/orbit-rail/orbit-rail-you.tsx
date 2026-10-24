import type { OrbitRailSlotProps } from '@/components/orbit-rail/orbit-rail-item';
import type { Profile } from '@/types';

/**
 * The always-first "You" slot that keeps the rail from popping in: a dashed ring with a plus that opens
 * `GoLiveDialog`, or your own live ring whose card offers End stream.
 */
export function OrbitRailYou({
    user,
    ...slot
}: OrbitRailSlotProps & {
    user: Pick<Profile, 'name' | 'username' | 'avatar_url' | 'lane' | 'live'>;
}) {
    return null;
}
