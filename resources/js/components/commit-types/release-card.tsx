import { ExternalLink, Tag } from 'lucide-react';
import { useState } from 'react';
import { LinkifiedText } from '@/components/linkified-text';
import { cn } from '@/lib/utils';
import type { Release } from '@/types';

export function ReleaseCard({ release }: { release: Release }) {
    const [isClamped, setIsClamped] = useState(false);
    const [isExpanded, setIsExpanded] = useState(false);

    return (
        <div
            className="mt-3 rounded-lg border px-3 py-2.5"
            data-test="release-card"
        >
            <p
                className="flex w-fit max-w-full items-center gap-1.5 rounded-md bg-muted px-2 py-0.5 font-mono text-[13px]"
                data-test="release-tag"
            >
                <Tag
                    aria-hidden
                    className="size-3.5 shrink-0 text-muted-foreground"
                />
                <span className="truncate">{release.project}</span>
                <span className="shrink-0 font-medium">{release.version}</span>
            </p>

            {release.notes && (
                <>
                    <p
                        ref={(element) => {
                            if (element) {
                                setIsClamped(
                                    element.scrollHeight > element.clientHeight,
                                );
                            }
                        }}
                        className={cn(
                            'mt-2 text-sm leading-relaxed [overflow-wrap:anywhere] break-words whitespace-pre-wrap text-muted-foreground',
                            { 'line-clamp-4': !isExpanded },
                        )}
                        data-test="release-notes"
                    >
                        <LinkifiedText text={release.notes} />
                    </p>
                    {isClamped && !isExpanded && (
                        <button
                            type="button"
                            onClick={() => setIsExpanded(true)}
                            className="mt-1 rounded-sm text-sm font-medium text-primary hover:underline"
                            data-test="release-notes-more-button"
                        >
                            Show more
                        </button>
                    )}
                </>
            )}

            {release.url && (
                <a
                    href={release.url}
                    target="_blank"
                    rel="noopener noreferrer nofollow ugc"
                    onClick={(event) => event.stopPropagation()}
                    className="mt-2 flex w-fit items-center gap-1 rounded-sm text-sm font-medium text-primary hover:underline"
                    data-test="release-link"
                >
                    View release
                    <ExternalLink aria-hidden className="size-3.5" />
                </a>
            )}
        </div>
    );
}
