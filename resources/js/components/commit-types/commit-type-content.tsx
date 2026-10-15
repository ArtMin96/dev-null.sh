import { PollCard } from '@/components/commit-types/poll-card';
import { ReleaseCard } from '@/components/commit-types/release-card';
import { SnippetCard } from '@/components/commit-types/snippet-card';
import type { Commit } from '@/types';

export function CommitTypeContent({ commit }: { commit: Commit }) {
    switch (commit.type) {
        case 'release':
            return commit.release ? (
                <ReleaseCard release={commit.release} />
            ) : null;
        case 'poll':
            return commit.poll ? (
                <PollCard commit={commit} poll={commit.poll} />
            ) : null;
        case 'snippet':
            return commit.snippet ? (
                <SnippetCard snippet={commit.snippet} />
            ) : null;
        default:
            return null;
    }
}
