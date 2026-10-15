import { ReleaseCard } from '@/components/commit-types/release-card';
import type { Commit } from '@/types';

export function CommitTypeContent({ commit }: { commit: Commit }) {
    switch (commit.type) {
        case 'release':
            return commit.release ? (
                <ReleaseCard release={commit.release} />
            ) : null;
        default:
            return null;
    }
}
