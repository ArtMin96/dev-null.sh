import type { User } from '@/types/auth';

export type CommitStatus = 'pending' | 'approved' | 'rejected';

export type CommitType = 'text' | 'release' | 'poll' | 'snippet';

export type LinkPreview = {
    url: string;
    title: string | null;
    description: string | null;
    image_url: string | null;
    site_name: string | null;
};

export type CommitImage = {
    id: string;
    url: string;
    width: number;
    height: number;
};

export type BodySegment =
    | { type: 'text'; text: string }
    | { type: 'code'; code: string }
    | { type: 'block'; language: string | null; html: string };

export type Release = {
    project: string;
    version: string;
    url: string | null;
    notes: string | null;
};

/**
 * An option's votes stay null until the viewer may see the results: once they voted, on their own poll, or once the
 * poll closed.
 */
export type PollOption = {
    id: string;
    label: string;
    votes_count: number | null;
};

export type Poll = {
    closes_at: string | null;
    is_closed: boolean;
    voted_option_id: string | null;
    options: PollOption[];
};

export type Snippet = {
    language: string;
    language_label: string;
    filename: string | null;
    code: string;
    highlighted_html: string;
};

export type SnippetLanguageOption = {
    value: string;
    label: string;
};

export type Commit = {
    id: string;
    user_id: string;
    parent_id: string | null;
    body: string;
    body_segments: BodySegment[];
    status: CommitStatus;
    type: CommitType;
    created_at: string;
    updated_at: string;
    deleted_at?: null;
    stars_count: number;
    starred: boolean;
    user: User;
    replies_count?: number;
    is_saved?: boolean;
    parent?: (Omit<Commit, 'parent'> & { user: User }) | DeletedCommit | null;
    link_preview?: LinkPreview | null;
    images?: CommitImage[];
    release?: Release | null;
    poll?: Poll | null;
    snippet?: Snippet | null;
};

export type DeletedCommit = {
    id: string;
    parent_id: string | null;
    deleted_at: string;
    replies_count?: number;
};

export type ThreadCommit = Commit | DeletedCommit;

export type CursorPaginated<T> = {
    data: T[];
    next_cursor: string | null;
    prev_cursor: string | null;
};
