import type { ReactNode } from 'react';
import type { LiveCommit, LiveStream, User } from '@/types';

/**
 * What someone is streaming: the player, who it is and where, their latest commit, when it ends, the platform's
 * link-out and `actions` (such as View profile or End stream). It owns its play state and reports it through
 * `onPlayingChange`, so a popover can pin itself open while a stream plays.
 */
export function LivePreviewCard({
    user,
    stream,
    latestCommit,
    actions,
    onPlayingChange,
}: {
    user: Pick<User, 'name' | 'username' | 'avatar_url' | 'lane'>;
    stream: LiveStream;
    latestCommit?: LiveCommit | null;
    actions?: ReactNode;
    onPlayingChange?: (isPlaying: boolean) => void;
}) {
    return null;
}
