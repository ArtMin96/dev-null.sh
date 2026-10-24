/**
 * The go-live form (`PUT settings/live`): a stream URL and how many hours to show it as live, from the profile menu
 * or the rail's You slot.
 */
export function GoLiveDialog({
    open,
    onOpenChange,
}: {
    open: boolean;
    onOpenChange: (open: boolean) => void;
}) {
    return null;
}

/**
 * Ends your stream (`DELETE settings/live`) and flushes cached pages so no live marker lingers; `isEnding` is true
 * while the request runs.
 */
export function useEndStream(): { endStream: () => void; isEnding: boolean } {
    return { endStream: () => {}, isEnding: false };
}
