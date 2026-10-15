import type { LucideIcon } from 'lucide-react';
import { Tag } from 'lucide-react';
import type { ComponentType } from 'react';
import { ReleaseFields } from '@/components/commit-types/release-fields';
import type { CommitType } from '@/types';

export type ComposerFieldsProps = {
    /**
     * Tells the composer whether the fields are filled in enough to commit.
     */
    onCompletenessChange: (isComplete: boolean) => void;
};

export type ComposerType = {
    type: Exclude<CommitType, 'text'>;
    label: string;
    icon: LucideIcon;
    placeholder: string;
    Fields: ComponentType<ComposerFieldsProps>;
    /**
     * Whether a commit of this type may come with images, like the server's `allowsImages()`: yes unless set.
     */
    allowsImages?: boolean;
    /**
     * Whether "Commit" waits for the fields to report they are complete, since they start out empty.
     */
    startsIncomplete?: boolean;
};

/**
 * The types the composer offers next to plain text, in the order of their toggle buttons.
 */
export const composerTypes: ComposerType[] = [
    {
        type: 'release',
        label: 'Release',
        icon: Tag,
        placeholder: 'What did you release?',
        Fields: ReleaseFields,
    },
];
