import type { LiveStream, User } from '@/types';

/**
 * The stream's 16:9 thumbnail with the 5-minute cache-bust from `thumbnailUrl`, so it never shifts layout.
 */
export function StreamThumbnail({
    stream,
    className,
}: {
    stream: Pick<LiveStream, 'thumbnail_url'>;
    className?: string;
}) {
    return null;
}

/**
 * The thumbnail with a play button that swaps in the provider's player through `EmbedFrame` while `isPlaying`. With
 * no `stream.embed` it only links out to `stream.url`.
 */
export function StreamPlayer({
    user,
    stream,
    isPlaying,
    onPlayingChange,
}: {
    user: Pick<User, 'name'>;
    stream: LiveStream;
    isPlaying: boolean;
    onPlayingChange: (isPlaying: boolean) => void;
}) {
    return null;
}
